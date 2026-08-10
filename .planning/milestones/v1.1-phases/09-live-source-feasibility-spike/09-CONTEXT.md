# Phase 9: Live-Source Feasibility Spike - Context

**Gathered:** 2026-07-25
**Status:** Ready for planning

<domain>
## Phase Boundary

A time-boxed, non-blocking spike that determines whether `wsp.registraduria.gov.co`'s reCAPTCHA Enterprise flow can be solved and accepted end-to-end as a live polling-place source. The phase delivers a working (or failed) proof-of-concept run against the real endpoint plus a documented go/no-go recommendation. It does **not** deliver a production-wired adapter, a UI change, or the reconciliation job — those are Phase 10/11 concerns and remain unblocked regardless of this spike's outcome.

</domain>

<decisions>
## Implementation Decisions

### Spike Code Disposition
- **D-01:** Modify `registraduria-service/app.py` directly — throwaway/experimental change, not a new adapter class or isolated script. Change target URL, sitekey, and captcha params directly in the existing Flask service.

### Environment
- **D-02:** Run the spike from the local dev machine (where `registraduria-service` already runs — confirmed via `service.log`), not the korserver Dokploy container. Faster iteration, no deploy step. Accepted caveat: local IP may score differently than production IP if Enterprise factors in IP reputation — not treated as a blocker for this spike.

### Test Data
- **D-03:** Three specific real cédulas, provided directly by the user, with known ground-truth polling-place data to verify results against: `1102812122`, `1102815878`, `64552231`. The executor does not need to pause and ask — these are already supplied.

### Captcha Budget
- **D-04:** Budget ~20-30 real 2captcha Enterprise solve attempts across the spike, enough to work through the score-escalation ladder from PITFALLS.md (start at 0.3, raise toward 0.9 only if >50% denied) across the three known cédulas.

### Go/No-Go Scope Boundary
- **D-05:** Phase 9 stops at the documented go/no-go recommendation, even on success. Wiring a working adapter into `RegistraduriaService`/production config (flipping `REGISTRADURIA_LIVE_ENABLED`, updating target domain for real use) is explicitly out of scope for this phase — separate future phase/quick-task, regardless of spike outcome.

### Documentation & Stopping Condition
- **D-06:** Write a dedicated `SPIKE-RESULTS.md` in the phase directory (not folded into the standard `SUMMARY.md`), capturing: extracted sitekey/action/data-s values, the outcome taxonomy breakdown (`success` / `denied_by_score` / `not_found` / `source_unreachable`), and the final go/no-go recommendation with rationale. This is what Phase 11 will consult to scope how much automated reconciliation is achievable.
- **D-07:** Stopping condition is budget exhaustion (D-04), not a fixed wall-clock limit. Once the ~20-30 solve budget is used, stop and document whatever signal was gathered — a mixed/inconclusive result counts as a documented "no-go for now," not a reason to keep spending.

### Claude's Discretion
- Exact mechanics of extracting the Enterprise sitekey/action/data-s from `wsp.registraduria.gov.co/censo/consulta/` (manual devtools inspection vs. scripted extraction) — whichever is fastest and reliable for a one-time spike.
- Exact structure/format of `SPIKE-RESULTS.md` beyond the required content (D-06).
- Whether to keep the old dead-domain v2 logic in `app.py` as commented-out reference or rely on git history.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Feasibility research (already done — do not re-litigate)
- `.planning/research/STACK.md` §"The reCAPTCHA Enterprise feasibility question" (lines 56-75) and §"Stack Patterns by Variant" (lines 123-134) — the exact technical delta needed (new sitekey, `enterprise=1` param, possible `action`/`data-s`, token goes into `g-recaptcha-response` + form POST not a Bearer header), and what changes downstream depending on spike outcome.
- `.planning/research/PITFALLS.md` §"Pitfall 8: reCAPTCHA Enterprise token is 'solved' but the lookup is still denied" (lines 139-153) — the required outcome taxonomy (`success` / `denied_by_score` / `not_found` / `source_unreachable`), the score-escalation strategy (start 0.3, raise toward 0.9 only if >50% denied), ~2-minute token TTL, and the need for environment consistency (same UA/headers/origin the token was minted under).
- `.planning/research/ARCHITECTURE.md` lines 144, 162, 240, 247, 250 — why `RegistraduriaService` must stay a pure live-source adapter (swappable pending this spike), and the async/interactive-vs-automated caveat: Phase 11's automated reconciliation completeness is gated by this spike's outcome.
- `.planning/research/SUMMARY.md` — overall risk framing: the census snapshot makes a spike failure acceptable; the milestone still delivers via fallback + provenance + scheduled retry.

