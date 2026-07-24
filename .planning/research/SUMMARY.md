# Project Research Summary

**Project:** SIGMA — Sistema Integral de Gestión y Análisis Electoral (v1.1 milestone)
**Domain:** Resilient dual-source polling-place lookup — live captcha-gated Registraduría scrape + national census-snapshot fallback + scheduled reconciliation
**Researched:** 2026-07-24
**Confidence:** MEDIUM-HIGH (Stack/Features/Architecture/Pitfalls are HIGH and codebase-grounded; the live-source captcha feasibility is the one genuine MEDIUM unknown)

## Executive Summary

This is a **brownfield resiliency milestone**, not a greenfield build. SIGMA already runs a 3-tier polling-place lookup cascade (`HasRegistraduriaPolling`: Redis cache → campaign-scoped `census_records` DB reconstruction → 2captcha live lookup). The v1.1 work does **not** invent fallback — it adds the four pieces the existing cascade is missing: (1) a **national** 216K-row census snapshot as a proper reference-data fallback tier, (2) a **persisted, queryable source flag** on each voter (`live`/`snapshot`/`manual`/`cache`) so results stop being anonymous, (3) an **auditable resolution history** modeled on the existing `ValidationHistory` pattern, and (4) a **scheduled reconciliation job** that silently upgrades snapshot-sourced records once the live source succeeds. The domain is a well-understood industry pattern (cache-aside + stale fallback + stale-while-revalidate); the real gap SIGMA is closing is **provenance and reconciliation**, not resiliency mechanics.

The recommended approach installs almost nothing new — every capability reuses infrastructure already in the codebase. Captcha stays on the existing Python/Playwright/2captcha microservice (Enterprise is a one-parameter delta: `enterprise=1` + a new sitekey). CSV import uses MySQL-native `LOAD DATA LOCAL INFILE` (or streaming `LazyCollection` + chunked upsert) into a **new** `national_census_records` table kept separate from campaign data. Reconciliation clones the test-protected `FinalizeElectionEvent` queued-job pattern and registers via `Schedule::job()->withoutOverlapping()` in `routes/console.php`. The architectural keystone is extracting the cascade out of the Filament trait into a new `PollingPlaceResolver` service so both the interactive UI and the headless job express the fallback order exactly once.

The **single highest risk** is whether Registraduría's `wsp.registraduria.gov.co` endpoint will actually *accept* a solved reCAPTCHA Enterprise token — Enterprise is server-side risk-score-based, so a valid token is necessary but not sufficient (the backend can still reject it on IP/behavioral signals). This is unresolved by documentation and must be settled by a **time-boxed feasibility spike** before committing to the live-source path. The mitigating design: the census snapshot makes this risk acceptable — if the spike fails, the snapshot becomes the primary source and the milestone still delivers (fallback + provenance + scheduled retry are the resilient core; the live source is upside). Dedicated pitfalls research (11 pitfalls, full detail in `PITFALLS.md`) surfaced a second-highest risk cluster around the reconciliation job specifically: it runs headless with no natural `CampaignContext` or human actor for the audit trail, it can hang indefinitely on a captcha step it can't complete without a person, and — unless explicitly bounded — it can silently drain the 2captcha budget or freeze itself via a stale `withoutOverlapping()` lock during exactly the outage this milestone exists to survive.

## Key Findings

### Recommended Stack

Nothing new needs installing. All three capabilities (captcha, CSV import, reconciliation) are served by infrastructure already in production in this repo. The only genuinely new code is a captcha-*type* change in the Python service, a new import Artisan command, and a new queued job — no new dependencies, accounts, or frameworks. See `.planning/research/STACK.md`.

