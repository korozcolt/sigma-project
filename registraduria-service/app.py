"""
Registraduria polling-place lookup microservice -- feasibility spike (Phase 9, LIVE-02).

Spike target: wsp.registraduria.gov.co (classic reCAPTCHA v2 checkbox widget,
possibly Enterprise-registered sitekey on Google's backend -- see 09-RESEARCH.md).

Architecture:
  1. Playwright opens ONE browser context for the whole attempt.
  2. Load https://wsp.registraduria.gov.co/censo/consultar/, extract the live
     data-sitekey and the per-load anti-replay #token nonce (both rotate --
     never hardcode).
  3. 2captcha solves the checkbox (method=userrecaptcha; enterprise=1 added only
     when the caller sets "enterprise": true in the /lookup request body, per the
     escalation strategy in 09-RESEARCH.md -- there is no numeric "score" param
     for a checkbox-type solve).
  4. Inject the solved token into #g-recaptcha-response, fill #nuip / #tipo,
     click #enviar -- same page/context so cookies/session stay valid.
  5. Classify the AJAX response into: success / denied_by_score / not_found /
     session_expired / source_unreachable (never treat a token as a success).

Key findings (Phase 9 research, 2026-07-25):
  - Sitekey/anti-replay token must be extracted live, per attempt -- both rotate.
  - Submission is AJAX POST to the SAME page URL, multipart/form-data.
  - A WAF (F5 BIG-IP ASM) returns 200 OK with an HTML block page for
    non-browser-shaped traffic -- never hand-roll raw HTTP against wsp.

This process also coexists with the restored pre-Phase-9 infovotantes flow:
wsp is served at POST /lookup, infovotantes at POST /lookup/infovotantes,
sharing the same Flask process, sessions dict, and GET /result/<session_id>.
"""

import asyncio
import json as _json
import os
import threading
import uuid

import aiohttp
from flask import Flask, jsonify, request
from playwright.async_api import async_playwright

app = Flask(__name__)


def load_env():
    for path in ('../.env', '.env'):
        if os.path.exists(path):
            try:
                with open(path, 'r') as f:
                    for line in f:
                        line = line.strip()
                        if line and not line.startswith('#') and '=' in line:
                            k, v = line.split('=', 1)
                            k = k.strip()
                            v = v.strip().strip('"').strip("'")
                            os.environ[k] = v
            except Exception:
                pass


load_env()
TWO_CAPTCHA_KEY = os.environ["TWO_CAPTCHA_KEY"]
WSP_PAGE_URL = "https://wsp.registraduria.gov.co/censo/consultar/"

INFOVOTANTES_SITEKEY = "6Lc9DmgrAAAAAJAjWVhjDy1KSgqzqJikY5z7I9SV"
INFOVOTANTES_PAGE_URL = "https://eleccionescolombia.registraduria.gov.co/identificacion"
INFOVOTANTES_API = "https://apiweb-eleccionescolombia.infovotantes.com/api/v1/citizen/get-information"

sessions: dict = {}
sessions_lock = threading.Lock()


async def _lookup_async(session_id: str, cedula: str, enterprise: bool) -> None:
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
            await page.goto(WSP_PAGE_URL, timeout=30_000)
            sitekey = await page.get_attribute(".g-recaptcha", "data-sitekey")
            session_nonce = await page.get_attribute("#token", "value")
            if not sitekey or not session_nonce:
                _set(session_id, status="error", outcome="source_unreachable",
                     error=f"sitekey or #token missing from DOM (sitekey={sitekey!r}, token={session_nonce!r})")
                return

            _set(session_id, status="solving_captcha", sitekey=sitekey)

            connector = aiohttp.TCPConnector(ssl=False)
            async with aiohttp.ClientSession(connector=connector) as http:
                submit_payload = {
                    "key": TWO_CAPTCHA_KEY,
                    "method": "userrecaptcha",
                    "googlekey": sitekey,
                    "pageurl": WSP_PAGE_URL,
                    "invisible": "0",
                    "json": "1",
                }
                if enterprise:
                    submit_payload.update({"enterprise": "1"})

                resp = await http.post("https://2captcha.com/in.php", data=submit_payload)
                payload = await resp.json(content_type=None)
                if str(payload.get("status")) != "1":
                    _set(session_id, status="error", outcome="source_unreachable",
                         error=f"2captcha submit failed (check balance/key): {payload}")
                    return

                captcha_id = payload["request"]
                token = None
                for _ in range(30):
                    await asyncio.sleep(5)
                    poll = await http.get(
                        "https://2captcha.com/res.php",
                        params={"key": TWO_CAPTCHA_KEY, "action": "get",
                                "id": captcha_id, "json": "1"},
                    )
                    poll_payload = await poll.json(content_type=None)
                    if str(poll_payload.get("status")) == "1":
                        token = poll_payload["request"]
                        break
                    if poll_payload.get("request") not in ("CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"):
                        _set(session_id, status="error", outcome="source_unreachable",
                             error=f"2captcha poll error: {poll_payload}")
                        return

            if not token:
                _set(session_id, status="error", outcome="source_unreachable",
                     error="2captcha did not resolve within 150s")
                return

            _set(session_id, status="waiting_result")

            await page.evaluate(
                """(token) => {
                    const el = document.getElementById('g-recaptcha-response');
                    if (el) { el.value = token; el.innerHTML = token; }
                }""",
                token,
            )
            await page.fill("#nuip", cedula)
            await page.select_option("#tipo", "-1")

            try:
                async with page.expect_response(
                    lambda r: r.url == WSP_PAGE_URL and r.request.method == "POST",
                    timeout=20_000,
                ) as resp_info:
                    await page.click("#enviar")
                response = await resp_info.value
            except Exception as exc:
                _set(session_id, status="error", outcome="source_unreachable",
                     error=f"no POST response captured: {exc}")
                return

            raw_body = await response.text()
            try:
                result = _json.loads(raw_body)
            except Exception:
                _set(session_id, status="done", outcome="source_unreachable",
                     raw_response=raw_body[:500],
                     error="non-JSON response (likely WAF block page)")
                return

            if result.get("success"):
                pp_html = result.get("data", {}).get("message", "")
                _set(session_id, status="done", outcome="success",
                     data={"raw_message_html": pp_html}, raw_response=result)
            elif result.get("reload"):
                _set(session_id, status="done", outcome="session_expired", raw_response=result)
            else:
                message = result.get("data", {}).get("message", "")
                lowered = message.lower()
                if "no existe" in lowered or "no registra" in lowered or "no se encontr" in lowered:
                    outcome = "not_found"
                else:
                    outcome = "denied_by_score"
                _set(session_id, status="done", outcome=outcome,
                     raw_response=result, message=message)

        finally:
            await browser.close()


