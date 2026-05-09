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

            # Click Consultar button
            page.get_by_role("button", name="Consultar").click()

            # Notify the poller that we are now waiting for the CAPTCHA
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

    The result page layout groups column headers together, then their values:

        Puesto          <- column headers (all 3 on consecutive lines)
        Mesa
        Zona
        02 - IE SAN JOSE C I P   <- values in same order
        13
        03

        Departamento    <- column headers
        Municipio
        Dirección
        SUCRE           <- values
        SINCELEJO
        CL 22 No. 10A-380

    Strategy: find the line that is EXACTLY "Puesto" (not "Puesto de Votación"),
    confirm "Mesa" and "Zona" follow, then read values positionally.
    Same for the Departamento/Municipio/Dirección group.
    Fallback: inline "Label: value" scan if positional fails.
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

    # ── Positional group 1: Puesto / Mesa / Zona ────────────────────────────
    for i, line in enumerate(lines):
        n = norm(line)
        # Match exactly "PUESTO" (not "PUESTO DE VOTACIÓN" which has spaces after)
        if n == "PUESTO" or n == "PUESTO DE VOTACIÓN":
            # Check next lines for Mesa and Zona
            remaining = lines[i + 1:]
            non_empty = [l for l in remaining[:6] if l]
            # Look for Mesa and Zona within the next 3 lines
            if len(non_empty) >= 2:
                heads = [norm(non_empty[0]), norm(non_empty[1]) if len(non_empty) > 1 else ""]
                if "MESA" in heads[0] or "ZONA" in heads[0] or "MESA" in heads[1] or "ZONA" in heads[1]:
                    # Find where values start (after the 3 headers)
                    header_count = 0
                    val_start = i + 1
                    while val_start < len(lines) and header_count < 3:
                        if norm(lines[val_start]) in ("PUESTO", "MESA", "ZONA",
                                                       "PUESTO DE VOTACIÓN"):
                            header_count += 1
                            val_start += 1
                        else:
                            break
                    vals = [lines[j] for j in range(val_start, min(val_start + 3, len(lines)))]
                    if len(vals) >= 1:
                        result["puesto_nombre"] = vals[0]
                    if len(vals) >= 2:
                        result["mesa_numero"] = vals[1]
                    if len(vals) >= 3:
                        result["zona_codigo"] = vals[2]
                    break

    # ── Positional group 2: Departamento / Municipio / Dirección ────────────
    for i, line in enumerate(lines):
        n = norm(line)
        if n == "DEPARTAMENTO":
            remaining = [lines[j] for j in range(i + 1, min(i + 8, len(lines)))]
            # Skip past Municipio and Dirección headers, collect values
            header_count = 0
            val_start = i + 1
            while val_start < len(lines) and header_count < 3:
                u = norm(lines[val_start])
                if u in ("DEPARTAMENTO", "MUNICIPIO", "DIRECCIÓN", "DIRECCION") or u.startswith("DIRECCI"):
                    header_count += 1
                    val_start += 1
                else:
                    break
            vals = [lines[j] for j in range(val_start, min(val_start + 3, len(lines)))]
            if len(vals) >= 1:
                result["departamento"] = vals[0]
            if len(vals) >= 2:
                result["municipio"] = vals[1]
            if len(vals) >= 3:
                result["direccion"] = vals[2]
            break

    # ── Fallback: inline "Label: value" scan (handles alternative layouts) ──
    if not result["puesto_nombre"] or not result["departamento"]:
        for line in lines:
            if ":" not in line:
                continue
            u = line.upper()
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