**Core technologies:**
- **2captcha reCAPTCHA Enterprise API** (existing vendor): solve the `wsp` checkbox — Enterprise is the same `userrecaptcha` flow already used, plus `enterprise=1` and a new sitekey (possibly `action`/`data-s`). ~$1–2.99/1k solves.
- **MySQL `LOAD DATA LOCAL INFILE`** (bundled): bulk-load the 216K-row snapshot into a staging table, then `INSERT … SELECT` join against seeded `polling_places` — orders of magnitude faster than Eloquent. (Architecture research offers streaming `LazyCollection` + chunked `upsert()` as the idempotent, re-runnable alternative.)
- **PHP `mbstring`/`iconv`** (bundled): one-shot ISO-8859-1 → UTF-8 conversion of the Latin-1 census file *before* MySQL sees it — avoids the "Malformed UTF-8" corruption on accented names. Never `utf8_decode/encode` (deprecated).
- **Laravel Scheduler + `ShouldQueue` job** (in production use): recurring reconciliation via `Schedule::job()->hourly()->withoutOverlapping()`, cloning the `FinalizeElectionEvent` pattern verbatim.

**What NOT to add:** a new queue/cron system, a new scraping framework (Puppeteer/Selenium/Scrapy), `maatwebsite/excel` for the national load (keep it for user-facing admin uploads), or a brand-new captcha vendor account.

### Expected Features

The dual-source result must be *trustworthy* — an operator on Day D must always know whether a polling place is authoritative or a best-effort guess. See `.planning/research/FEATURES.md`.

**Must have (table stakes, v1.1 core):**
- **National census snapshot import** — the fallback has nothing to attribute or reconcile without it.
- **Explicit source flag + resolved-at timestamp on voter** — the linchpin every other feature reads.
- **Source badge + freshness on the voter record** — provenance must reach the human, not just the DB.
- **Non-blocking fallback cascade** (live → snapshot, flagged) — the resiliency behavior itself.
- **Audit trail of source transitions** (reusing the `ValidationHistory` shape) — SIGMA's non-negotiable traceability bar.
- **Scheduled reconciliation job** (snapshot → live upgrade, no-op-safe until a live source exists) — the self-healing behavior named in the milestone goal.
- **Live-source feasibility spike** (`wsp.registraduria.gov.co`) — gates the *value* of the reconciliation job.

**Should have (competitive, v1.x — pick 1–2):**
- **Reconciliation-queue / stale-data widget** — turns the invisible job into an operator-visible health signal.
- **Manual re-check action** (per record) — one-click "Reconsultar Registraduría".
- **Filter/view of voters on fallback data** — Day-D readiness triage for coordinators.
- **Confidence/coverage state** (`snapshot-hit` vs `unresolved`) — prevents false confidence from snapshot coverage gaps.

**Defer (v2+):**
- Bulk re-check action (needs rate-limiting discipline against the paid live source).
- Per-record reconciliation status narrative ("still trying" vs "gave up").
- Automated snapshot-refresh pipeline (one-time import suffices for v1.1).

**Explicit anti-features:** treating snapshot data as authoritative / hiding the source; blocking the voter workflow on live reconciliation; real-time reconciliation on every page view (cost explosion); auto-overwriting manual corrections; aggressive retry of the dead endpoint; per-record notifications on silent upgrades (the milestone *wants* silent).

### Architecture Approach

Extract the cascade out of the Filament trait into a dedicated `PollingPlaceResolver` service, add the national snapshot as a new tier, and make the source flag a first-class persisted column so a background job can query it. Recommended resolver tier order: `cache → live → campaign census_records → national snapshot`. Keep national reference data (snapshot) architecturally next to `polling_places`, never inside campaign-scoped `census_records`. See `.planning/research/ARCHITECTURE.md`.

