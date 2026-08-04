---
phase: 260804-gl5
plan: 01
subsystem: api
tags: [pest, 2captcha, registraduria, resilience, cascade]

# Dependency graph
requires:
  - phase: 260804-84g
    provides: LIVE_POLL_ATTEMPTS/LIVE_POLL_INTERVAL_MS widened poll window (~40s/adapter) and shared reconciliation counters
provides:
  - "resolveAutomated() escalates to the next live adapter ONLY on isReachable()===false, never on a reachable adapter's timeout/error/waiting_captcha/not_found"
  - "attemptLiveAutomated() blank-puesto_nombre guard: any status=done result with a blank puesto_nombre is treated as non-success (never persisted as LIVE)"
  - "registraduria-service/app.py's infovotantes flow explicitly classifies not-found as outcome=not_found, data=None (mirrors wsp/censo)"
affects: [reconciliation, live-source-adapters, 2captcha-cost]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Automated/headless live cascade escalates strictly on adapter-availability (isReachable), never on query outcome — the single isReachable() check now lives only in resolveAutomated()'s loop, not duplicated inside attemptLiveAutomated()"
    - "A status=done payload with a blank primary field (puesto_nombre) is treated identically to not_found/error at the PHP layer, independent of whether the upstream adapter classifies it correctly"

key-files:
  created: []
  modified:
    - app/Services/PollingPlaceResolver.php
    - tests/Feature/Services/PollingPlaceResolverTest.php
    - registraduria-service/app.py

key-decisions:
  - "attemptLiveAutomated() no longer performs its own isReachable() check; resolveAutomated()'s loop is now the sole source of truth for the escalation rule (avoids a duplicate check and keeps the rule in one place)"
  - "The PHP blank-puesto_nombre guard is intentionally kept even after fixing the Python root cause — defense in depth against any current or future adapter making the same class of mistake"
  - "No Python test infrastructure was introduced for app.py's change (registraduria-service/ has no tests/pytest.ini/conftest.py and none is added solely for this fix); verified by structural comparison against the existing wsp/censo not_found patterns, backed by the independently-tested PHP-side guard"

requirements-completed: []

# Metrics
duration: 15min
completed: 2026-08-04
---

# Phase 260804-gl5: Cascada de sitios en vivo debe escalar solo por disponibilidad Summary

**Automated Registraduría live cascade now escalates to the next adapter only when unreachable (never on a reachable adapter's timeout/error/not_found), and a `done` status with a blank `puesto_nombre` is never persisted as a LIVE success — stopping tripled 2captcha spend per cédula and a data-corrupting false-positive classification.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-08-04
- **Tasks:** 2/2
- **Files modified:** 3

## Accomplishments
- `PollingPlaceResolver::resolveAutomated()`'s live-adapter loop now checks `isReachable()` explicitly as the ONLY escalation gate; any non-success from a reachable adapter (timeout/exhausted-polls, `error`, `waiting_captcha`) breaks the loop and falls through to the free national snapshot tier instead of trying a second/third live/2captcha adapter.
- `attemptLiveAutomated()` gained a guard: a `status=done` result whose `puesto_nombre` is blank is treated as non-success (same as `not_found`), regardless of which adapter produced it.
- `registraduria-service/app.py`'s `_lookup_infovotantes_async()` now explicitly classifies a cédula-not-found API response as `outcome="not_found", data=None`, matching the existing `wsp`/`censo` flow patterns exactly, instead of emitting a 7-key dict of empty strings under a bare `status="done"`.
- 3 new Pest tests added, all passing; all 33 pre-existing tests in the file continue to pass unchanged.

## Task Commits

Each task was committed atomically:

1. **Task 1: Stop the automated cascade escalating on query failure — only escalate on unreachable** - `69346d5` (fix)
2. **Task 2: Fix infovotantes not_found misclassification (Python + PHP defense-in-depth guard)** - `955aee6` (fix)

_Note: tasks were implemented and verified together per-task (test + implementation in a single commit each) rather than separate RED/GREEN/REFACTOR commits, since the exact fix shape was already fully specified and confirmed in the plan — no exploratory implementation was needed between test and code changes._

## Files Created/Modified
- `app/Services/PollingPlaceResolver.php` - `resolveAutomated()`'s live loop now gates escalation solely on `isReachable()`; `attemptLiveAutomated()` no longer duplicates that check and gained the blank-`puesto_nombre` guard
- `tests/Feature/Services/PollingPlaceResolverTest.php` - 3 new tests (32, 33, 34): no second-adapter escalation on timeout, no second-adapter escalation on error, blank-`puesto_nombre` guard falls back to snapshot
- `registraduria-service/app.py` - `_lookup_infovotantes_async()` explicitly classifies not-found (`outcome=not_found, data=None`) mirroring `wsp`/`censo`; `lookup_infovotantes()`'s session init gained `outcome`/`message`/`raw_response` keys to match the other two flows' session shape

## Decisions Made
- Kept the PHP-side blank-`puesto_nombre` guard as a permanent defense-in-depth layer, not a temporary patch — it protects the system against the exact same bug class from any live adapter (current or future), independent of whether any one Python flow correctly classifies not-found.
- Removed the internal `isReachable()` check from `attemptLiveAutomated()` entirely rather than leaving it as a redundant safety net — `resolveAutomated()`'s loop is now unambiguously the single place that decides escalation, per the plan's explicit intent.

## Deviations from Plan

None - plan executed exactly as written. Both diagnoses were fully confirmed before this quick task began; only implementation was required.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required. This is backend service/job logic only, fully exercised by fake `LiveSourceAdapter` instances in Pest (no real HTTP, 2captcha, or wall-clock delay). Per the user's standing preference, browser verification is reserved for UI-facing changes — not applicable here. The `registraduria-service/app.py` change requires no new deploy action beyond the project's existing Dokploy redeploy process whenever that service's code changes (not performed as part of this quick task; the Python microservice is deployed separately from the Laravel app).

## Next Phase Readiness

Both confirmed bugs are fixed and test-covered. No blockers. The existing STATE.md-documented `startLiveLookup()` fallback gap (interactive path doesn't catch-and-continue on a `startLookup()` exception) remains a separate, previously-documented issue, unaffected by this task's scope (which is limited to the automated/headless `resolveAutomated()` cascade).

---
*Phase: 260804-gl5*
*Completed: 2026-08-04*

## Self-Check: PASSED

- FOUND: app/Services/PollingPlaceResolver.php
- FOUND: tests/Feature/Services/PollingPlaceResolverTest.php
- FOUND: registraduria-service/app.py
- FOUND commit: 69346d5
- FOUND commit: 955aee6