### Existing code the spike modifies/depends on
- `registraduria-service/app.py` — the Flask+Playwright+2captcha microservice to modify in place (D-01). Currently targets the dead `eleccionescolombia.registraduria.gov.co` + classic reCAPTCHA v2 (`method=userrecaptcha`, sitekey `6Lc9DmgrAAAAAJAjWVhjDy1KSgqzqJikY5z7I9SV`), calling `apiweb-eleccionescolombia.infovotantes.com` via Playwright browser `fetch()` with the token as a Bearer header. 2captcha integration is raw `aiohttp` calls to `2captcha.com/in.php`/`res.php` (no SDK).
- `registraduria-service/.env` — holds `TWO_CAPTCHA_KEY`, loaded by the service's own `load_env()` helper (not python-dotenv).
- `app/Services/RegistraduriaService.php` — the Laravel `LiveSourceAdapter` implementation (`startLookup`/`getResult`/`isReachable`); NOT modified by this phase (D-05 defers production wiring).
- `app/Services/LiveSourceAdapter.php` — the interface (`startLookup(string $cedula): string`, `getResult(string $sessionId): array`, `isReachable(): bool`) a production-track adapter would need to implement — relevant only if a later phase revisits D-05's deferred wiring.

### Prior-phase decisions this spike must not contradict
- `.planning/phases/08-resilient-pollingplaceresolver-service/08-CONTEXT.md` D-05 — `REGISTRADURIA_LIVE_ENABLED` kill switch already exists (default `true`), ready to matter once a production adapter lands (out of scope here per D-05 above).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `registraduria-service/app.py`'s existing 2captcha submit/poll loop (`in.php` submit → poll `res.php` every 5s up to 30 times) is largely reusable for Enterprise — needs `enterprise=1` (+ possibly `action`/`data-s`) added to the submit payload, a new sitekey, and the token-injection step changed from a Bearer header to a `g-recaptcha-response` form field + form submit against the new endpoint.
- `App\Services\LiveSourceAdapter` interface and the resolver's multi-adapter-ready design (Phase 8, LIVE-01) mean a production adapter, if ever built later, has a clear contract to satisfy — no resolver redesign needed.

### Established Patterns
- Async session pattern in `app.py`: `POST /lookup` returns a `session_id` immediately (background thread), `GET /result/<id>` polls status (`pending`/`solving_captcha`/`waiting_result`/`done`/`error`). Kept as-is for the spike.
- `service.log` confirms the Flask dev server is already running locally on port 5757.

### Integration Points
- None required for this phase — the spike is deliberately isolated from the resolver/UI per D-05.

</code_context>

<specifics>
## Specific Ideas

- Real test cédulas (with known ground-truth polling-place data to verify against): `1102812122`, `1102815878`, `64552231` — these came directly from the user during discussion, no need for the executor to pause and request them.
- Budget: 20-30 2captcha Enterprise solve attempts total.

</specifics>

<deferred>
## Deferred Ideas

- Wiring a working wsp adapter into `RegistraduriaService`/production config, flipping `REGISTRADURIA_LIVE_ENABLED` for real — deferred regardless of spike outcome (D-05). Belongs to a future phase/quick-task once a go decision exists.
- Running the spike from the korserver Dokploy container to validate under production IP/environment conditions — deferred; local dev machine chosen instead (D-02). Revisit only if local results are ambiguous and IP reputation is suspected as a factor.

None else — discussion stayed within phase scope.

</deferred>

---

*Phase: 09-live-source-feasibility-spike*
*Context gathered: 2026-07-25*