**Major components:**
1. **`PollingPlaceResolver`** (NEW service) — the single place the fallback order is expressed; returns a `PollingPlaceResolution` value object with the source flag; persists the voter flag + audit row. Serves both interactive and automated (headless) modes.
2. **`NationalCensusRecord`** (NEW model + `national_census_records` table, cédula-unique) — nationwide reference data, `polling_place_id` FK resolved at import time via the divipol join. **Separate** from campaign-scoped `census_records`.
3. **`voters.polling_place_source` + `polling_place_resolved_at`** (NEW columns, `PollingPlaceSource` enum cast) — the current, indexed, queryable flag the reconciliation job filters on.
4. **`polling_place_resolutions`** (NEW audit table, `ValidationHistory`-*shaped* but with nullable `resolved_by` since jobs are headless) — append-only resolution history.
5. **`ReconcileSnapshotPollingPlaces`** (NEW queued job, clones `FinalizeElectionEvent`) — bounded `->oldest('polling_place_resolved_at')->limit(N)` slice per hourly run to avoid stampeding the paid live source.
6. **`RegistraduriaService`** (KEEP AS-IS) — stays a pure single-responsibility live-source HTTP adapter; the resolver treats it as one tier.

### Critical Pitfalls

Full pitfalls research completed — 11 pitfalls documented with warning signs and phase mapping in `.planning/research/PITFALLS.md`. Top ones, ranked by blast radius:

1. **Fallback clobbers fresher data (no source precedence).** `fillPollingPlaceFields()` currently overwrites the polling place unconditionally; reusing it verbatim for the fallback lets a stale 2023 snapshot silently downgrade an already-`live`-flagged voter. *Avoid:* a non-null source flag written in the *same* statement as the polling-place write, plus a strict precedence guard (`live > db_reconstruction > snapshot_2023`) that never auto-downgrades.
2. **National snapshot table becomes a campaign-isolation leak.** SIGMA already had one real cross-campaign leak in a reassignment flow — same failure class if the snapshot's derived writes (`CensusRecord`, `PollingPlace::firstOrCreate`) inherit a wrong/absent `CampaignContext`. *Avoid:* keep the snapshot table strictly read-only/lookup-only; require an explicit A-vs-B campaign-isolation test on every derived write path.
3. **Reconciliation job runs headless with no `CampaignContext` and no human actor.** Two compounding risks: `fillPollingPlaceFields()` reads `CampaignContext::currentCampaignId()` (null/wrong inside a queued job — must derive `campaign_id` from `$voter->campaign_id` instead), and `ValidationHistory.validated_by` is a non-null FK (the job needs an explicit system-actor strategy decided *before* writing the job, not improvised).
4. **Job hangs on a captcha step it can't complete headlessly, and can flood the 2captcha budget doing so.** The live tier is currently part human-driven (modal + click); a naive automated retry either hangs waiting for a dispatch that never comes, or — if it completes — retries every snapshot voter every run with no circuit breaker, draining the captcha budget during exactly the outage this milestone exists to survive. *Avoid:* gate automated reconciliation on the feasibility spike's outcome; ship a circuit breaker + per-record backoff + per-run cap from day one.
5. **reCAPTCHA Enterprise token acceptance is score-based and not guaranteed even with a returned token.** Verified: 2captcha only returns coarse scores (~0.1/0.3/0.9), tokens live ~2 minutes, and any mismatch between the token-generation and final-request environment causes rejection. *Avoid:* the spike must classify outcomes explicitly (`success` / `denied_by_score` / `not_found` / `source_unreachable`), not treat "token received" as "lookup will succeed."
6. **Records reconciled forever / stale scheduler lock.** Two related traps: no terminal state means a permanently-unreachable live source causes every run to rescan the same never-resolvable records forever; and `Schedule::job()->withoutOverlapping()` defaults to a **24-hour lock hold** on a crashed/hung run (exactly the kind pitfall #4 produces), silently freezing reconciliation for a full day with no error surfaced. *Avoid:* explicit exhaustion state (`reverify_attempts >= MAX`) plus an explicit `withoutOverlapping($minutes)`/`expireAfter()` sized to the job's real max runtime.
7. **Jurisdiction/divipol code drift corrupts the existing dentro/fuera report.** If the 2023 snapshot's codes don't match the current `polling_places`/`divipole-nacional.json` seed (municipality merges, renamed/relocated puestos), a snapshot-sourced polling place can silently resolve to the wrong municipality — flipping a voter's dentro/fuera classification in the Phase 04.1 report. *Avoid:* validate snapshot codes against the current seed at import time and report the unmatched percentage; prefer code-based joins over name string-matching; propagate the source flag into the dentro/fuera report so stale classifications are visibly caveated.
8. **Latin-1 encoding corruption on import.** The census CSV is ISO-8859-1 (`LA PE\xd1ATA`, `CHOCHO`); loading it raw yields "Malformed UTF-8". *Avoid:* `mb_convert_encoding(..., 'UTF-8', 'ISO-8859-1')` / `iconv` before MySQL sees it.

## Implications for Roadmap

Dependencies here are strict: you cannot fall back to a snapshot that isn't imported, cannot flag a source without the schema, and cannot reconcile without both the flag (to query) and the resolver (to re-attempt). The build order below reflects that chain (from ARCHITECTURE.md's recommended sequence).

