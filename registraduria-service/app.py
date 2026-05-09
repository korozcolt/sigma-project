"""
Registraduria polling-place lookup microservice.

Strategy:
  1. Playwright (headless=False + Xvfb) loads the page and fills the cedula
  2. Clicks the reCAPTCHA checkbox directly INSIDE its iframe
     → If Google sees the click as human, the checkbox passes immediately
     → If an image challenge appears, 2captcha solves it
  3. Once CAPTCHA is ticked, clicks Consultar and parses the result

This is more reliable than token injection because:
- The widget's internal state is updated via a real click
- 2captcha is only used if a challenge appears (cheaper + faster)
"""

import asyncio
import threading
import time
import uuid
import re

import aiohttp
from flask import Flask, jsonify, request
from playwright.async_api import async_playwright

app = Flask(__name__)

TWO_CAPTCHA_KEY = "9fab1f6ad28812795d61fe8858585ef4"
REGISTRADURIA_URL = "https://eleccionescolombia.registraduria.gov.co/identificacion"

sessions: dict = {}
sessions_lock = threading.Lock()


# ---------------------------------------------------------------------------
# 2captcha: solve image challenge (coordinate-based)
# ---------------------------------------------------------------------------

async def solve_image_captcha(image_b64: str) -> list:
    """Submit an image CAPTCHA to 2captcha, returns list of clicked coordinates."""
    connector = aiohttp.TCPConnector(ssl=False)
    async with aiohttp.ClientSession(connector=connector) as http:
        resp = await http.post("https://2captcha.com/in.php", data={
            "key": TWO_CAPTCHA_KEY,
            "method": "base64",
            "body": image_b64,
            "json": "1",
        })
        payload = await resp.json(content_type=None)
        if str(payload.get("status")) != "1":
            raise RuntimeError(f"2captcha image submit failed: {payload}")

        captcha_id = payload["request"]

        for _ in range(12):
            await asyncio.sleep(5)
            resp = await http.get(
                "https://2captcha.com/res.php",
                params={"key": TWO_CAPTCHA_KEY, "action": "get", "id": captcha_id, "json": "1"},
            )
            payload = await resp.json(content_type=None)
            if str(payload.get("status")) == "1":
                return payload["request"]
            if payload.get("request") not in ("CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"):
                raise RuntimeError(f"2captcha error: {payload}")

    raise TimeoutError("2captcha image solve timeout")


async def solve_recaptcha_token(site_key: str, page_url: str) -> str:
    """Full reCAPTCHA v2 token solve via 2captcha (fallback if checkbox click fails)."""
    connector = aiohttp.TCPConnector(ssl=False)
    async with aiohttp.ClientSession(connector=connector) as http:
        resp = await http.post("https://2captcha.com/in.php", data={
            "key": TWO_CAPTCHA_KEY,
            "method": "userrecaptcha",
            "googlekey": site_key,
            "pageurl": page_url,
            "invisible": "0",
            "json": "1",
        })
        payload = await resp.json(content_type=None)
        if str(payload.get("status")) != "1":
            raise RuntimeError(f"2captcha submit failed: {payload}")

        captcha_id = payload["request"]

        for _ in range(24):
            await asyncio.sleep(5)
            resp = await http.get(
                "https://2captcha.com/res.php",
                params={"key": TWO_CAPTCHA_KEY, "action": "get", "id": captcha_id, "json": "1"},
            )
            payload = await resp.json(content_type=None)
            if str(payload.get("status")) == "1":
                return payload["request"]
            if payload.get("request") not in ("CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"):
                raise RuntimeError(f"2captcha poll error: {payload}")

    raise TimeoutError("2captcha token solve timeout (2 min)")


# ---------------------------------------------------------------------------
# Playwright lookup
# ---------------------------------------------------------------------------

