"""
Registraduria polling-place lookup microservice.

Exposes:
    POST /lookup  — Start an async browser lookup for a cedula. Returns session_id.
    GET  /result/<session_id> — Poll result for a given session.

The browser (visible Chrome) navigates to the Registraduria portal, fills the cedula,
and waits for the user to solve the CAPTCHA. Once the result page is visible, it parses
the polling-place data and stores it in the in-memory sessions dict.
"""

import threading
import uuid
import time

from flask import Flask, jsonify, request
from playwright.sync_api import sync_playwright

app = Flask(__name__)

sessions: dict = {}
sessions_lock = threading.Lock()


def run_lookup(session_id: str, cedula: str) -> None:
    """Background thread: opens a visible Chromium window, navigates to the Registraduria
    portal, fills the cedula, and waits for the user to solve the CAPTCHA.
    Updates sessions[session_id] on completion or error.
    """
    with sync_playwright() as p:
        browser = None
        try:
            browser = p.chromium.launch(headless=False)
            page = browser.new_page()

            page.goto("https://eleccionescolombia.registraduria.gov.co/identificacion")

            # Fill cedula — try multiple selectors for robustness
            try:
                page.fill('input[name="nuip"]', cedula)
            except Exception:
                try:
                    page.fill('input[type="number"]', cedula)
                except Exception:
                    page.fill('input#nuip', cedula)

            # The "Consultar" button is disabled until the reCAPTCHA is solved.
            # We do NOT click it — the user must solve the CAPTCHA and click the
            # button themselves in the visible browser window.
            with sessions_lock:
                sessions[session_id]["status"] = "waiting_captcha"

            # Wait up to 120 seconds for a result element to appear
            # Wait up to 120 s for the result — detect by presence of "Puesto de Votación"
            # text that only appears on the result page (not the input form).
            deadline = time.time() + 120
            found = False
            while time.time() < deadline:
                try:
                    body_text = page.inner_text("body")
                    # The result page always contains the cedula and "Puesto" section
                    if cedula in body_text and ("Puesto" in body_text or "PUESTO" in body_text):
                        found = True
                        break
                except Exception:
                    pass
                time.sleep(2)

            if not found:
                raise TimeoutError("Se agotó el tiempo esperando la respuesta de Registraduría (120 s).")

            # Give the page a moment to fully render
            time.sleep(1)

            body_text = page.inner_text("body")
            data = _parse_result_text(body_text)

            with sessions_lock:
                sessions[session_id]["status"] = "done"
                sessions[session_id]["data"] = data

        except Exception as exc:
            with sessions_lock:
                sessions[session_id]["status"] = "error"
                sessions[session_id]["error"] = str(exc)
        finally:
            if browser:
                try:
                    browser.close()
                except Exception:
                    pass


def _parse_result_text(text: str) -> dict:
    """Parse the Registraduria result page body text into a structured dict.

    The real page layout is interleaved label → value on consecutive lines:

        Puesto
        02 - IE SAN JOSE C I P
        Mesa
        13
        Zona
        03
        Departamento
        SUCRE
        Municipio
        SINCELEJO
        Dirección
        CL 22 No. 10A-380

    Strategy: find exact label lines and grab the immediately following
    non-empty line as the value.
    Fallback: inline "Label: value" (colon on same line).
    """
    result = {
        "puesto_nombre": "",
        "puesto_codigo": "",
        "zona_codigo": "",
        "mesa_numero": "",
        "departamento": "",
        "municipio": "",
        "direccion": "",
    }

    lines = [ln.strip() for ln in text.splitlines() if ln.strip()]

    def norm(s: str) -> str:
        return s.upper().strip()

    def next_value(lines: list, i: int) -> str:
        """Return the first non-empty line after index i."""
        for j in range(i + 1, min(i + 4, len(lines))):
            if lines[j].strip():
                return lines[j].strip()
        return ""

    # ── Pass 1: interleaved label → value (real page layout) ────────────────
    for i, line in enumerate(lines):
        n = norm(line)

        if n == "PUESTO" and not result["puesto_nombre"]:
            result["puesto_nombre"] = next_value(lines, i)

        elif n == "MESA" and not result["mesa_numero"]:
            result["mesa_numero"] = next_value(lines, i)

        elif n == "ZONA" and not result["zona_codigo"]:
            result["zona_codigo"] = next_value(lines, i)

        elif n == "DEPARTAMENTO" and not result["departamento"]:
            result["departamento"] = next_value(lines, i)

        elif n == "MUNICIPIO" and not result["municipio"]:
            result["municipio"] = next_value(lines, i)

        elif (n == "DIRECCIÓN" or n == "DIRECCION") and not result["direccion"]:
            result["direccion"] = next_value(lines, i)

    # ── Pass 2: inline "Label: value" fallback ───────────────────────────────
    if not result["puesto_nombre"] or not result["departamento"]:
        for line in lines:
            if ":" not in line:
                continue
            key, _, val = line.partition(":")
            val = val.strip()
            if not val:
                continue
            ku = key.upper().strip()
            if "PUESTO" in ku and not result["puesto_nombre"]:
                result["puesto_nombre"] = val
            elif "ZONA" in ku and not result["zona_codigo"]:
                result["zona_codigo"] = val
            elif "MESA" in ku and not result["mesa_numero"]:
                result["mesa_numero"] = val
            elif "DIRECCI" in ku and not result["direccion"]:
                result["direccion"] = val
            elif "MUNICIPIO" in ku and not result["municipio"]:
                result["municipio"] = val
            elif "DEPARTAMENTO" in ku and not result["departamento"]:
                result["departamento"] = val

    # ── Derive place_code from puesto_nombre ────────────────────────────────
    nombre = result["puesto_nombre"]
    if nombre:
        code_part = nombre.split("-", 1)[0].strip()
        result["puesto_codigo"] = code_part if code_part.isdigit() else ""

    return result


@app.route("/lookup", methods=["POST"])
def lookup() -> tuple:
    """Start an async browser lookup for a cedula.

    Request body (JSON): {"cedula": "<document_number>"}
    Response: {"session_id": "<uuid>"}
    """
    body = request.get_json(silent=True) or {}
    cedula = body.get("cedula", "").strip()

    if not cedula:
        return jsonify({"error": "El campo 'cedula' es requerido."}), 400

    session_id = str(uuid.uuid4())

    with sessions_lock:
        sessions[session_id] = {"status": "pending", "data": None, "error": None}

    thread = threading.Thread(target=run_lookup, args=(session_id, cedula), daemon=True)
    thread.start()

    return jsonify({"session_id": session_id}), 200


@app.route("/result/<session_id>", methods=["GET"])
def result(session_id: str) -> tuple:
    """Return the current status of a lookup session.

    Response: {"status": "pending"|"waiting_captcha"|"done"|"error", "data": {...}|null, "error": "..."|null}
    """
    with sessions_lock:
        session = sessions.get(session_id)

    if session is None:
        return jsonify({"error": f"Sesión '{session_id}' no encontrada."}), 404

    return jsonify(session), 200


if __name__ == "__main__":
    app.run(port=5757, debug=False)
