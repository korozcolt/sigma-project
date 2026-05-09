"""
Registraduria polling-place lookup microservice — screenshot proxy edition.

The reCAPTCHA must be solved by a HUMAN in the same browser session.
2captcha tokens don't work because Google verifies session continuity.

Flow:
  1. Playwright (headless=False + Xvfb) loads the page, fills cedula
  2. Operator sees live screenshots in SIGMA modal and clicks the reCAPTCHA area
  3. Click is forwarded: if it hits the reCAPTCHA iframe → clicks #recaptcha-anchor
     properly inside the frame (Google registers a real-session human click)
  4. If Google shows image challenge → operator clicks tiles via screenshot
  5. Once CAPTCHA is ticked, operator clicks Consultar → result parsed

Screenshot poll: every 1.5 s, JPEG q50 (lightweight).
Thread safety: all Playwright calls go through asyncio queues back to the owner thread.
"""

import asyncio
import queue
import threading
import time
import uuid

from flask import Flask, Response, jsonify, request
from playwright.async_api import async_playwright

app = Flask(__name__)

REGISTRADURIA_URL = "https://eleccionescolombia.registraduria.gov.co/identificacion"

sessions: dict = {}
sessions_lock = threading.Lock()

# Per-session command bridges (sync→async via threading.Queue)
session_bridges: dict = {}
session_bridges_lock = threading.Lock()

_LOADING_PNG: bytes = (
    b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00"
    b"\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx"
    b"\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82"
)


# ---------------------------------------------------------------------------
# Sync→async bridge
# ---------------------------------------------------------------------------

def _send_cmd(session_id: str, cmd: dict, timeout: float = 8.0):
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
# Playwright async lookup
# ---------------------------------------------------------------------------

async def _async_lookup(session_id: str, cedula: str, bridge: queue.Queue) -> None:

    async def process_bridge(page) -> None:
        """Drain pending commands from the sync bridge."""
        while True:
            try:
                cmd = bridge.get_nowait()
            except queue.Empty:
                break
            resp_q = cmd.pop("_resp_q", None)
            t = cmd.get("type")
            try:
                if t == "screenshot":
                    img = await page.screenshot(type="jpeg", quality=50)
                    if resp_q:
                        resp_q.put({"ok": True, "data": img})

                elif t == "click":
                    x, y = cmd["x"], cmd["y"]
                    # Check if click is inside any reCAPTCHA iframe
                    clicked_in_frame = False
                    for frame in page.frames:
                        if "recaptcha" not in frame.url:
                            continue
                        try:
                            frame_el = await frame.frame_element()
                            box = await frame_el.bounding_box()
                            if box and box["x"] <= x <= box["x"] + box["width"] \
                               and box["y"] <= y <= box["y"] + box["height"]:
                                # Click the reCAPTCHA anchor/checkbox inside the frame
                                if "anchor" in frame.url:
                                    try:
                                        await frame.locator("#recaptcha-anchor").click(timeout=3_000)
                                        clicked_in_frame = True
                                    except Exception:
                                        pass
                                if not clicked_in_frame:
                                    fx = x - box["x"]
                                    fy = y - box["y"]
                                    await frame.evaluate(
                                        f"() => {{ const el = document.elementFromPoint({fx},{fy}); if(el) el.click(); }}"
                                    )
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
                    resp_q.put({"ok": False, "error": str(exc).split("\n")[0]})

    async with async_playwright() as p:
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
            # Navigate concurrently with screenshot serving
            goto_task = asyncio.create_task(
                page.goto(REGISTRADURIA_URL, wait_until="domcontentloaded", timeout=30_000)
            )
            while not goto_task.done():
                await process_bridge(page)
                await asyncio.sleep(0.1)
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

            # Monitor for result while serving screenshot/click commands
            deadline = time.time() + 180
            found = False
            while time.time() < deadline:
                await process_bridge(page)
                try:
                    body = await page.inner_text("body")
                    if cedula in body and ("Puesto" in body or "PUESTO" in body):
                        found = True
                        break
                except Exception:
                    pass
                await asyncio.sleep(0.15)

            if not found:
                raise TimeoutError("Tiempo agotado esperando el resultado (180 s)")

            await asyncio.sleep(1)
            body = await page.inner_text("body")
            data = _parse_result(body)

            with sessions_lock:
                sessions[session_id]["status"] = "done"
                sessions[session_id]["data"] = data

            # Keep serving screenshots for a few seconds so operator sees result
            deadline_final = time.time() + 4
            while time.time() < deadline_final:
                await process_bridge(page)
                await asyncio.sleep(0.1)

        except Exception as exc:
            err = str(exc).split("\n")[0].strip()
            with sessions_lock:
                sessions[session_id]["status"] = "error"
                sessions[session_id]["error"] = err
        finally:
            with session_bridges_lock:
                session_bridges.pop(session_id, None)
            try:
                await browser.close()
            except Exception:
                pass


