"""
Registraduria polling-place lookup microservice.

Architecture:
- Each lookup runs in a daemon thread with its own asyncio event loop.
- Playwright async API allows page.goto() and command processing to run
  concurrently (goto as a task, commands polled every 100ms).
- Each screenshot/click/viewport request carries its own asyncio.Queue for
  the response, so concurrent requests don't mix up responses.
- Flask routes bridge sync ↔ async via threading.Event / queue.Queue.
"""

import asyncio
import queue
import threading
import time
import uuid

from flask import Flask, Response, jsonify, request
from playwright.async_api import async_playwright

app = Flask(__name__)

sessions: dict = {}
sessions_lock = threading.Lock()

# session_id -> asyncio.Queue (cmd queue on the lookup thread's event loop)
# We also store a threading.Queue for the bridge between Flask (sync) and asyncio
session_bridges: dict = {}         # session_id -> threading.Queue  (Flask→async)
session_bridges_lock = threading.Lock()

_LOADING_PNG: bytes = b""          # 1×1 transparent PNG served while page loads


def _make_loading_png() -> bytes:
    """Return a minimal 1×1 transparent PNG."""
    import base64
    data = (
        b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00"
        b"\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx"
        b"\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82"
    )
    return data


def _send_cmd_sync(session_id: str, cmd: dict, timeout: float = 8.0):
    """
    Send a command to the async lookup loop and wait for the response.
    Each call gets its own threading.Queue for private response routing.
    """
    resp_q: queue.Queue = queue.Queue()
    cmd["_resp_q"] = resp_q

    with session_bridges_lock:
        bridge = session_bridges.get(session_id)
    if bridge is None:
        return None

    bridge.put(cmd)
    try:
        return resp_q.get(timeout=timeout)
    except queue.Empty:
        return None


# ---------------------------------------------------------------------------
# Async lookup core
# ---------------------------------------------------------------------------

async def _async_lookup(session_id: str, cedula: str, bridge: queue.Queue) -> None:
    """
    Async Playwright lookup. Runs in its own event loop on a daemon thread.
    Processes commands from `bridge` while navigating / waiting for CAPTCHA.
    """
    async def process_bridge() -> None:
        """Drain the bridge queue and run Playwright commands."""
        while True:
            try:
                cmd = bridge.get_nowait()
            except queue.Empty:
                break
            resp_q: queue.Queue = cmd.pop("_resp_q", None)
            try:
                t = cmd.get("type")
                if t == "screenshot":
                    # JPEG is ~5x smaller than PNG — much faster over the network
                    img = await page.screenshot(type="jpeg", quality=75)
                    if resp_q:
                        resp_q.put({"ok": True, "data": img, "mime": "image/jpeg"})
                elif t == "click":
                    x, y = cmd["x"], cmd["y"]
                    # Try to click inside any frame whose bounding box contains (x, y)
                    # This handles reCAPTCHA iframes that intercept mouse events
                    clicked_in_frame = False
                    for frame in page.frames:
                        if frame == page.main_frame:
                            continue
                        try:
                            frame_el = await frame.frame_element()
                            box = await frame_el.bounding_box()
                            if box and box["x"] <= x <= box["x"] + box["width"] and box["y"] <= y <= box["y"] + box["height"]:
                                # Translate to frame-local coords
                                fx = x - box["x"]
                                fy = y - box["y"]
                                await frame.evaluate(f"() => {{ const el = document.elementFromPoint({fx}, {fy}); if(el) el.click(); }}")
                                await page.mouse.click(x, y)
                                clicked_in_frame = True
                                break
                        except Exception:
                            pass
                    if not clicked_in_frame:
                        await page.mouse.click(x, y)
                    if resp_q:
                        resp_q.put({"ok": True})
                elif t == "viewport":
                    vp = page.viewport_size or {"width": 1280, "height": 800}
                    if resp_q:
                        resp_q.put({"ok": True, "data": vp})
                else:
                    if resp_q:
                        resp_q.put({"ok": False, "error": f"unknown cmd {t}"})
            except Exception as exc:
                if resp_q:
                    resp_q.put({"ok": False, "error": str(exc)})

    async with async_playwright() as p:
        # headless=False: gov site blocks headless Chromium at TCP level.
        # VPS: run with Xvfb → DISPLAY=:99 python3 app.py (virtual display).
        # Mac dev: opens real Chrome window.
        browser = await p.chromium.launch(
            headless=False,
            args=[
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--ignore-certificate-errors",
            ],
        )
        context = await browser.new_context(
            viewport={"width": 1280, "height": 800},
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
            ignore_https_errors=True,
        )
        page = await context.new_page()

        try:
            # Navigate while concurrently serving screenshot/click commands
            goto_task = asyncio.create_task(
                page.goto(
                    "https://eleccionescolombia.registraduria.gov.co/identificacion",
                    wait_until="domcontentloaded",
                    timeout=30_000,
                )
            )

            while not goto_task.done():
                await process_bridge()
                await asyncio.sleep(0.1)

            # Propagate any navigation exception
            await goto_task

            # Fill cedula
            for selector in ['input[name="nuip"]', 'input[type="number"]', "input#nuip"]:
                try:
                    await page.fill(selector, cedula, timeout=3_000)
                    break
                except Exception:
                    continue

            with sessions_lock:
                sessions[session_id]["status"] = "waiting_captcha"

            # Monitor for result while serving commands
            deadline = time.time() + 180
            found = False
            while time.time() < deadline:
                await process_bridge()

                try:
                    body = await page.inner_text("body")
                    if cedula in body and ("Puesto" in body or "PUESTO" in body):
                        found = True
                        break
                except Exception:
                    pass

                await asyncio.sleep(0.15)

            if not found:
                raise TimeoutError("Tiempo agotado esperando la respuesta de Registraduría (180 s).")

            await asyncio.sleep(1)
            body = await page.inner_text("body")
            data = _parse_result_text(body)

            with sessions_lock:
                sessions[session_id]["status"] = "done"
                sessions[session_id]["data"] = data

            # Serve a few more screenshots so the result page renders in the modal
            deadline_final = time.time() + 4
            while time.time() < deadline_final:
                await process_bridge()
                await asyncio.sleep(0.1)

        except Exception as exc:
            with sessions_lock:
                sessions[session_id]["status"] = "error"
                sessions[session_id]["error"] = str(exc)
        finally:
            with session_bridges_lock:
                session_bridges.pop(session_id, None)
            try:
                await browser.close()
            except Exception:
                pass


