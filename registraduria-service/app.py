"""
Registraduria polling-place lookup microservice — 2captcha edition.

Flow per lookup:
  1. Playwright (headless=False + Xvfb on VPS) loads the page
  2. Cedula is filled automatically
  3. reCAPTCHA site key is extracted from the page
  4. 2captcha solves the token on their servers (~5-15 s)
  5. Token is injected and form is submitted
  6. Result is parsed and returned
  7. Browser closes immediately

No screenshot/click proxy needed — all automated.
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
# 2captcha solver (async)
# ---------------------------------------------------------------------------

async def solve_recaptcha(site_key: str, page_url: str) -> str:
    """Submit reCAPTCHA to 2captcha and poll until solved. Returns g-recaptcha-response token."""
    connector = aiohttp.TCPConnector(ssl=False)
    async with aiohttp.ClientSession(connector=connector) as http:

        # Submit job
        resp = await http.post("https://2captcha.com/in.php", data={
            "key": TWO_CAPTCHA_KEY,
            "method": "userrecaptcha",
            "googlekey": site_key,
            "pageurl": page_url,
            "json": "1",
        })
        payload = await resp.json(content_type=None)
        if str(payload.get("status")) != "1":
            raise RuntimeError(f"2captcha submit failed: {payload}")

        captcha_id = payload["request"]

        # Poll — typical solve time 5-20 s
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
                raise RuntimeError(f"2captcha error: {payload}")

    raise TimeoutError("2captcha did not solve within 2 minutes")


# ---------------------------------------------------------------------------
# Playwright lookup (async, runs in its own event loop per thread)
# ---------------------------------------------------------------------------

async def _async_lookup(session_id: str, cedula: str) -> None:
    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=False,          # Gov site blocks pure headless at TCP level
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
            # 1 — Navigate
            _set(session_id, status="loading")
            await page.goto(REGISTRADURIA_URL, wait_until="domcontentloaded", timeout=30_000)

            # 2 — Fill cedula
            for selector in ['input[name="nuip"]', 'input[type="number"]', "input#nuip"]:
                try:
                    await page.fill(selector, cedula, timeout=3_000)
                    break
                except Exception:
                    continue

            # 3 — Wait briefly for reCAPTCHA widget to render, then extract site key
            await asyncio.sleep(3)

            site_key = await page.evaluate("""() => {
                // Method 1: data-sitekey attribute on any element
                const el = document.querySelector('[data-sitekey]');
                if (el) return el.getAttribute('data-sitekey');

                // Method 2: reCAPTCHA iframe src
                for (const iframe of document.querySelectorAll('iframe[src*="recaptcha"]')) {
                    const m = iframe.src.match(/[?&]k=([A-Za-z0-9_-]{20,})/);
                    if (m) return m[1];
                }

                // Method 3: grecaptcha JS config
                try {
                    const cfg = window.___grecaptcha_cfg;
                    if (cfg && cfg.clients) {
                        for (const client of Object.values(cfg.clients)) {
                            for (const obj of Object.values(client)) {
                                if (obj && obj.sitekey) return obj.sitekey;
                            }
                        }
                    }
                } catch (_) {}

                return null;
            }""")

            if not site_key:
                # Last fallback: page source regex
                content = await page.content()
                m = re.search(r'data-sitekey=["\']([A-Za-z0-9_-]{20,})["\']', content)
                if m:
                    site_key = m.group(1)

            if not site_key:
                # Scan frame URLs
                for frame in page.frames:
                    m = re.search(r"[?&]k=([A-Za-z0-9_-]{30,})", frame.url)
                    if m:
                        site_key = m.group(1)
                        break
            if not site_key:
                raise RuntimeError("No se encontró el data-sitekey del reCAPTCHA en la página")

            # 4 — Solve via 2captcha
            _set(session_id, status="solving_captcha")
            token = await solve_recaptcha(site_key, REGISTRADURIA_URL)

            # 5 — Inject token and trigger callback
            await page.evaluate(f"""() => {{
                const el = document.getElementById('g-recaptcha-response');
                if (el) el.innerHTML = '{token}';

                // Trigger the reCAPTCHA callback so the button enables
                try {{
                    const cfg = window.___grecaptcha_cfg;
                    if (cfg && cfg.clients) {{
                        Object.values(cfg.clients).forEach(client => {{
                            Object.values(client).forEach(obj => {{
                                if (obj && typeof obj.callback === 'function') {{
                                    obj.callback('{token}');
                                }}
                            }});
                        }});
                    }}
                }} catch (_) {{}}
            }}""")

            await asyncio.sleep(0.8)

            # 6 — Click Consultar (may be enabled now) or submit form directly
            try:
                btn = page.locator("button:has-text('Consultar'), input[type=submit]")
                await btn.first.click(timeout=5_000)
            except Exception:
                # Last resort: JS form submit
                await page.evaluate("document.querySelector('form') && document.querySelector('form').submit()")

            # 7 — Wait for result page
            _set(session_id, status="waiting_result")
            deadline = time.time() + 30
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
                raise TimeoutError("El resultado de la Registraduría no apareció en 30 s")

            await asyncio.sleep(0.5)
            body = await page.inner_text("body")
            data = _parse_result(body)
            _set(session_id, status="done", data=data)

        except Exception as exc:
            # Strip newlines so the error message is valid in JSON strings
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
# Result parser (interleaved label → value format)
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

    # Colon fallback
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
