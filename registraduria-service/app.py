"""
Registraduria polling-place lookup microservice — fully automated.

Architecture:
  1. 2captcha solves the reCAPTCHA token (~60-90 s)
  2. Playwright opens a headless browser, navigates to the Registraduria page,
     then calls the infovotantes JSON API via browser fetch() using the token
     as Authorization: Bearer header
  3. Result is parsed and returned

The API requires calls from a browser context (Sec-Fetch headers, HTTP/2).
Python aiohttp is blocked; browser fetch() works.

Key findings:
  - Sitekey: 6Lc9DmgrAAAAAJAjWVhjDy1KSgqzqJikY5z7I9SV
  - API: https://apiweb-eleccionescolombia.infovotantes.com/api/v1/citizen/get-information
  - Auth: Authorization: Bearer {recaptcha_token}
  - No Xvfb needed — headless=True works for aiohttp+Playwright here
"""

import asyncio
import threading
import uuid

import aiohttp
from flask import Flask, jsonify, request
from playwright.async_api import async_playwright

app = Flask(__name__)

TWO_CAPTCHA_KEY = "9fab1f6ad28812795d61fe8858585ef4"
REGISTRADURIA_SITEKEY = "6Lc9DmgrAAAAAJAjWVhjDy1KSgqzqJikY5z7I9SV"
REGISTRADURIA_PAGE_URL = "https://eleccionescolombia.registraduria.gov.co/identificacion"
INFOVOTANTES_API = "https://apiweb-eleccionescolombia.infovotantes.com/api/v1/citizen/get-information"

sessions: dict = {}
sessions_lock = threading.Lock()


async def _lookup_async(session_id: str, cedula: str) -> None:
    # Step 1 — solve reCAPTCHA via 2captcha
    connector = aiohttp.TCPConnector(ssl=False)
    async with aiohttp.ClientSession(connector=connector) as http:
        resp = await http.post("https://2captcha.com/in.php", data={
            "key": TWO_CAPTCHA_KEY,
            "method": "userrecaptcha",
            "googlekey": REGISTRADURIA_SITEKEY,
            "pageurl": REGISTRADURIA_PAGE_URL,
            "invisible": "0",
            "json": "1",
        })
        payload = await resp.json(content_type=None)
        if str(payload.get("status")) != "1":
            raise RuntimeError(f"2captcha submit failed: {payload}")

        captcha_id = payload["request"]
        _set(session_id, status="solving_captcha")

        token = None
        for _ in range(30):
            await asyncio.sleep(5)
            poll = await http.get("https://2captcha.com/res.php",
                params={"key": TWO_CAPTCHA_KEY, "action": "get",
                        "id": captcha_id, "json": "1"})
            p = await poll.json(content_type=None)
            if str(p.get("status")) == "1":
                token = p["request"]
                break
            if p.get("request") not in ("CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"):
                raise RuntimeError(f"2captcha poll error: {p}")

    if not token:
        raise RuntimeError("2captcha no resolvió en 150 s")

    # Step 2 — call infovotantes API via browser fetch() (required by API)
    _set(session_id, status="waiting_result")

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--ignore-certificate-errors"],
        )
        ctx = await browser.new_context(
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
            ignore_https_errors=True,
        )
        page = await ctx.new_page()

        try:
            import json as _json
            # Use Playwright's built-in request API — bypasses CORS, no page load needed
            api_response = await page.request.fetch(
                INFOVOTANTES_API,
                method="POST",
                headers={
                    "Authorization": f"Bearer {token}",
                    "Content-Type": "application/json",
                    "Origin": "https://eleccionescolombia.registraduria.gov.co",
                    "Referer": "https://eleccionescolombia.registraduria.gov.co/identificacion",
                    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
                    "Accept": "*/*",
                    "Sec-Fetch-Site": "cross-site",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Dest": "empty",
                },
                data=_json.dumps({
                    "identification": cedula,
                    "identification_type": "CC",
                    "election_code": "presidencia",
                    "platform": "web",
                    "module": "polling_place",
                }),
                timeout=20_000,
            )
            result = await api_response.json()

        finally:
            await browser.close()

    if not result or not result.get("status"):
        raise RuntimeError(f"API error: {result}")

    pp = result.get("data", {}).get("polling_place", {})
    addr = pp.get("place_address", {})

    data = {
        "puesto_nombre": f"{pp.get('stand_code','')} - {pp.get('stand','')}".strip(" -"),
        "puesto_codigo": pp.get("stand_code", ""),
        "zona_codigo": addr.get("zone", ""),
        "mesa_numero": str(pp.get("table", "")),
        "departamento": addr.get("state", ""),
        "municipio": addr.get("town", ""),
        "direccion": addr.get("address", ""),
    }

    _set(session_id, status="done", data=data)


def _set(session_id: str, **kwargs) -> None:
    with sessions_lock:
        sessions[session_id].update(kwargs)


def _run(session_id: str, cedula: str) -> None:
    try:
        asyncio.run(_lookup_async(session_id, cedula))
    except Exception as exc:
        _set(session_id, status="error", error=str(exc).split("\n")[0])


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
