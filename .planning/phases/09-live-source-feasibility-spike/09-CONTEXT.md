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
- **D-01:** Modify `registraduria-service/app.py` in place — throwaway/experimental change, not a clean second adapter class. Change target URL, sitekey, and captcha params directly in the existing Flask service. If the spike fails, nothing production-grade was wasted; if it succeeds, cleanup/production-wiring is separate follow-up work (see D-04).
- **D-02:** Run the spike from the local dev machine (where `registraduria-service` is already running per `service.log`), not the korserver Dokploy container. Faster iteration, no deploy step required. Accept the caveat that a local IP may behave differently than the production server's IP if Registraduría's Enterprise scoring factors in IP reputation — this is a known limitation, not a blocker.

### Test Data & Captcha Budget
- **D-03:** Test cédulas will be supplied by the user (Kristian) at execution time, not pulled from campaign Apoyo data. **The plan/executor must pause and explicitly ask the user for cédula(s) before running any live submission against `wsp.registraduria.gov.co`.**
- **D-04 (budget):** Budget ~20-30 real 2captcha Enterprise solve attempts across the spike — enough to try a few distinct cédulas up the 0.3→0.9 score-escalation ladder (per `STACK.md`'s recommended strategy) and get a real signal on denial rate, without open-ended spend. At $1-3/1000 solves this is trivially cheap regardless of outcome.

### Go/No-Go Scope Boundary
- **D-05:** Phase 9 stops at the documented go/no-go recommendation. Even if the spike succeeds (tokens accepted end-to-end), wiring the new adapter into `RegistraduriaService`/production config (flipping `REGISTRADURIA_LIVE_ENABLED`, updating the target domain for real) is explicitly **out of scope for this phase** — it becomes separate follow-up work. This matches the ROADMAP's literal success criterion #3 ("a documented go/no-go decision... is produced") and keeps the spike honestly time-boxed rather than sliding into a full adapter rewrite mid-investigation.

### Decision Documentation & Timebox
- **D-06:** Write a dedicated `SPIKE-RESULTS.md` in the phase directory (not just folded into the standard `SUMMARY.md`) capturing: the extracted sitekey/action/data-s values, the outcome taxonomy breakdown (counts of `success` / `denied_by_score` / `not_found` / `source_unreachable`), and the final go/no-go recommendation with rationale. This is the durable reference Phase 11 will consult when scoping how much of automated reconciliation is achievable (per `ARCHITECTURE.md`'s async caveat and `FEATURES.md`'s "reconciliation job is inert without a live source" note).
- **D-07:** The spike's stopping condition is budget exhaustion (D-04), not a fixed wall-clock limit. Once the ~20-30 solve budget is used, stop and document whatever signal was gathered — an inconclusive/mixed result counts as a documented "no-go for now" rather than blocking on more spend. This ties the timebox directly to the budget decision rather than introducing a second independent constraint.

### Claude's Discretion
- Exact mechanics of extracting the Enterprise sitekey/action/data-s from the `wsp.registraduria.gov.co/censo/consulta/` page (manual devtools inspection vs. scripted extraction) — whichever is fastest and reliable enough for a one-time spike.
- Exact structure/format of `SPIKE-RESULTS.md` beyond the required content (D-06).
- Whether to keep the old dead-domain v2 logic in `app.py` as commented-out reference or rely on git history — git history is sufficient, no explicit requirement either way.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Feasibility research (already done — do not re-litigate)
- `.planning/research/STACK.md` §"The reCAPTCHA Enterprise feasibility question" (lines 56-75) and §"Stack Patterns by Variant" (lines 123-134) — the exact technical delta needed (new sitekey, `enterprise=1` param, possible `action`/`data-s`, token goes into `g-recaptcha-response` + form POST not a Bearer header), the encouraging signal (visible checkbox = v2-style Enterprise, more solvable than invisible v3-score), and what changes in downstream phases depending on spike outcome.
- `.planning/research/PITFALLS.md` §"Pitfall 8: reCAPTCHA Enterprise token is 'solved' but the lookup is still denied" (lines 139-151) — the exact outcome taxonomy required (`success` / `denied_by_score` / `not_found` / `source_unreachable`), the score-escalation strategy (start 0.3, raise toward 0.9 only if >50% denied), ~2-minute token TTL, and the need for environment consistency (same UA/headers/origin the token was minted under).
- `.planning/research/ARCHITECTURE.md` lines 144, 162, 240 — why `RegistraduriaService` must stay a pure live-source adapter (swappable pending this spike), and the async/interactive-vs-automated caveat that Phase 11's automated reconciliation completeness is gated by this spike's outcome.
- `.planning/research/FEATURES.md` line 91 — reconciliation job should be built as a no-op-safe scaffold regardless of this spike's outcome; do not let Phase 11 block on Phase 9.
- `.planning/research/SUMMARY.md` lines 14, 41 — overall risk framing: the census snapshot makes a spike failure acceptable: the milestone still delivers via fallback + provenance + scheduled retry.

### Existing code the spike modifies/depends on
- `registraduria-service/app.py` — the Flask+Playwright+2captcha microservice to modify in place (D-01). Currently targets the dead `eleccionescolombia.registraduria.gov.co` + classic reCAPTCHA v2 (`method=userrecaptcha`, sitekey `6Lc9DmgrAAAAAJAjWVhjDy1KSgqzqJikY5z7I9SV`). No 2captcha SDK — raw `aiohttp` calls to `2captcha.com/in.php`/`res.php`.
- `registraduria-service/.env` — holds `TWO_CAPTCHA_KEY`, loaded by the service's own `load_env()` helper (not python-dotenv).
- `app/Services/RegistraduriaService.php` — the Laravel `LiveSourceAdapter` implementation (`startLookup`/`getResult`/`isReachable`) that talks to the Python service; NOT modified by this phase (D-05 defers production wiring), but its async `/lookup` + `/result/{id}` contract is what any eventual production adapter must keep satisfying.
- `app/Services/LiveSourceAdapter.php` — the interface a production-track adapter would need to implement (`startLookup(string $cedula): string`, `getResult(string $sessionId): array`, `isReachable(): bool`) — relevant only if D-05's "wire it in" path is later revisited.

### Prior-phase decisions this spike must not contradict
- `.planning/phases/07-source-flag-schema-resolution-audit-trail/07-CONTEXT.md` D-02 — the `LIVE` source enum is already adapter-agnostic; a successful wsp result would count as `LIVE` with zero schema changes.
- `.planning/phases/08-resilient-pollingplaceresolver-service/08-CONTEXT.md` D-05 — `REGISTRADURIA_LIVE_ENABLED` kill switch already exists (default `true`), ready to matter once/if a production adapter lands (out of scope here per D-05 above).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `registraduria-service/app.py`'s existing 2captcha polling loop (`in.php` submit → poll `res.php` every 5s up to 30 times) is ~90% reusable for Enterprise — only needs `enterprise=1` (+ possibly `action`/`data-s`) added to the submit payload, a new sitekey, and the token-injection step changed from a Bearer header to a `g-recaptcha-response` form field + POST submit.
- `App\Services\LiveSourceAdapter` interface and the resolver's multi-adapter-ready design (Phase 8) mean a production adapter, if ever built, has a clear contract to satisfy — no resolver redesign needed (LIVE-01 already validated this).

### Established Patterns
- Async session pattern: `POST /lookup` returns a `session_id` immediately (background thread), `GET /result/<id>` polls status (`pending`/`waiting_captcha`/`done`/`error`). Keep this shape if the spike's script needs to be polled rather than run synchronously.
- Structured "key findings" docstring at the top of `app.py` (sitekey, API endpoint, auth pattern) — good place to record the new wsp sitekey/action/endpoint once extracted, mirroring how the current dead-domain findings are documented.

### Integration Points
- None required for this phase — the spike is deliberately isolated from the resolver/UI per the scope boundary (D-05).

</code_context>

<specifics>
## Specific Ideas

- Test cédulas come from the user directly — the executor/planner must build in an explicit pause point to request them before any live submission runs (D-03).
- The user explicitly wants a standalone `SPIKE-RESULTS.md`, not just a line in `SUMMARY.md` — treat this as a required deliverable file, not optional polish.

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