def _run(session_id: str, cedula: str, bridge: queue.Queue) -> None:
    asyncio.run(_async_lookup(session_id, cedula, bridge))


# ---------------------------------------------------------------------------
# Result parser
# ---------------------------------------------------------------------------

def _parse_result(text: str) -> dict:
    result = {k: "" for k in ("puesto_nombre", "puesto_codigo", "zona_codigo",
                               "mesa_numero", "departamento", "municipio", "direccion")}
    lines = [ln.strip() for ln in text.splitlines() if ln.strip()]

    def nxt(lst, i):
        for j in range(i + 1, min(i + 4, len(lst))):
            if lst[j].strip():
                return lst[j].strip()
        return ""

    for i, line in enumerate(lines):
        n = line.upper().strip()
        if n == "PUESTO" and not result["puesto_nombre"]:
            result["puesto_nombre"] = nxt(lines, i)
        elif n == "MESA" and not result["mesa_numero"]:
            result["mesa_numero"] = nxt(lines, i)
        elif n == "ZONA" and not result["zona_codigo"]:
            result["zona_codigo"] = nxt(lines, i)
        elif n == "DEPARTAMENTO" and not result["departamento"]:
            result["departamento"] = nxt(lines, i)
        elif n == "MUNICIPIO" and not result["municipio"]:
            result["municipio"] = nxt(lines, i)
        elif n in ("DIRECCIÓN", "DIRECCION") and not result["direccion"]:
            result["direccion"] = nxt(lines, i)

    if not result["puesto_nombre"]:
        for line in lines:
            if ":" not in line:
                continue
            k, _, v = line.partition(":")
            v = v.strip()
            ku = k.upper().strip()
            if "PUESTO" in ku: result["puesto_nombre"] = v
            elif "ZONA" in ku: result["zona_codigo"] = v
            elif "MESA" in ku: result["mesa_numero"] = v
            elif "DIRECCI" in ku: result["direccion"] = v
            elif "MUNICIPIO" in ku: result["municipio"] = v
            elif "DEPARTAMENTO" in ku: result["departamento"] = v

    nombre = result["puesto_nombre"]
    if nombre:
        code = nombre.split("-", 1)[0].strip()
        result["puesto_codigo"] = code if code.isdigit() else ""
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

    threading.Thread(target=_run, args=(session_id, cedula, bridge), daemon=True).start()
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
    resp = _send_cmd(session_id, {"type": "screenshot"}, timeout=6.0)
    if resp is None or not resp.get("ok"):
        return Response(_LOADING_PNG, mimetype="image/png",
                        headers={"Cache-Control": "no-store"})
    return Response(resp["data"], mimetype="image/jpeg",
                    headers={"Cache-Control": "no-store, no-cache"})


@app.route("/click/<session_id>", methods=["POST"])
def click_route(session_id: str):
    body = request.get_json(silent=True) or {}
    try:
        x, y = int(body["x"]), int(body["y"])
    except (KeyError, ValueError, TypeError):
        return jsonify({"error": "x e y requeridos"}), 400
    resp = _send_cmd(session_id, {"type": "click", "x": x, "y": y}, timeout=8.0)
    if resp is None:
        return jsonify({"error": "sesión no activa"}), 503
    return jsonify({"ok": resp.get("ok", False)}), 200


@app.route("/viewport/<session_id>", methods=["GET"])
def viewport_route(session_id: str):
    resp = _send_cmd(session_id, {"type": "viewport"}, timeout=6.0)
    if resp is None or not resp.get("ok"):
        return jsonify({"width": 1280, "height": 800}), 200
    return jsonify(resp["data"]), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5757, debug=False, threaded=True)