### Phase 1: National Census Snapshot Import
**Rationale:** Blocks everything — the fallback has nothing to read until this exists. Independent of the schema work, so it can run in parallel with Phase 2.
**Delivers:** `national_census_records` migration + `NationalCensusRecord` model + `php artisan census:import-national` (streaming/`LOAD DATA` load, Latin-1 → UTF-8 decode, divipol → `polling_place_id` join). Test: small fixture CSV asserts row count, FK resolution, encoding handling.
**Addresses:** "National census snapshot import" (P1 table stakes).
**Avoids:** Pitfall #2 (separate, read-only table — no campaign_id), #7 (validate divipol codes against the current `polling_places` seed, report unmatched %), and #8 (encoding corruption).

### Phase 2: Source-Flag Schema + Audit Table
**Rationale:** Must exist before the resolver can write a flag and before the job can query stale records. Parallelizable with Phase 1 (no data dependency).
**Delivers:** `voters.polling_place_source` + `polling_place_resolved_at` (+ `PollingPlaceSource` enum + cast + `$fillable`); `polling_place_resolutions` table + `PollingPlaceResolution` model (ValidationHistory-shaped, nullable `resolved_by`) + `Voter::pollingPlaceResolutions()` relation.
**Addresses:** "Explicit source flag + resolved-at", "Audit trail of source transitions" (P1).
**Implements:** Architecture components 3 and 4.

### Phase 3: PollingPlaceResolver Orchestrating Service
**Rationale:** The keystone — depends on Phase 1 (snapshot to read) and Phase 2 (flag/audit to write). Extracts the cascade out of `HasRegistraduriaPolling` so both UI and job share one fallback order.
**Delivers:** `app/Services/PollingPlaceResolver.php` returning a `PollingPlaceResolution` VO; trait refactored to delegate; interactive + automated modes. Test: table-driven Pest dataset over tiers (cache hit / live success / live-down→snapshot / total miss) asserting correct source flag + audit row written.
**Addresses:** "Non-blocking fallback cascade" (P1).
**Avoids:** Pitfall #1 (source precedence, no silent downgrade) and #4 (automated mode gives up on `waiting_captcha` rather than hanging).

### Phase 4: Scheduled Reconciliation Job
**Rationale:** Depends on Phase 2 (flag to query) and Phase 3 (resolver to re-attempt). Ships no-op-safe even if the live source is still down.
**Delivers:** `ReconcileSnapshotPollingPlaces` (clones `FinalizeElectionEvent`), deriving `campaign_id` from each `$voter->campaign_id` (never `CampaignContext`), an explicit system-actor strategy for the audit FK, bounded `->oldest()->limit(N)` slice with a circuit breaker + per-record backoff + terminal/exhaustion state, registered via `Schedule::job()->hourly()->withoutOverlapping($minutes)` with an explicit expiry. Direct job test mirroring the `FinalizeElectionEvent` test (snapshot→live flip + inverse + wrong-campaign-never-touched).
**Addresses:** "Scheduled reconciliation job" (P1).
**Avoids:** Pitfall #3 (campaign context + audit actor decided up front), #4 (bounded slice + circuit breaker, no budget stampede), and #6 (terminal state + explicit lock expiry, no infinite reprocessing or 24h stale-lock freeze).