def _set(session_id: str, **kwargs) -> None:
    with sessions_lock:
        sessions[session_id].update(kwargs)


def _run(session_id: str, cedula: str, enterprise: bool) -> None:
    try:
        asyncio.run(_lookup_async(session_id, cedula, enterprise))
    except Exception as exc:
        _set(session_id, status="error", outcome="source_unreachable", error=str(exc).split("\n")[0])


async def _lookup_infovotantes_async(session_id: str, cedula: str) -> None:
    """Restored pre-Phase-9 flow (git commit ac1dd5a): 2captcha solves the
    eleccionescolombia sitekey, then a headless Playwright browser context
    calls the infovotantes structured JSON API directly (required -- the API
    only accepts browser-shaped fetch() requests, not raw aiohttp calls).
    """
    connector = aiohttp.TCPConnector(ssl=False)
    async with aiohttp.ClientSession(connector=connector) as http:
        resp = await http.post("https://2captcha.com/in.php", data={
            "key": TWO_CAPTCHA_KEY,
            "method": "userrecaptcha",
            "googlekey": INFOVOTANTES_SITEKEY,
            "pageurl": INFOVOTANTES_PAGE_URL,
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
            # Use Playwright's built-in request API -- bypasses CORS, no page load needed
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
        "puesto_nombre": f"{pp.get('stand_code', '')} - {pp.get('stand', '')}".strip(" -"),
        "puesto_codigo": pp.get("stand_code", ""),
        "zona_codigo": addr.get("zone", ""),
        "mesa_numero": str(pp.get("table", "")),
        "departamento": addr.get("state", ""),
        "municipio": addr.get("town", ""),
        "direccion": addr.get("address", ""),
    }

    _set(session_id, status="done", data=data)


def _run_infovotantes(session_id: str, cedula: str) -> None:
    try:
        asyncio.run(_lookup_infovotantes_async(session_id, cedula))
    except Exception as exc:
        _set(session_id, status="error", error=str(exc).split("\n")[0])


@app.route("/lookup", methods=["POST"])
def lookup():
    body = request.get_json(silent=True) or {}
    cedula = str(body.get("cedula", "")).strip()
    enterprise = bool(body.get("enterprise", False))
    if not cedula:
        return jsonify({"error": "El campo 'cedula' es requerido."}), 400

    session_id = str(uuid.uuid4())
    with sessions_lock:
        sessions[session_id] = {
            "status": "pending", "outcome": None, "data": None,
            "error": None, "sitekey": None, "message": None, "raw_response": None,
        }

    threading.Thread(target=_run, args=(session_id, cedula, enterprise), daemon=True).start()
    return jsonify({"session_id": session_id}), 200


@app.route("/lookup/infovotantes", methods=["POST"])
def lookup_infovotantes():
    body = request.get_json(silent=True) or {}
    cedula = str(body.get("cedula", "")).strip()
    if not cedula:
        return jsonify({"error": "El campo 'cedula' es requerido."}), 400

    session_id = str(uuid.uuid4())
    with sessions_lock:
        sessions[session_id] = {"status": "pending", "data": None, "error": None}

    threading.Thread(target=_run_infovotantes, args=(session_id, cedula), daemon=True).start()
    return jsonify({"session_id": session_id}), 200


@app.route("/result/<session_id>", methods=["GET"])
def result(session_id: str):
    with sessions_lock:
        session = sessions.get(session_id)
    if session is None:
        return jsonify({"error": f"Sesion '{session_id}' no encontrada."}), 404
    return jsonify(session), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5757, debug=False, threaded=True)
