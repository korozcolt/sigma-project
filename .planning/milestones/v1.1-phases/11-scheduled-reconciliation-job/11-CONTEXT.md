# Phase 11: Scheduled Reconciliation Job - Context

**Gathered:** 2026-07-26
**Status:** Ready for planning

<domain>
## Phase Boundary

An unattended, scheduled job that automatically re-attempts live lookup for fallback-sourced voters and upgrades them to `live` when the source succeeds — campaign-safe (resolves each voter's own campaign from the voter record), auditable (records actor/reason even though unattended), rate-limited/bounded (cannot exhaust the captcha budget or self-flood), and cannot be silently frozen by a stuck lock. Covers RECON-01 through RECON-06.

This phase ALSO includes production-wiring the `wsp.registraduria.gov.co` live source that Phase 9's spike validated (Verdict: GO, 96.7% success) but explicitly deferred wiring — because without it, `PollingPlaceResolver::resolveAutomated()`'s live tier is permanently unreachable and this phase's entire premise (RECON-01: "upgrades them when the live source succeeds") would be hollow.

</domain>

<decisions>
## Implementation Decisions

### Live-Source Production Wiring (prerequisite, done within this phase)
- **D-01:** Fix the reachability gap: `config('services.registraduria.probe_url')` / `REGISTRADURIA_PROBE_URL` currently points to the dead `apiweb-eleccionescolombia.infovotantes.com` domain (a leftover from before Phase 9). Update it to correctly reflect `wsp.registraduria.gov.co` reachability so `RegistraduriaService::isReachable()` (called by `PollingPlaceResolver::attemptLiveAutomated()` before every automated attempt) returns true when the live source is actually up. Without this fix, the reconciliation job would call `resolveAutomated()`, always get "unreachable," and silently fall through to snapshot every time — RECON-01 would never actually succeed.
- **D-02:** Write an HTML-to-structured-fields parser for the wsp success response. `registraduria-service/app.py` (Phase 9) currently returns only `data.raw_message_html` (a raw `<table id="consulta">` HTML blob) on `success`, not the structured fields (`puesto_nombre`, `puesto_codigo`, `zona_codigo`, `mesa_numero`, `departamento`, `municipio`, `direccion`) that `RegistraduriaService::getResult()`'s existing docblock contract promises and that `HasRegistraduriaPolling::fillPollingPlaceFields()` / `PollingPlaceResolver::resolveOrCreatePollingPlace()` require to populate `municipality_id`/`polling_place_id`/etc. **The full HTML table structure was never fully captured** — Phase 9's `09-SPIKE-RESULTS.md` only logged the first ~200 characters of each response (enough to confirm the `id="consulta"` table exists, not enough to know every column/row shape). Research for this phase must re-extract at least one full, untruncated wsp success response (e.g., via a fresh zero-or-low-cost live attempt) to determine the exact HTML structure before writing the parser.
- **D-03:** This wiring work happens either in `registraduria-service/app.py` (parse the HTML into the structured dict server-side, matching the old `infovotantes` response shape so `RegistraduriaService.php` needs zero changes) or in `RegistraduriaService.php` (parse `raw_message_html` into structured fields on the PHP side). Left as **Claude's discretion** for research/planning to decide based on where parsing is more reliable/testable — no user preference either way.

### System Actor for Audit Trail (RECON-03)
- **D-04:** Automated reconciliation writes use `resolved_by = null` + `resolved_via = 'reconciliation'` — no seeded system/bot user. Zero new migrations needed: Phase 7 already made `polling_place_resolutions.resolved_by` nullable specifically for this (07-CONTEXT.md D-05), and `PollingPlaceResolver::resolveAutomated()` already defaults its `$resolvedVia` parameter to `'reconciliation'`. Audit reports distinguish automated from manual/interactive changes via the `resolved_via` column, not via a fake user identity.

### Schedule, Batch Size & Captcha Budget (RECON-04)
- **D-05:** The job runs **hourly** via `Schedule::command(...)->hourly()->withoutOverlapping($expiresAt)` in `routes/console.php` — same style as the existing `birthday:dispatch-webhooks` entry (which already uses `->withoutOverlapping()`).
- **D-06:** Processes up to **50 voters per run**. With 24 runs/day, this bounds worst-case captcha spend to **~500 voters/day** system-wide (not per-campaign) — a predictable, cheap ceiling given 2captcha's ~$1-3/1000-solve pricing confirmed in Phase 9.
- **D-07:** A circuit breaker independent of the per-run cap: if the live source's reachability check fails (or the first few attempts in a run all fail with `source_unreachable`-equivalent errors), the run should skip remaining voters for that run rather than attempting all 50 against a confirmed-down source — exact circuit-breaker mechanics are Claude's discretion (e.g., check `isLiveReachable()` once per run before the loop, matching the existing `attemptLiveAutomated()` pattern which already does this per-attempt).

### Terminal / Exhaustion State (RECON-05)
- **D-08:** A voter reaches a terminal "exhausted" state after **5 consecutive failed live reconciliation attempts** (i.e., 5 job runs in a row where this voter's live attempt did not succeed — the counter resets to 0 the moment a live attempt succeeds for that voter). Once exhausted, the job skips that voter on future runs (no more live attempts spent on them) until something resets the counter (e.g., a future manual "Actualizar" force-refresh from Phase 10, which is unaffected by this — it's a human-initiated action, not this job's automated cascade).
- **D-09:** Two new columns on `voters`: `reconciliation_attempts` (integer, default 0, increments on each failed automated live attempt, resets to 0 on success) and `reconciliation_exhausted_at` (nullable timestamp, set once attempts hit 5, checked by the job's query to skip already-exhausted voters). New migration required — this is additive schema, not a change to Phase 7's existing `polling_place_source`/`polling_place_resolved_at`/`polling_place_resolutions` schema.

### Lock / Stuck-Run Protection (RECON-06)
- **D-10:** `withoutOverlapping()` carries an explicit expiry of **10 minutes** — sized with margin above the worst-case runtime of a 50-voter run (each attempt backs off up to ~1.6s per `attemptLiveAutomated()`'s existing backoff array, plus captcha solve time if a full live round-trip is attempted; 10 minutes is comfortably above any realistic single-run duration without leaving a truly stuck run frozen for hours).

### Claude's Discretion
- Exact mechanics of the per-run circuit breaker (D-07).
- Whether the wsp HTML parser lives in the Python service or the PHP service (D-03).
- Exact query shape for selecting "eligible for reconciliation" voters (fallback-sourced, not exhausted, respecting D-06's 50-per-run cap) — follow `FinalizeElectionEvent`'s `chunkById` pattern where applicable, though a bounded `limit(50)` is likely more direct here than a full chunked scan.
- Command/job naming conventions — follow existing style (e.g., `census:reconcile-live` per prior STACK.md research, or similar).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing resolver & adapter code this phase builds on
- `app/Services/PollingPlaceResolver.php` — `resolveAutomated()` (already defaults `resolvedVia: 'reconciliation'`), `attemptLiveAutomated()` (already calls `isReachable()` before every attempt, already has a 5-step backoff array), `isLiveReachable()`. This phase's job calls `resolveAutomated()` directly per voter — no resolver changes needed beyond what D-01/D-02 require in the adapter layer.
- `app/Services/RegistraduriaService.php` — `startLookup()`/`getResult()`/`isReachable()`. `isReachable()` probes `config('services.registraduria.probe_url')` (D-01 fixes this). `getResult()`'s docblock contract (`puesto_nombre`, `puesto_codigo`, `zona_codigo`, `mesa_numero`, `departamento`, `municipio`, `direccion`) is what D-02's parser must produce.
- `config/services.php` lines ~45-48 — `registraduria.url`, `registraduria.live_enabled`, `registraduria.probe_url` config keys; `.env`'s `REGISTRADURIA_PROBE_URL` currently `https://apiweb-eleccionescolombia.infovotantes.com` (dead, per D-01).
- `registraduria-service/app.py` — Phase 9's rewritten flow. `_lookup_async()`'s success branch (`outcome == "success"`) currently only sets `data={"raw_message_html": pp_html}` — this is the exact spot D-02's HTML parsing needs to enrich (if parsing happens Python-side) or where the raw HTML is exposed to PHP (if parsing happens PHP-side).
- `.planning/phases/09-live-source-feasibility-spike/09-SPIKE-RESULTS.md` — the only captured (truncated) sample of a real wsp success response; confirms an `id="consulta"` HTML table exists but not its full column structure.
- `.planning/phases/09-live-source-feasibility-spike/09-RESEARCH.md` — full verified request/response contract for wsp (sitekey/token extraction, AJAX submission, WAF-block detection) — still accurate, only the success-body's *internal* HTML structure is unmapped.

### Schema this phase extends
- `.planning/phases/07-source-flag-schema-resolution-audit-trail/07-CONTEXT.md` D-05 — `polling_place_resolutions.resolved_by` nullable + `nullOnDelete`, explicitly built to tolerate this phase's headless writes (D-04 relies on this).
- `app/Models/PollingPlaceResolution.php` — has a `resolved_via` scope already (`scopeResolvedVia` or similar, confirmed present) — D-04's audit distinction uses this existing column/scope, no model changes needed for that part.
- `database/migrations/2026_07_24_130002_create_polling_place_resolutions_table.php` — reference for column types/conventions if D-09's new `voters` columns need a similarly-styled migration.

### Job/scheduling patterns to follow
- `app/Jobs/FinalizeElectionEvent.php` — the house pattern: `ShouldQueue`, `chunkById(500, ...)`, dotted `Log::info/warning` events, `failed(\Throwable $e)` hook. Use this shape for the reconciliation job (adapted for D-06's 50-per-run cap rather than a full chunked scan).
- `routes/console.php` — `Schedule::command('birthday:dispatch-webhooks')->everyMinute()->withoutOverlapping()` is the exact existing precedent for D-05/D-10's scheduling + lock-expiry pattern (adjust to `->hourly()->withoutOverlapping($expiresAt)`).
- `.planning/research/STACK.md` §"Reconciliation job (matches FinalizeElectionEvent)" (from Phase 9's roadmap-era research) — already recommends this exact `Schedule::command()` + `ShouldQueue` approach; still valid.

### Prior-phase decisions this must not contradict
- `.planning/phases/09-live-source-feasibility-spike/09-CONTEXT.md` D-05 — production wiring was explicitly deferred as "a future phase/quick-task"; this phase (11) is that future phase, by this session's explicit decision (see Implementation Decisions above), not a violation of D-05's deferral.
- `.planning/phases/08-resilient-pollingplaceresolver-service/08-CONTEXT.md` D-13 — the no-downgrade guard (source precedence `live > db_reconstruction > snapshot > manual`) already applies automatically inside `resolveAutomated()`'s call to `persist()` — this phase's job does not need to reimplement that guard, just call the existing method.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `PollingPlaceResolver::resolveAutomated(string $cedula, Voter $voter, string $resolvedVia = 'reconciliation')` — already the exact entry point this phase's job calls per voter. Already campaign-safe (takes the `Voter` model directly, no `CampaignContext` dependency) — RECON-02 is satisfied by construction as long as the job's voter-selection query itself scopes by `$voter->campaign_id` per row (not by an ambient active-campaign filter).
- `attemptLiveAutomated()`'s existing 5-step backoff array (`[200, 400, 800, 1200, 1600]` ms) and give-up-on-`waiting_captcha` logic already satisfy "never blocks" (LIVE-03, reused here) — no changes needed for RECON-04's bounded-attempt behavior at the per-voter level, only the per-run/per-day ceiling (D-06) is new.

### Established Patterns
- Dotted structured log events (`election_event.finalize.started`, `...completed`, `...skipped_*`) — follow this exact naming convention for the reconciliation job's own log events.
- `chunkById()` for large voter scans in `FinalizeElectionEvent` — likely overkill for a capped 50-per-run query (D-06), a plain `limit(50)` ordered query is probably more direct, but confirm against `FinalizeElectionEvent`'s style during planning.

### Integration Points
- `routes/console.php` (new `Schedule::command()` entry), a new `app/Console/Commands/*.php` command (thin, dispatches the job per Laravel convention already used for `birthday:dispatch-webhooks`), a new `app/Jobs/*.php` job class, a new migration for `voters.reconciliation_attempts`/`reconciliation_exhausted_at` (D-09), and either `registraduria-service/app.py` or `app/Services/RegistraduriaService.php` for the HTML parser (D-02/D-03).

</code_context>

<specifics>
## Specific Ideas

- The wiring fix (D-01/D-02) is being done as part of this phase specifically because leaving it unfixed would make the entire phase's job a no-op on the one thing it's supposed to do (upgrade voters to live) — this was surfaced and explicitly confirmed with the user, not assumed.
- Research must budget at least one fresh live attempt against wsp to capture the FULL (untruncated) HTML response structure before the parser (D-02) can be written with confidence — the existing Phase 9 log only has truncated samples.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. (The wiring work, initially assumed to be a separate future phase/quick-task per Phase 9's D-05, was explicitly pulled into this phase's scope after discovering the reachability-probe and HTML-parsing gaps — see Implementation Decisions above.)

</deferred>

---

*Phase: 11-scheduled-reconciliation-job*
*Context gathered: 2026-07-26*