### Phase 5 (parallel / non-blocking): Live-Source Feasibility Spike
**Rationale:** Does not block Phases 1–4 but *determines how effective Phase 4's automated live mode can be*. Sequence before or parallel to Phase 4, never after.
**Delivers:** A time-boxed spike that extracts the `wsp` Enterprise sitekey (+ `action`/`data-s` if present), adds `enterprise=1`, injects the token into the consulta form, and submits one real cédula end-to-end to confirm server-side acceptance. Documents the go/no-go for the live path.
**Addresses:** "Live source feasibility spike" (P1, gates job value).
**Avoids:** Pitfall #5 (proves acceptance before committing, with an explicit success/denied/not-found/unreachable classifier rather than treating a returned token as success).

### Phase 6 (v1.x, after validation): Operator Visibility
**Rationale:** Presentation/triage on top of the now-persisted flag — deferrable without weakening the trustworthy core.
**Delivers:** Source badge + freshness on the record (arguably pull into P1 core), reconciliation-queue/stale-data widget, fallback filter, manual re-check action.
**Addresses:** P2 differentiators.

### Phase Ordering Rationale
- **Dependency-driven:** import → schema → resolver → job is the strict chain from ARCHITECTURE.md; steps 1 and 2 parallelize.
- **Grouping by architecture boundary:** each phase lands one architecture component with its own test, matching the QUAL test-protection precedent.
- **Risk isolation:** the captcha unknown is quarantined in a parallel spike so the deterministic snapshot/flag/reconcile core is never blocked by it — the milestone delivers value whether or not the live source proves solvable.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 5 (feasibility spike):** the one genuine unknown — reCAPTCHA Enterprise token acceptance by `wsp` can only be answered by a live spike; treat as research-heavy.

Phases with standard patterns (skip research-phase):
- **Phase 1 (import):** well-documented `LOAD DATA`/streaming-upsert + existing `CensusImporter`/`PollingPlaceSeeder` precedents.
- **Phase 2 (schema):** direct mirror of `ValidationHistory` + `census_validated_at` conventions.
- **Phase 4 (job):** direct clone of the test-protected `FinalizeElectionEvent`.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | MEDIUM-HIGH | Import + scheduling HIGH (verified against repo); reCAPTCHA Enterprise *acceptance* is the honest MEDIUM unknown pending a spike. |
| Features | HIGH | Grounded in existing codebase patterns + verified industry resilience patterns; only the live-source viability is LOW (separate spike). |
| Architecture | HIGH | Every integration point names a real existing file/class; brownfield build order is dependency-verified. |
| Pitfalls | HIGH | 11 pitfalls documented with warning signs, phase mapping, and a "looks done but isn't" checklist in `PITFALLS.md`; MEDIUM specifically on Registraduría/Enterprise behavior (live endpoints dead, `wsp` unvalidated — same spike gate as Stack). |

**Overall confidence:** MEDIUM-HIGH — the deterministic core (snapshot import, source flag, audit, reconciliation scaffold) is HIGH and codebase-grounded, including a thorough pitfalls pass; the live-source captcha path is MEDIUM and explicitly gated on a spike.

### Gaps to Address
- **Live-source token acceptance:** unresolved by docs. Handle via the time-boxed Phase 5 spike; design so the milestone ships even if it fails (snapshot becomes primary).
- **Live-first vs. cost-first ordering:** the requirement says "live first, fall back to snapshot," but the existing interactive path is deliberately cost-*last* (live = paid). Confirm the interactive ordering with the client during planning; reconciliation is unambiguously live-first.
- **System-actor decision for reconciliation's audit trail:** must be made explicit before Phase 4 is planned in detail — either a seeded `system`/bot user passed as `validated_by`-equivalent, or a nullable FK + `validation_type='auto_reconciliation'` (Pitfall #3). Not yet decided; flag during roadmap approval or Phase 4 planning.
- **`local_infile` availability:** if `LOAD DATA LOCAL INFILE` can't be enabled in the target env, fall back to the streaming `LazyCollection` + chunked `upsert()` path (both documented).