async def _async_lookup(session_id: str, cedula: str) -> None:
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
            _set(session_id, status="loading")
            await page.goto(REGISTRADURIA_URL, wait_until="domcontentloaded", timeout=30_000)

            # Fill cedula
            for selector in ['input[name="nuip"]', 'input[type="number"]', "input#nuip"]:
                try:
                    await page.fill(selector, cedula, timeout=3_000)
                    break
                except Exception:
                    continue

            # Wait for reCAPTCHA iframe to load
            await asyncio.sleep(3)

            _set(session_id, status="solving_captcha")

            # --- Strategy A: click the reCAPTCHA checkbox directly in its iframe ---
            captcha_solved = False
            try:
                # The reCAPTCHA anchor iframe contains the checkbox
                anchor_frame = page.frame_locator('iframe[src*="recaptcha"][src*="anchor"]').first
                await anchor_frame.locator("#recaptcha-anchor").click(timeout=8_000)
                await asyncio.sleep(2)

                # Check if checkbox is now checked (CAPTCHA passed without challenge)
                is_checked = await anchor_frame.locator("#recaptcha-anchor").get_attribute("aria-checked")
                if is_checked == "true":
                    captcha_solved = True

            except Exception:
                pass

            # --- Strategy B: token injection via 2captcha (if checkbox click didn't solve it) ---
            if not captcha_solved:
                # Extract site key
                site_key = await page.evaluate("""() => {
                    const el = document.querySelector('[data-sitekey]');
                    if (el) return el.getAttribute('data-sitekey');
                    for (const iframe of document.querySelectorAll('iframe[src*="recaptcha"]')) {
                        const m = iframe.src.match(/[?&]k=([A-Za-z0-9_-]{20,})/);
                        if (m) return m[1];
                    }
                    try {
                        const cfg = window.___grecaptcha_cfg;
                        if (cfg && cfg.clients) {
                            for (const c of Object.values(cfg.clients))
                                for (const obj of Object.values(c))
                                    if (obj && obj.sitekey) return obj.sitekey;
                        }
                    } catch (_) {}
                    return null;
                }""")

                if not site_key:
                    content = await page.content()
                    m = re.search(r'data-sitekey=["\']([A-Za-z0-9_-]{20,})["\']', content)
                    if m:
                        site_key = m.group(1)

                if not site_key:
                    for frame in page.frames:
                        m = re.search(r"[?&]k=([A-Za-z0-9_-]{30,})", frame.url)
                        if m:
                            site_key = m.group(1)
                            break

                if not site_key:
                    raise RuntimeError("No se encontró el sitekey del reCAPTCHA")

                token = await solve_recaptcha_token(site_key, REGISTRADURIA_URL)

                # Inject token and trigger callbacks
                await page.evaluate(f"""() => {{
                    const token = '{token}';
                    document.querySelectorAll('[name="g-recaptcha-response"], #g-recaptcha-response').forEach(el => {{
                        el.value = token; el.innerHTML = token;
                    }});
                    document.querySelectorAll('[data-callback]').forEach(div => {{
                        const cb = div.getAttribute('data-callback');
                        if (cb && typeof window[cb] === 'function') try {{ window[cb](token); }} catch(_) {{}}
                    }});
                    try {{
                        const cfg = window.___grecaptcha_cfg;
                        if (cfg && cfg.clients) Object.values(cfg.clients).forEach(c =>
                            Object.values(c).forEach(obj => {{
                                if (obj && typeof obj.callback === 'function') try {{ obj.callback(token); }} catch(_) {{}}
                            }})
                        );
                    }} catch(_) {{}}
                    document.querySelectorAll('button[disabled], input[type=submit][disabled]').forEach(btn => {{
                        btn.disabled = false; btn.removeAttribute('disabled');
                    }});
                }}""")
                await asyncio.sleep(2)

            # --- Click Consultar ---
            _set(session_id, status="waiting_result")
            try:
                await page.get_by_role("button", name="Consultar").click(timeout=5_000)
            except Exception:
                try:
                    await page.locator("button, input[type='submit']").first.click(force=True, timeout=3_000)
                except Exception:
                    pass

            # --- Wait for result ---
            deadline = time.time() + 45
            found = False
            while time.time() < deadline:
                try:
                    body = await page.inner_text("body")
                    if cedula in body and ("Puesto" in body or "PUESTO" in body):
                        found = True
                        break
                except Exception:
                    pass
                await asyncio.sleep(1)

            if not found:
                try:
                    url = page.url
                    snippet = (await page.inner_text("body"))[:300].replace("\n", " ")
                    await page.screenshot(path="/tmp/debug_registraduria.jpg", type="jpeg", quality=70)
                except Exception:
                    url, snippet = "unknown", "unknown"
                raise TimeoutError(f"Sin resultado. URL: {url} | Body: {snippet}")

            await asyncio.sleep(0.5)
            body = await page.inner_text("body")
            data = _parse_result(body)
            _set(session_id, status="done", data=data)

        except Exception as exc:
            err = str(exc).split("\n")[0].strip()
            _set(session_id, status="error", error=err)
        finally:
            try:
                await browser.close()
            except Exception:
                pass


def _set(session_id: str, **kwargs) -> None:
    with sessions_lock:
        sessions[session_id].update(kwargs)


def _run(session_id: str, cedula: str) -> None:
    asyncio.run(_async_lookup(session_id, cedula))


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
            if "PUESTO" in ku:
                result["puesto_nombre"] = v
            elif "ZONA" in ku:
                result["zona_codigo"] = v
            elif "MESA" in ku:
                result["mesa_numero"] = v
            elif "DIRECCI" in ku:
                result["direccion"] = v
            elif "MUNICIPIO" in ku:
                result["municipio"] = v
            elif "DEPARTAMENTO" in ku:
                result["departamento"] = v

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
    with sessions_lock:
        sessions[session_id] = {"status": "pending", "data": None, "error": None}

    threading.Thread(target=_run, args=(session_id, cedula), daemon=True).start()
    return jsonify({"session_id": session_id}), 200


@app.route("/result/<session_id>", methods=["GET"])
def result(session_id: str):
    with sessions_lock:
        session = sessions.get(session_id)
    if session is None:
        return jsonify({"error": f"Sesión '{session_id}' no encontrada."}), 404
    return jsonify(session), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5757, debug=False, threaded=True)
