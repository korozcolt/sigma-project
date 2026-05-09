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
            result_selector = ".resultado, #resultado, [class*='resultado'], table, .puesto"
            deadline = time.time() + 120

            found = False
            while time.time() < deadline:
                try:
                    page.wait_for_selector(result_selector, timeout=2000)
                    found = True
                    break
                except Exception:
                    pass

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

    Expected format (each on its own line):
        PUESTO DE VOTACIÓN: 02 - IE SAN JOSE C I P
        ZONA: 01
        MESA: 008
        DIRECCIÓN: CRA 7 No. 10-50
        MUNICIPIO: SINCELEJO
        DEPARTAMENTO: SUCRE
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

    for line in text.splitlines():
        line = line.strip()
        if not line or ":" not in line:
            continue

        upper = line.upper()

        if "PUESTO" in upper:
            value = line.split(":", 1)[1].strip()
            result["puesto_nombre"] = value
            # Extract place code: leading digits (up to 2 chars) before the dash/space
            parts = value.split("-", 1)
            code_part = parts[0].strip() if parts else ""
            if code_part.isdigit():
                result["puesto_codigo"] = code_part[:2]
            else:
                result["puesto_codigo"] = ""

        elif "ZONA" in upper:
            result["zona_codigo"] = line.split(":", 1)[1].strip()

        elif "MESA" in upper:
            result["mesa_numero"] = line.split(":", 1)[1].strip()

        elif "DIRECCI" in upper:  # handles DIRECCIÓN / DIRECCION
            result["direccion"] = line.split(":", 1)[1].strip()

        elif "MUNICIPIO" in upper:
            result["municipio"] = line.split(":", 1)[1].strip()

        elif "DEPARTAMENTO" in upper:
            result["departamento"] = line.split(":", 1)[1].strip()

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