## Sources

### Primary (HIGH confidence)
- SIGMA codebase — `HasRegistraduriaPolling`, `RegistraduriaService`, `RegistraduriaController`, `FinalizeElectionEvent`, `ValidationHistory`, `CensusRecord`/`CensusImporter`, `Voter`/`PollingPlace`, `PollingPlaceSeeder` + `divipole-nacional.json`, `censo_decoded_202310210734.csv` (216,528 rows, Latin-1 confirmed), `routes/console.php`, `.planning/PROJECT.md`.
- [2captcha reCAPTCHA Enterprise solver](https://2captcha.com/p/recaptcha_enterprise) — Enterprise support, `enterprise=1`, optional `action`/`data-s`, pricing, "same as v2/v3" integration.
- [league/csv CharsetConverter](https://csv.thephpleague.com/9.0/converter/charset/) + [PHP mb_convert_encoding](https://www.php.net/manual/en/function.mb-convert-encoding.php) — ISO-8859-1 → UTF-8 conversion.

### Secondary (MEDIUM confidence)
- [MojoAuth — reCAPTCHA v2/v3/Enterprise differences](https://mojoauth.com/blog/recaptcha-vs-captcha-versions-v2-v3-enterprise) — Enterprise is server-side risk-score-based (the core acceptance risk).
- [CapMonster Cloud — reCAPTCHA Enterprise](https://capmonster.cloud/en/blog/recaptcha-v2-vs-v3-vs-enterprise/) — viable alternative solver.
- [LOAD DATA INFILE in Laravel](https://medium.com/@techsolver/lightning-fast-laravel-csv-imports-with-load-data-infile-b403ec8bd532) — `local_infile` requirement.
- Resilience/SWR patterns: [Cache-Aside + Stale Fallback + Background Refresh](https://medium.com/@oshiryaeva/building-resilient-rest-api-integrations-cache-aside-stale-fallback-and-background-refresh-9028e5497dfb), [Retry/Circuit Breakers/Fallbacks](https://medium.com/@pearl.rathour33/resilience-mechanisms-in-api-clients-retry-logic-circuit-breakers-and-fallbacks-09d8f58569d2), [Data Freshness Boundaries](https://www.nilus.be/blog/data_freshness_boundaries_in_data_architecture/), [Queue-Based Exponential Backoff](https://dev.to/andreparis/queue-based-exponential-backoff-a-resilient-retry-pattern-for-distributed-systems-37f3).

### Tertiary (LOW confidence)
- [uCaptcha — Solving reCAPTCHA Enterprise guide](https://ucaptcha.net/blog/recaptcha-enterprise-guide/) — token-vs-acceptance distinction, needs validation via spike.

### Pitfalls-specific sources (see PITFALLS.md for full list)
- [2captcha — bypassing reCAPTCHA v3/Enterprise](https://2captcha.com/h/how-to-bypass-recaptcha-v3-enterprise), [2captcha reCAPTCHA v3 API](https://2captcha.com/api-docs/recaptcha-v3), [CapSolver — reCAPTCHA score explained](https://www.capsolver.com/blog/reCAPTCHA/recaptcha-score-explained) — MEDIUM: score behavior, ~2-min token TTL, environment-consistency requirement.
- [Google reCAPTCHA Enterprise assessment/score docs](https://docs.cloud.google.com/recaptcha/docs/interpret-assessment-website) — HIGH: official score/assessment model.
- [Laravel `withoutOverlapping` stale-lock issue](https://github.com/laravel/framework/issues/37060), [production scheduler failures write-up](https://mozex.dev/blog/17-5-laravel-scheduler-failures-that-only-show-up-in-production) — MEDIUM: verified 24h default lock-hold behavior on a crashed/hung run.

---
*Research completed: 2026-07-24*
*Ready for roadmap: yes*
