---
phase: 11-scheduled-reconciliation-job
plan: 01
subsystem: infra
tags: [http-client, registraduria, wsp, fixture, laravel-http]

# Dependency graph
requires:
  - phase: 09-live-source-feasibility-spike
    provides: "GO verdict on wsp.registraduria.gov.co (29/30 real 2captcha attempts succeeded) and known-good test cedulas (1102812122, 1102815878, 64552231)"
  - phase: 08-resilient-pollingplaceresolver-service
    provides: "PollingPlaceResolver::attemptLiveAutomated() which calls RegistraduriaService::isReachable() before every automated attempt"
provides:
  - "isReachable() correctly probes the real wsp.registraduria.gov.co endpoint via GET (was always false before this plan due to dead probe URL + wrong HTTP verb)"
  - "A real, untruncated wsp #consulta success HTML fixture (tests/fixtures/registraduria/consulta-sample.html) documenting the exact table structure: NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN, MESA columns"
affects: [11-02-html-parser, 11-03-reconciliation-job, 11-04-scheduling]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Http::get() reachability probe with connectTimeout/timeout/withoutRedirecting (unchanged from existing pattern, verb fixed)"]

key-files:
  created:
    - tests/fixtures/registraduria/consulta-sample.html
  modified:
    - app/Services/RegistraduriaService.php
    - config/services.php
    - .env
    - tests/Feature/Services/RegistraduriaServiceReachabilityTest.php

key-decisions:
  - "isReachable() changed from ->head() to ->get() since HEAD returns HTTP 500 on wsp.registraduria.gov.co every time while GET returns 200 with the real form page"
  - "probe_url default (config + .env) corrected from dead apiweb-eleccionescolombia.infovotantes.com to https://wsp.registraduria.gov.co/censo/consultar/"
  - "Fixture captured on the first real 2captcha-budgeted attempt (cedula 1102812122) — no retries needed"

patterns-established:
  - "New assertSent-based test locks in HTTP verb behavior for reachability probes — future probe changes must not silently regress the verb"

requirements-completed: []  # RECON-01 intentionally NOT marked complete despite being listed in this plan's frontmatter requirements — see Decisions Made below

# Metrics
duration: 10min
completed: 2026-07-26
---

# Phase 11 Plan 01: Fix wsp Reachability Probe + Capture Real HTML Fixture Summary

**Fixed `isReachable()`'s dead HEAD-based probe to use GET against the corrected wsp.registraduria.gov.co URL, and captured one real untruncated wsp success HTML response for Plan 11-02's parser**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-07-26T14:07:29Z
- **Completed:** 2026-07-26T14:15:40Z
- **Tasks:** 2
- **Files modified:** 4 modified, 1 created

## Accomplishments
- `RegistraduriaService::isReachable()` now issues a `GET` (not `HEAD`) against the corrected `wsp.registraduria.gov.co/censo/consultar/` probe URL — previously always returned `false` due to a combination of a dead probe domain and `HEAD` returning HTTP 500 on the real endpoint (`GET` returns 200).
- `config/services.php` and `.env` probe URL defaults corrected to the real, live wsp endpoint.
- New reachability test (`assertSent` on HTTP verb) locks in GET-vs-HEAD behavior so this regression class can't recur silently.
- Spent exactly one real 2captcha-budgeted lookup against the live wsp site (cedula `1102812122`, a known-good cedula from Phase 9's spike) and captured the full, untruncated `#consulta` success HTML — 962 bytes, revealing the complete table structure (columns: NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN, MESA) that Phase 9's truncated ~200-char samples never showed.

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix isReachable() to GET the corrected wsp probe URL (D-01)** - `7cbf239` (fix)
2. **Task 2: Capture one real, untruncated wsp success HTML response (D-02 prerequisite)** - `880013d` (test)

_Note: no plan-metadata commit yet — created after this SUMMARY per execute-plan protocol._

## Files Created/Modified
- `app/Services/RegistraduriaService.php` - `isReachable()` now uses `Http::get()` instead of `->head()`
- `config/services.php` - `registraduria.probe_url` fallback default corrected to `https://wsp.registraduria.gov.co/censo/consultar/`
- `.env` - `REGISTRADURIA_PROBE_URL` live value corrected to match (gitignored, not committed)
- `tests/Feature/Services/RegistraduriaServiceReachabilityTest.php` - added a GET-vs-HEAD `assertSent` test; 4 existing tests untouched
- `tests/fixtures/registraduria/consulta-sample.html` - real, complete wsp `#consulta` success HTML fixture (962 bytes) for Plan 11-02

## Decisions Made
- Kept `connectTimeout(2)->timeout(3)->withoutRedirecting()` unchanged on the probe call — verified real wsp response times (~0.15-0.20s) are well within these limits, so only the HTTP verb needed to change.
- Used the first known-good cedula (`1102812122`) from Phase 9's spike list for the live capture; it succeeded on the first attempt (no retries against `1102815878` or `64552231` were needed).
- Fixture written via `python3 -c "import json..."` full-fidelity extraction from the raw curl JSON response (not copy-pasted from truncated terminal output), per the plan's explicit instruction to avoid a repeat of Phase 9's truncation issue.
- **RECON-01 intentionally NOT marked complete in REQUIREMENTS.md**, despite being listed in this plan's frontmatter `requirements` field. RECON-01's actual claim ("A scheduled job automatically re-attempts live lookup... and upgrades them when successful") requires the scheduled job itself, which this plan does not build — it only fixes the reachability probe and captures an HTML fixture, both prerequisites for later plans (11-02's parser, and the job/scheduling plans after it). Deferred requirement sign-off to phase completion, matching the precedent set in Phase 10 Plan 01 for requirements split across multiple plans in a phase.

## Deviations from Plan

None - plan executed exactly as written. The Python Flask service (`registraduria-service/app.py`, port 5757) was already running from a prior session, so no restart was needed.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required. (The `.env` change was to an already-configured, gitignored local value; no new secrets introduced.)

## Next Phase Readiness
- `isReachable()` now correctly reports the real wsp.registraduria.gov.co up/down state, unblocking `PollingPlaceResolver::attemptLiveAutomated()`'s live tier for Plan 11-03/11-04's reconciliation job.
- `tests/fixtures/registraduria/consulta-sample.html` gives Plan 11-02 a real, complete HTML sample to parse against instead of a guessed schema — no blockers for the next plan.

---
*Phase: 11-scheduled-reconciliation-job*
*Completed: 2026-07-26*

## Self-Check: PASSED

All claimed files exist on disk and both task commits (`7cbf239`, `880013d`) are present in git history.
