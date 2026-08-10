# Phase 9: Live-Source Feasibility Spike - Research

**Researched:** 2026-07-25
**Domain:** reCAPTCHA-gated government site automation (2captcha + Playwright), targeting `wsp.registraduria.gov.co`
**Confidence:** HIGH on the page contract and submission mechanics (verified directly against the live site's current HTML/JS — not training-data assumption). MEDIUM-LOW on whether a solved token will actually be *accepted* by the backend (the one thing that genuinely cannot be known before spending real 2captcha budget).

## Summary

This research pass fetched `wsp.registraduria.gov.co/censo/consultar/` directly (the correct URL — note the trailing path is `consultar`, not `consulta` as CONTEXT.md's canonical-refs paraphrase says) and extracted the live page source, its form-submission JS, and several probe responses. This overturns one load-bearing assumption in the prior research (STACK.md/PITFALLS.md): **the page renders a classic reCAPTCHA v2 checkbox via the standard `recaptcha/api.js` script — not `recaptcha/enterprise.js`, and with no `action` or `data-s` parameters anywhere in the DOM.** There is no explicit `grecaptcha.enterprise.render()`/`execute()` call to intercept; the widget auto-renders from a plain `<div class="g-recaptcha" data-sitekey="...">`, and the page's own JS reads the solved token via `grecaptcha.getResponse()` (the classic v2 API), not `grecaptcha.enterprise.getResponse()`.

This does not necessarily mean the backend isn't using reCAPTCHA Enterprise risk-scoring — a sitekey can be registered as "Enterprise" in Google Cloud Console while the front-end integration stays 100% classic-v2-shaped (Enterprise supports this exact backward-compatible mode). The client-side HTML cannot reveal the sitekey's Enterprise-vs-classic registration type — that is invisible from outside Google. So the spike still cannot skip the empirical test, but it now has a fully concrete, already-decoded submission contract to build against, which was previously unknown.

The full request/response contract was extracted directly: cédula field is `nuip` (not a generic "cedula"), there's a **second, server-generated hidden anti-replay `token` field (unrelated to the captcha)** that must be captured fresh per page load and is tied to a `PHPSESSID` cookie, and the actual submit is an **AJAX multipart POST to the same page URL** returning JSON (`{"success": bool, "data": {"message": "..."}}`) — not a whole-page form POST/redirect. A WAF (F5 BIG-IP ASM signature) sits in front of the site and returns an HTML block page instead of JSON when a request looks malformed/scripted — this is a distinct fourth response shape the outcome classifier must detect and not attempt to JSON-parse.

**Primary recommendation:** Drive the whole flow through one Playwright browser context (page load → read sitekey/token from DOM → solve via 2captcha with the *existing* `userrecaptcha` method (no `enterprise=1` on the first attempt, since the DOM shows a plain v2 widget) → inject token into `grecaptcha`'s response slot → submit via the page's own AJAX call (not a hand-crafted raw HTTP POST) → parse the JSON response `success`/`data.message` fields, with an explicit non-JSON/WAF-block branch, to build the `success`/`denied_by_score`/`not_found`/`source_unreachable` taxonomy.

## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Modify `registraduria-service/app.py` directly — throwaway/experimental change, not a new adapter class or isolated script. Change target URL, sitekey, and captcha params directly in the existing Flask service.
- **D-02:** Run the spike from the local dev machine (not the korserver Dokploy container). Faster iteration, no deploy step. Accepted caveat: local IP may score differently than production IP — not a blocker for this spike.
- **D-03:** Three specific real cédulas with known ground-truth polling-place data: `1102812122`, `1102815878`, `64552231`. No need to pause and ask.
- **D-04:** Budget ~20-30 real 2captcha solve attempts across the spike, enough to work through PITFALLS.md's score-escalation ladder (start 0.3, raise toward 0.9 only if >50% denied) across the three cédulas.
- **D-05:** Phase 9 stops at the documented go/no-go recommendation, even on success. Wiring a working adapter into `RegistraduriaService`/production config is explicitly out of scope, regardless of outcome.
- **D-06:** Write a dedicated `SPIKE-RESULTS.md` in the phase directory, capturing: extracted sitekey/action/data-s values, outcome taxonomy breakdown, and the final go/no-go recommendation with rationale.
- **D-07:** Stopping condition is budget exhaustion (D-04), not a fixed wall-clock limit. A mixed/inconclusive result counts as a documented "no-go for now."

### Claude's Discretion
- Exact mechanics of extracting the Enterprise sitekey/action/data-s from `wsp.registraduria.gov.co/censo/consulta/` (manual devtools inspection vs. scripted extraction) — whichever is fastest and reliable for a one-time spike.
- Exact structure/format of `SPIKE-RESULTS.md` beyond the required content (D-06).
- Whether to keep the old dead-domain v2 logic in `app.py` as commented-out reference or rely on git history.

### Deferred Ideas (OUT OF SCOPE)
- Wiring a working wsp adapter into `RegistraduriaService`/production config, flipping `REGISTRADURIA_LIVE_ENABLED` for real — deferred regardless of spike outcome (D-05).
- Running the spike from the korserver Dokploy container to validate under production IP/environment conditions — deferred; local dev machine chosen instead (D-02). Revisit only if local results are ambiguous and IP reputation is suspected as a factor.

## Project Constraints (from CLAUDE.md)

These apply if the spike's throwaway Python code touches anything reviewed under normal repo conventions (it mostly won't, since `app.py` is a standalone Flask/Python microservice, not Laravel/PHP — most Laravel Boost rules below don't literally apply to Python code, but are listed for completeness/awareness):

- No dependency changes without approval (do not add new pip packages like a 2captcha SDK — the existing raw `aiohttp` calls must be extended, not replaced).
- No new base folders without approval (keep the spike inside `registraduria-service/`).
- Only create documentation files if explicitly requested — `SPIKE-RESULTS.md` is explicitly requested by D-06, so it's an approved exception; do not create additional unsolicited `.md` files.
- No verification scripts when tests cover the functionality — this is a manual/live spike outside the Pest test suite by nature; no Pest test coverage is expected or appropriate for one-off live-endpoint probing (this phase produces no `app/` PHP changes at all).
- Log via `Log::info/error/debug` conventions apply to Laravel code only; the Python service already uses its own status/error string convention (`_set(session_id, status=..., error=...)`) — keep that pattern, don't introduce a different logging style.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| LIVE-02 | Feasibility of `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) as an additional live-source adapter is validated end-to-end before the system relies on it | This document supplies: (1) the verified, decoded page contract (sitekey, form fields, AJAX submit shape, JSON response shape) so the plan can write concrete Playwright steps instead of guessing; (2) the corrected finding that the widget is classic-v2-shaped (not Enterprise-styled), which determines the first 2captcha method to try; (3) a concrete outcome-detection strategy (JSON `success` field + WAF-block-page detection) satisfying the "success/denied/not_found/unreachable, never token=success" requirement; (4) environment/session-consistency requirements (single browser context, fresh per-request `token` nonce, cookie reuse) that the plan's tasks must encode. |
</phase_requirements>

## Standard Stack

### Core (already installed, verified)

| Library | Version (verified locally) | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Playwright (Python) | **1.59.0** (verified via `pip show playwright` in `registraduria-service/`) | Drive a real browser context: load the page, extract sitekey/anti-replay token, inject solved captcha response, trigger the page's own AJAX submit | Already installed and used by `app.py`'s existing flow; the same async API (`async_playwright`) is reusable. |
| Flask | **3.1.3** (verified) | Existing microservice framework | No change — `app.py` stays a Flask app. |
| aiohttp | **3.13.5** (verified) | Raw async HTTP calls to 2captcha `in.php`/`res.php` | Already the pattern in `app.py`; keep using it, do not introduce the `2captcha-python` SDK per CLAUDE.md's no-new-dependency rule. |
| Python | **3.14.3** (verified, `python3 --version`) | Runtime | Confirmed compatible with the above (Playwright 1.59 supports 3.14). |
| 2captcha `in.php`/`res.php` legacy API | current (service) | Solve the checkbox challenge | Reuse the exact submit/poll shape already in `app.py` (`method=userrecaptcha`, `googlekey`, `pageurl`, `invisible`, `json=1` → poll `res.php` every 5s ×30). Add `enterprise=1` **only as a fallback attempt**, not the first try (see Corrected Finding below). |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None new required | — | — | No new pip packages needed for this spike — it is a parameter/URL/injection-logic change to the existing `app.py`, per D-01. |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Legacy 2captcha `in.php`/`res.php` (existing pattern) | 2captcha's newer `createTask`/`getTaskResult` REST API (`api.2captcha.com`) with `type: "RecaptchaV2EnterpriseTask"` | The newer API has cleaner Enterprise-specific fields (`enterprisePayload`, explicit `isInvisible`), but switching APIs mid-spike adds an unnecessary variable and contradicts D-01's "throwaway/direct-modification" framing. Only worth it if the legacy API's `enterprise=1` flag proves insufficient. |

**Installation:** None — everything required is already installed in `registraduria-service`'s environment (Python 3.14.3, Playwright 1.59.0, Flask 3.1.3, aiohttp 3.13.5 all confirmed present via `pip show`/`--version`).

## Corrected Finding: This Is a Classic reCAPTCHA v2 Checkbox, Not an Enterprise-Styled Widget

**Confidence: HIGH — verified directly against the live page source on 2026-07-25, not inferred from docs.**

Fetching `https://wsp.registraduria.gov.co/censo/consultar/` directly returns (relevant excerpt):

```html
<div class="g-recaptcha" data-sitekey="6LcthjAgAAAAAFIQLxy52074zanHv47cIvmIHglH"></div>
<input type="hidden" class="form-control" id="token" name="token" value="32c6117f5dc9cce0db24748be4fc2f43" required>
...
<script src="https://www.google.com/recaptcha/api.js"></script>
```

And the page's own submit handler (`/censo/public/js/functions.js`) reads the response with the **classic** API, not the Enterprise namespace:

```javascript
// Source: https://wsp.registraduria.gov.co/censo/public/js/functions.js (fetched 2026-07-25)
var cptcha = grecaptcha.getResponse();          // classic v2 API — NOT grecaptcha.enterprise.getResponse()
var formData = new FormData();
formData.append('nuip', nuip);
formData.append('tipo', tipo);
formData.append('token', token);                // anti-replay nonce, unrelated to captcha
formData.append('g-recaptcha-response', cptcha);

if (cptcha.length == 0){
    error = ["cptcha", "Marque el cuadro de verificación (Captcha: No soy un robot)"];
    return errores(error);
}
$.ajax({
    type: "POST",
    url: location,             // submits back to the SAME page URL, not a separate API domain
    dataType: "json",
    data: formData,
    contentType: false,
    processData: false,
}).done(function(response){
    if(response.success){
        $("#success").html(response.data.message);
    } else {
        if (response.reload){ location.reload(); }
        else { /* show response.data.message as an error */ }
    }
});
```

**What this means for the spike:**
1. **No `data-sitekey` for an Enterprise div variant, no `data-action`, no `data-s` anywhere in the DOM.** There is nothing extra to scrape beyond the plain `data-sitekey` — PITFALLS.md's assumption that `action`/`data-s` extraction would be needed does not apply to this page as currently deployed.
2. **`grecaptcha.getResponse()` is the classic v2 accessor.** If the sitekey were rendered through the Enterprise JS namespace, the page would call `grecaptcha.enterprise.getResponse()` and load `recaptcha/enterprise.js`. Neither is present.
3. **The sitekey may still be an Enterprise-*registered* key on Google's backend** — Google explicitly supports serving Enterprise-registered sitekeys through the classic `api.js`/`g-recaptcha` front-end for backward compatibility, with all scoring happening invisibly server-side via `createAssessment`. This means: **the front-end shape being "classic v2" does NOT rule out Enterprise-style score-based rejection on Registraduría's backend.** The spike's actual acceptance test (does the backend accept the token?) is therefore still the only way to know — but the *2captcha request itself* should default to the plain `userrecaptcha` method first (matching what the page's own JS actually does), adding `enterprise=1` only as an escalation if plain solves are consistently rejected.
4. **Corollary — re-scope the "score escalation ladder" from PITFALLS.md.** That guidance (start captcha-solve score at 0.3, raise toward 0.9) is a reCAPTCHA **v3/invisible** concept exposed via 2captcha's `min_score`/`action` parameters. There is no such parameter surface for a v2 checkbox solve — 2captcha's `userrecaptcha` method has no score dial. **Recommendation for the plan:** treat "raising the score" not as a request parameter, but as maximizing solve realism/consistency (same UA across mint→submit, real cookies, a single browser context, submitting well within the token's ~2-minute TTL) — these are the actual levers available for a checkbox-shaped Enterprise-backed assessment. If the first ~10 solves are >50% denied, the concrete escalation move is to add `enterprise=1` to the 2captcha task (not a numeric score bump), then re-test.

## Verified Request/Response Contract

**Confidence: HIGH — every field name and response shape below was observed directly against the live endpoint on 2026-07-25 (zero-cost probes: missing/blank captcha field, no real cédula lookups or captcha solves were consumed).**

### Extracting sitekey + anti-replay token (must be done per-request, not hardcoded)

```python
# Playwright pattern — load the real page in one browser context and pull both values from the live DOM.
page = await context.new_page()
await page.goto("https://wsp.registraduria.gov.co/censo/consultar/")
sitekey = await page.get_attribute(".g-recaptcha", "data-sitekey")   # "6LcthjAgAAAAAFIQLxy52074zanHv47cIvmIHglH" (currently stable, but re-extract each run — don't hardcode)
anti_replay_token = await page.get_attribute("#token", "value")       # changes on EVERY page load — server-generated, tied to PHPSESSID
```

Confirmed via two consecutive fetches 1s apart: the `#token` value changed (`32c6117f...` → `567082d8...`) each time — it is a **per-page-load, server-side session nonce**, not a static config value, and is a completely separate concept from the reCAPTCHA token. It must be re-extracted immediately before each submission, from the same session that will submit.

### Session/cookie requirement (HIGH confidence, verified via response headers)

The GET response sets:
```
Set-Cookie: PHPSESSID=nkjfrtc0irl8bugrbj7g95jai0; path=/; secure; HttpOnly
Set-Cookie: cookiesession1=...; Expires=...; Path=/; Secure; HttpOnly
```
The hidden `#token` nonce is almost certainly validated server-side against this `PHPSESSID`. **The page load (GET) and the form submission (POST) must happen in the same Playwright browser context** so cookies persist automatically — do not use separate raw HTTP calls with fresh cookie jars for the GET vs the POST, or the anti-replay token will be rejected independent of captcha correctness.

### Submission shape (HIGH confidence — this is the page's real JS, quoted above)

- **Method:** AJAX `POST` to the **same page URL** (`https://wsp.registraduria.gov.co/censo/consultar/`), not a separate API domain (unlike the old dead `infovotantes` API).
- **Content-Type:** `multipart/form-data` (built via JS `FormData`, not `application/x-www-form-urlencoded` and not JSON body).
- **Fields:**
  | Field | Value | Notes |
  |---|---|---|
  | `nuip` | the cédula, digits only | maxlength 10 client-side; the three test cédulas (10, 10, 8 digits) all fit |
  | `tipo` | `-1` for "LUGAR DE VOTACIÓN ACTUAL..." (current polling place) or `677` for a specific named election | Use `-1` — that's the general/current lookup the phase needs |
  | `token` | the freshly-extracted `#token` hidden value | anti-replay nonce, NOT the captcha token |
  | `g-recaptcha-response` | the captcha token (either the real solved 2captcha token, or read live via `grecaptcha.getResponse()` after injecting it) | |

- **Recommended injection technique:** rather than hand-building the multipart POST outside the browser (risky — see WAF section below), inject the solved token into the page's own `grecaptcha` state and let the page's *own* AJAX handler run:
```javascript
// Executed via page.evaluate() after 2captcha returns a token:
// reCAPTCHA v2 checkbox widgets expose a hidden textarea with id starting "g-recaptcha-response"
document.getElementById("g-recaptcha-response").value = TOKEN;
document.getElementById("g-recaptcha-response").innerHTML = TOKEN;
// Then let the page's real submit path fire (do NOT call raw .submit() — the page binds
// $('#form').submit() -> preventDefault() -> validar() -> $.ajax(...), so either:
//   (a) click the real #enviar submit button via page.click("#enviar"), or
//   (b) call the page's own validar()/ajax logic directly via page.evaluate()
// Prefer (a): clicking the real button exercises the exact code path a human browser would,
// which matters for a risk-scored backend.
```

### Response shape (HIGH confidence, directly observed)

Successful JSON response (structure inferred from the `.done()` handler; exact `success:true` body not observed — would require spending real budget):
```json
{"success": true, "data": {"message": "<HTML with polling place details>"}}
```

Observed failure response (missing captcha, zero cost — confirmed live):
```json
{"success": false, "data": {"message": "Marque el cuadro de verificación o captcha!..."}}
```

Observed additional failure branch in the client JS (not yet triggered/observed live, but present in the code): `response.reload === true` → the page does a full `location.reload()`, which strongly implies **the anti-replay `#token` (or session) expired/mismatched** and the client must restart the flow from a fresh page load. Treat a `reload:true` response as its own outcome (`session_expired` / retry-with-fresh-token), not as `not_found` or `denied_by_score`.

### The fourth response shape: WAF block page (HIGH confidence — directly triggered and observed)

Submitting a real-shaped cédula (`1102812122`) together with a nonsensical `token` and `g-recaptcha-response` value returned, instead of the expected JSON, a full **HTML block page** matching an F5 BIG-IP ASM ("Application Security Manager") signature — CSS classes `.blocked h3`, `.authenticate h3`, and the characteristic split-header/gradient block-page template. This is a **fourth, distinct response shape** the outcome classifier must handle:

```
HTTP/1.1 200 OK   (yes — 200, not 403/429; the WAF block returns 200 with an HTML body)
Content-Type: text/html; charset=UTF-8
<html>...  <div class="header">...</div> <h3 class="blocked">... (WAF block template) ...
```

**Implication for the outcome classifier:** never assume the response body is JSON. Attempt to parse as JSON first (`try/except`); if parsing fails or the body's `Content-Type` isn't JSON-shaped, classify as `source_unreachable` (or a distinct `blocked_by_waf` sub-state worth logging separately in `SPIKE-RESULTS.md`, since it's diagnostically different from a DNS/network failure). **HTTP status code alone is not a reliable signal** — this WAF returns `200 OK` on block.

**Operational safety implication:** this WAF block was triggered by a hand-crafted raw `curl`/multipart POST with an obviously-fake token/captcha value, *not* by a real Playwright browser session with real cookies and a real (even if ultimately rejected) 2captcha-solved token. **The plan should drive 100% of real spike traffic through an actual Playwright-controlled browser (real session, real cookies, real button click)** — never hand-roll raw HTTP requests against this endpoint, since that is exactly the shape of traffic the WAF appears tuned to catch. This also means: do not use `page.request.fetch()` (Playwright's API-request shortcut, bypassing the page) the way the old `app.py` flow does for `infovotantes` — that pattern is what makes a request "raw" from the WAF's perspective. Use real page navigation + a real button click for the wsp flow.

## Architecture Patterns

### Recommended flow inside `app.py`'s existing `_lookup_async()`

```
1. Launch ONE Playwright browser context for the whole attempt (page load + eventual submit).
2. page.goto("https://wsp.registraduria.gov.co/censo/consultar/")
3. Extract sitekey (data-sitekey) and anti-replay token (#token value) from the live DOM — do not hardcode either.
4. Submit sitekey + this page's own URL as pageurl to 2captcha's existing in.php flow
   (method=userrecaptcha, googlekey=<extracted sitekey>, pageurl=<this exact URL>, invisible=0, json=1).
   -> First attempt: WITHOUT enterprise=1 (matches the page's actual classic-v2 shape).
5. Poll res.php (existing 5s x 30 loop — no change needed).
6. On token received: within the SAME browser context/page (session/cookies must match),
   fill #nuip, select #tipo (-1), inject the token into the g-recaptcha-response element,
   then page.click("#enviar") to trigger the page's OWN ajax submit handler.
7. Capture the AJAX response body. Classify:
     - JSON with success:true            -> success
     - JSON with success:false + reload:true         -> session_expired (retry once with a fresh page load/token)
     - JSON with success:false + a "not found"/"no existe"/similar message -> not_found
     - JSON with success:false + a captcha/score-related message           -> denied_by_score
     - Non-JSON body / WAF block signature / network error / timeout      -> source_unreachable
8. If step 4-7 is consistently denied_by_score across several attempts (>50% per D-04's threshold),
   retry the SAME cédula with enterprise=1 added to the 2captcha task before concluding no-go for that cédula.
```

### Anti-Patterns to Avoid
- **Raw `page.request.fetch()` / hand-built multipart POST outside a real page context** — this is what the old `infovotantes` flow does and is exactly the traffic shape that triggered the WAF block page during this research. Use real page navigation + real button click for `wsp`.
- **Treating a 2captcha token as a successful lookup** — per D-06/Pitfall 8, only a parsed `success:true` JSON body counts; a token existing is necessary, not sufficient.
- **Assuming HTTP status code communicates outcome** — the WAF returns `200 OK` on block; the real page's own failure responses are also `200 OK` with `success:false`. Status code is not a useful signal here; parse the body.
- **Hardcoding the sitekey or the `#token` nonce** — both must be freshly read from the live page for every attempt; the nonce is confirmed to rotate on every load.
- **Passing a numeric "score" to 2captcha for this sitekey** — there is no such parameter for a `userrecaptcha`/checkbox-type solve; don't invent one.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Captcha solving | A custom OCR/ML captcha breaker | The existing 2captcha `in.php`/`res.php` integration in `app.py`, extended with the new sitekey/pageurl and optionally `enterprise=1` | Already paid-for, working infrastructure; reCAPTCHA v2/Enterprise is not realistically breakable without a human-solving farm. |
| Session/cookie handling | Manual cookie-jar plumbing across separate HTTP calls | A single Playwright `BrowserContext` for the whole GET→solve→submit sequence | Playwright already persists cookies automatically within a context; manual cookie extraction/replay is exactly the kind of "raw traffic shape" that appears to trip the WAF. |
| Outcome classification | Ad-hoc string matching invented without seeing real responses | The verified JSON contract above (`success`, `data.message`, `reload`) plus a non-JSON/WAF fallback branch | This is now empirically known, not guessed — build the classifier against the real shape. |

**Key insight:** almost nothing new needs to be built — the existing `app.py` 2captcha loop is ~90% reusable (confirmed again by this research); the delta is entirely in (a) which URL/sitekey/pageurl to send 2captcha, (b) doing the page interaction through a real browser session with a real button click instead of a raw fetch, and (c) a response classifier against the now-known JSON shape plus a WAF-block fallback.

## Common Pitfalls

### Pitfall A: Treating this as reCAPTCHA Enterprise-invisible/v3 and trying to scrape an `action`/`data-s` that doesn't exist
**What goes wrong:** Time is spent hunting devtools/network tab for `action`/`data-s` params (per the original CONTEXT.md discretion note) that this page simply does not have — it's a classic `data-sitekey` checkbox.
**Why it happens:** Prior research (correctly, given it couldn't fetch the live page) assumed a generic "Enterprise" shape based on documentation patterns, not this specific page.
**How to avoid:** Skip the manual devtools hunt for `action`/`data-s` entirely — this research already confirms they don't exist in the DOM. Extract only `data-sitekey` (from `.g-recaptcha`) and the separate `#token` anti-replay nonce.
**Warning signs:** N/A — resolved by this research; just don't spend spike time re-deriving it.

### Pitfall B: Confusing the anti-replay `#token` field with the reCAPTCHA token
**What goes wrong:** Both are named/thought of as "the token." Submitting the reCAPTCHA solve into the `token` field (or vice versa) will fail silently or produce a confusing error.
**Why it happens:** The page literally has two different hidden concepts both colloquially called "token": `#token` (session nonce) and `g-recaptcha-response` (captcha solve).
**How to avoid:** Keep them as clearly separate variables in code (`session_nonce` vs `recaptcha_token`) from the start.
**Warning signs:** A `success:false` response with a message unrelated to captcha (e.g., about the request/session itself) despite a fresh, valid captcha token.

### Pitfall C: Hand-crafted/raw HTTP requests against `wsp` trip the WAF
**What goes wrong:** A request that doesn't look like it came from a real, freshly-loaded browser session (missing realistic headers/cookies/referer, or a multipart POST built outside any page context) can return an HTML WAF block page instead of the expected JSON — confirmed directly in this research.
**Why it happens:** An F5-style WAF sits in front of the site (see Environment Availability) and appears tuned against exactly this shape of traffic.
**How to avoid:** Do everything — page load, form fill, button click — inside one real Playwright page/context. Do not use `page.request.fetch()` shortcuts or external `aiohttp` calls against `wsp` itself (only use `aiohttp`/raw HTTP for the 2captcha side-channel, which is a different, unrelated host).
**Warning signs:** A response body that isn't valid JSON, or that contains `<html>`/CSS instead of `{"success":...}`.

### Pitfall D: Reusing a stale `#token` nonce or an expired 2captcha token
**What goes wrong:** The 2-minute captcha-token TTL (Pitfall 8, PITFALLS.md) and the page's own session-nonce rotation (confirmed here to change on every load) can both silently invalidate a submission if there's a delay between extraction and submit (e.g., the ~60-150s 2captcha solve time itself).
**Why it happens:** The nonce/token are extracted at page-load time, but the captcha solve takes 60-150s (per `app.py`'s existing 5s×30 poll), during which the session-side `#token` could theoretically be invalidated by the same session issuing a second GET, or simply by TTL.
**How to avoid:** Load the page once, immediately read both sitekey and `#token`, start the 2captcha solve, and — critically — do **not** perform any other navigation/request in that same browser context while waiting, so the session-side token stays exactly as extracted. Submit immediately once the captcha token arrives.
**Warning signs:** A `reload:true` response despite a seemingly-correct submission — treat this as the signal that the timing/session assumption broke, and retry with a fresh page load.

## Code Examples

### Extracting the live page's captcha + session contract
```python
# Source: this research, fetched directly from https://wsp.registraduria.gov.co/censo/consultar/ on 2026-07-25
page = await context.new_page()
await page.goto(WSP_PAGE_URL)
sitekey = await page.get_attribute(".g-recaptcha", "data-sitekey")
session_nonce = await page.get_attribute("#token", "value")
```

### 2captcha submit (extends the EXISTING app.py pattern — no new library)
```python
# First attempt: plain v2 (matches the page's classic-v2-shaped widget)
resp = await http.post("https://2captcha.com/in.php", data={
    "key": TWO_CAPTCHA_KEY,
    "method": "userrecaptcha",
    "googlekey": sitekey,          # freshly extracted, not hardcoded
    "pageurl": WSP_PAGE_URL,
    "invisible": "0",              # visible checkbox, matches app.py's existing pattern
    "json": "1",
})
# Escalation attempt (only if >50% of first-attempt solves are denied per D-04's threshold):
# add "enterprise": "1" to the same payload and re-test.
```

### Injecting the solved token and submitting via the page's real button
```python
await page.evaluate(
    """(token) => {
        const el = document.getElementById('g-recaptcha-response');
        if (el) { el.value = token; el.innerHTML = token; }
    }""",
    token,
)
await page.fill("#nuip", cedula)
await page.select_option("#tipo", "-1")
async with page.expect_response(lambda r: r.url == WSP_PAGE_URL and r.request.method == "POST") as resp_info:
    await page.click("#enviar")
response = await resp_info.value
raw_body = await response.text()
try:
    result = await response.json()
except Exception:
    # Non-JSON body: WAF block page or unexpected error page
    outcome = "source_unreachable"
else:
    if result.get("success"):
        outcome = "success"
    elif result.get("reload"):
        outcome = "session_expired"
    else:
        message = result.get("data", {}).get("message", "")
        # classify further against observed vocabulary, e.g. "Marque el cuadro de verificación" (captcha-related)
        # vs a not-found phrasing (only discoverable by an actual live attempt with a real solved token) —
        # log the raw message string in SPIKE-RESULTS.md either way so the taxonomy can be refined in-spike.
        outcome = "denied_by_score"  # default bucket for unrecognized failure messages; refine once real messages are seen
```

## Runtime State Inventory

Not applicable — this is a feasibility spike modifying `registraduria-service/app.py` in place (D-01); it is not a rename/refactor/migration phase. No stored data, live service config, OS-registered state, secrets, or build artifacts are being renamed or migrated. (`.env`'s `TWO_CAPTCHA_KEY` is reused unchanged — confirmed present via `grep -c TWO_CAPTCHA_KEY .env` → 1 match.)

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Python 3 | `registraduria-service` runtime | ✓ | 3.14.3 | — |
| Playwright (Python) | Browser automation of the wsp page | ✓ | 1.59.0 (pip) | — |
| Playwright browser binaries (Chromium) | Actual headless browser launch | Not directly verified this pass (not re-launched to avoid an unnecessary browser download/launch during research) | — | Executor should run `playwright install chromium` (or confirm already installed) as the very first spike step; `app.py`'s working history (`service.log`) implies it was installed and functional as of 2026-07-20. |
| Flask | Microservice framework | ✓ | 3.1.3 | — |
| aiohttp | 2captcha HTTP calls | ✓ | 3.13.5 | — |
| `registraduria-service/.env` with `TWO_CAPTCHA_KEY` | 2captcha auth | ✓ | 1 key present (value not read/echoed) | — |
| Flask dev server (port 5757) | Local `/lookup`+`/result` endpoints | ✗ (not currently running — `service.log`'s last entry is 2026-07-20; a live `curl` to `localhost:5757` during this research got "connection refused") | — | Must be (re)started (`python app.py`) before the spike executes; no fallback needed, this is just a "start it" step, not a missing capability. |
| `wsp.registraduria.gov.co` reachability | The live target itself | ✓ reachable (HTTP 200/500 observed intermittently — see note below), hosted on a Colombian residential/business ISP (AS13489 UNE EPM Telecomunicaciones, Bogotá — NOT a global CDN/cloud edge) | — | None — this is the thing being spiked. Note the occasional bare `HTTP/1.1 500 Internal Server Error` observed on a plain GET (page still rendered fully) — treat isolated 500s on the initial page load as a possible retry-once case, not immediately `source_unreachable`, since the exact same GET returned 200 moments later. |
| 2captcha account/balance | Solving service | Assumed available (existing working key in `.env`; balance itself not checked — would require an authenticated API call, out of scope for this research pass) | — | If balance is insufficient, the executor will discover it on the very first `in.php` submit (`status=0` response) — flag immediately as a hard blocker, not silently retry. |

**Missing dependencies with no fallback:**
- None identified that would block starting the spike — the one "missing" item (Flask service not running) is a trivial startup step, not a capability gap.

**Missing dependencies with fallback:**
- Playwright Chromium binary presence unconfirmed — verify with `playwright install chromium` (idempotent, no-op if already installed) as the first executable step of the spike, rather than assuming.

## Open Questions

1. **Does `wsp.registraduria.gov.co`'s backend actually accept a plain (non-`enterprise=1`) 2captcha `userrecaptcha` solve, given the classic-v2-shaped front end?**
   - What we know: the front-end widget and JS are 100% classic-v2-shaped (verified above).
   - What's unclear: whether the sitekey is registered as an Enterprise key in Google Cloud Console (invisible from outside), which would determine whether the backend's assessment is scored (Enterprise) or a simple pass/fail (classic v2).
   - Recommendation: this is precisely the spike's core empirical question — try plain `userrecaptcha` first (cheapest, matches the page), escalate to `enterprise=1` only if denials exceed the D-04 50% threshold. Document which one (if either) succeeds in `SPIKE-RESULTS.md` — this is itself a key finding for Phase 11.

2. **What exact message strings does the backend return for `denied_by_score` vs `not_found` (cédula genuinely not in the census) vs other failure modes?**
   - What we know: the only observed failure message so far is the captcha-empty case (`"Marque el cuadro de verificación o captcha!..."`), obtained without spending real budget.
   - What's unclear: the real vocabulary for "not found" and any low-score/risk-denial message — these can only be observed by actually spending real 2captcha solves against real cédulas (which the spike's budget is for).
   - Recommendation: the plan's classifier should default unrecognized `success:false` messages to `denied_by_score` (safe default — never conflate with `success`) and log the raw `data.message` string verbatim into `SPIKE-RESULTS.md` for every attempt, so the taxonomy can be refined with real evidence during execution rather than guessed in advance.

3. **Does the intermittent bare `HTTP 500` on GET (observed once, not reproduced on retry) indicate a flaky backend worth retrying, or a WAF/rate-limit artifact?**
   - What we know: one GET returned `500` with a fully-rendered page body; a near-immediate repeat GET returned `200` with the same structure.
   - What's unclear: root cause (backend flake vs. transient WAF challenge vs. unrelated).
   - Recommendation: treat a single `500` on page load as retry-once-then-proceed, not as `source_unreachable`; only classify `source_unreachable` after a retry also fails or times out.

4. **Colombia-hosted, non-CDN IP (AS13489 UNE EPM, Bogotá) — does requesting from outside Colombia (a non-Colombian residential/dev-machine IP) meaningfully change acceptance rates?**
   - What we know: the site is NOT behind a global CDN edge (Cloudflare/Akamai/etc.) — it resolves to a Colombian ISP-hosted IP directly. This somewhat elevates D-02's already-accepted "local IP may score differently" caveat from a generic worry to a more specific one (geo-consistency, not just generic IP reputation).
   - What's unclear: whether this specific factor drives any denials seen during the spike.
   - Recommendation: if the spike sees a high denial rate on the local dev machine, note IP geography as a candidate explanation in `SPIKE-RESULTS.md` and reference D-02's deferred korserver-container option as the next thing to try (still out of scope for Phase 9 itself, per the locked decision).

## Sources

### Primary (HIGH confidence — directly fetched/verified this pass)
- `https://wsp.registraduria.gov.co/censo/consultar/` — fetched directly via `curl` and `WebFetch`, 2026-07-25: page HTML, form fields, sitekey, anti-replay `#token`, response headers/cookies.
- `https://wsp.registraduria.gov.co/censo/public/js/functions.js` — fetched directly, 2026-07-25: exact client-side submit/validation logic, AJAX contract, `grecaptcha.getResponse()` usage.
- Direct probe responses (zero real-budget cost): missing-captcha JSON error shape; WAF block-page HTML (F5 BIG-IP ASM signature) triggered by a malformed raw POST.
- `ipinfo.io` lookup for `190.248.51.105` (resolved via `dig`) — confirms Colombian ISP hosting (AS13489 UNE EPM Telecomunicaciones, Bogotá), no CDN edge.
- Local environment probes: `pip show playwright` (1.59.0), `python3 --version` (3.14.3), `python3 -c "import flask"` (3.1.3), `python3 -c "import aiohttp"` (3.13.5), `registraduria-service/service.log` (last activity 2026-07-20, service not currently running), `registraduria-service/.env` (`TWO_CAPTCHA_KEY` present).
- `registraduria-service/app.py` (read directly) — existing 2captcha `in.php`/`res.php` loop, session pattern, confirmed reusable.

### Secondary (MEDIUM confidence — WebSearch/WebFetch verified against 2captcha's own docs)
- [2captcha reCAPTCHA Enterprise solver](https://2captcha.com/p/recaptcha_enterprise) — confirms `enterprise=1` as the legacy-API delta parameter; confirms `action`/`data-s` only apply "in most cases" for v2-with-extra-data variants (not present on this specific page, per direct verification above).
- [2captcha reCAPTCHA v2 Enterprise API docs](https://2captcha.com/api-docs/recaptcha-v2-enterprise) — confirms the newer `createTask`/`getTaskResult` REST shape (`RecaptchaV2EnterpriseTaskProxyless`, `websiteKey`, `enterprisePayload`) as the alternative-but-not-required newer API.
- General reCAPTCHA-Enterprise-via-classic-widget backward-compatibility behavior — consistent with what was observed on `wsp` (classic front end, unknown backend scoring); this is standard, documented Google Cloud reCAPTCHA Enterprise behavior for migrated v2/v3 keys.

### Tertiary (LOW confidence / not found)
- No public GitHub issues, blog posts, or forum threads specifically documenting automation attempts against `wsp.registraduria.gov.co` were found. A couple of freelance-job listings (Workana) referencing "scraping a form with Google reCAPTCHA" for what appears to be a similar/related target were found but contain no technical detail worth citing.
- 2captcha account balance / historical success-rate against this specific sitekey — not checkable without live spend; genuinely unknown until the spike runs.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all versions verified directly against the installed environment, no dependency changes needed.
- Architecture/request contract: HIGH — verified directly against the live page's HTML/JS, not inferred.
- Backend acceptance (the actual go/no-go question): MEDIUM-LOW — inherently unknowable without spending real spike budget; this is expected and is exactly what the spike itself resolves. This research narrows *how* to spend that budget effectively, not *whether* it will succeed.
- Pitfalls: HIGH — the WAF-block and session/nonce pitfalls were directly triggered and observed, not theoretical.

**Research date:** 2026-07-25
**Valid until:** Short shelf-life — this is a live, unversioned government page that could change its captcha widget, form fields, or WAF posture at any time without notice. Treat findings as valid for **this specific spike execution only**; if execution is delayed more than ~1-2 weeks, re-verify the sitekey/DOM contract before writing the plan's tasks in stone.

---
*Research for: Phase 9 - Live-Source Feasibility Spike*
*Researched: 2026-07-25*