def _run_lookup_thread(session_id: str, cedula: str, bridge: queue.Queue) -> None:
    """Entry point for the daemon thread — runs its own asyncio event loop."""
    asyncio.run(_async_lookup(session_id, cedula, bridge))


# ---------------------------------------------------------------------------
# Result text parser
# ---------------------------------------------------------------------------

def _parse_result_text(text: str) -> dict:
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

    def next_value(lst: list, i: int) -> str:
        for j in range(i + 1, min(i + 4, len(lst))):
            if lst[j].strip():
                return lst[j].strip()
        return ""

    for i, line in enumerate(lines):
        n = line.upper().strip()
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
        elif n in ("DIRECCIÓN", "DIRECCION") and not result["direccion"]:
            result["direccion"] = next_value(lines, i)

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

    nombre = result["puesto_nombre"]
    if nombre:
        code_part = nombre.split("-", 1)[0].strip()
        result["puesto_codigo"] = code_part if code_part.isdigit() else ""

    return result


# ---------------------------------------------------------------------------
# Flask routes
# ---------------------------------------------------------------------------

@app.route("/lookup", methods=["POST"])
def lookup():
    body = request.get_json(silent=True) or {}
    cedula = str(body.get("cedula", "")).strip()
    if not cedula:
        return jsonify({"error": "El campo 'cedula' es requerido."}), 400

    session_id = str(uuid.uuid4())
    bridge: queue.Queue = queue.Queue()

    with sessions_lock:
        sessions[session_id] = {"status": "pending", "data": None, "error": None}
    with session_bridges_lock:
        session_bridges[session_id] = bridge

    thread = threading.Thread(
        target=_run_lookup_thread,
        args=(session_id, cedula, bridge),
        daemon=True,
    )
    thread.start()
    return jsonify({"session_id": session_id}), 200


@app.route("/result/<session_id>", methods=["GET"])
def result(session_id: str):
    with sessions_lock:
        session = sessions.get(session_id)
    if session is None:
        return jsonify({"error": f"Sesión '{session_id}' no encontrada."}), 404
    return jsonify(session), 200


@app.route("/screenshot/<session_id>", methods=["GET"])
def screenshot_route(session_id: str):
    resp = _send_cmd_sync(session_id, {"type": "screenshot"}, timeout=8.0)
    if resp is None:
        # Session not active yet — return transparent 1×1 PNG so <img> doesn't break
        return Response(
            _make_loading_png(),
            mimetype="image/png",
            headers={"Cache-Control": "no-store"},
        )
    if not resp.get("ok"):
        return Response(
            _make_loading_png(),
            mimetype="image/png",
            headers={"Cache-Control": "no-store"},
        )
    return Response(
        resp["data"],
        mimetype=resp.get("mime", "image/jpeg"),
        headers={"Cache-Control": "no-store, no-cache, must-revalidate"},
    )


@app.route("/click/<session_id>", methods=["POST"])
def click_route(session_id: str):
    body = request.get_json(silent=True) or {}
    try:
        x, y = int(body["x"]), int(body["y"])
    except (KeyError, ValueError, TypeError):
        return jsonify({"error": "Parámetros x e y requeridos (enteros)."}), 400

    resp = _send_cmd_sync(session_id, {"type": "click", "x": x, "y": y}, timeout=8.0)
    if resp is None:
        return jsonify({"error": "Sesión no activa."}), 503
    if not resp.get("ok"):
        return jsonify({"error": resp.get("error")}), 500
    return jsonify({"ok": True}), 200


@app.route("/viewport/<session_id>", methods=["GET"])
def viewport_route(session_id: str):
    resp = _send_cmd_sync(session_id, {"type": "viewport"}, timeout=8.0)
    if resp is None or not resp.get("ok"):
        return jsonify({"width": 1280, "height": 800}), 200
    return jsonify(resp["data"]), 200


if __name__ == "__main__":
    _LOADING_PNG = _make_loading_png()
    app.run(host="0.0.0.0", port=5757, debug=False, threaded=True)
