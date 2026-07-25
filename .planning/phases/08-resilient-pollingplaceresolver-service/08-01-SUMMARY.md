---
phase: 08-resilient-pollingplaceresolver-service
plan: 01
subsystem: api
tags: [laravel, http-client, pest, value-object, registraduria]

# Dependency graph
requires:
  - phase: 07-source-flag-schema-audit-trail
    provides: PollingPlaceSource enum and PollingPlaceResolution audit model this plan's VO must not collide with
provides:
  - LiveSourceAdapter interface (startLookup/getResult/isReachable contract for interchangeable live-source adapters)
  - RegistraduriaService::isReachable() — real reachability probe against the infovotantes host, independent of the always-200 Python microservice
  - services.registraduria.live_enabled kill switch and services.registraduria.probe_url config keys
  - PollingPlaceResolutionResult readonly value object (source/fields/pollingPlaceId/tableNumber)
affects: [08-02-resolver-core, 08-03-trait-wiring]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "LiveSourceAdapter interface as the seam for interchangeable live polling-place lookup sources (LIVE-01)"
    - "isReachable() as a cheap, kill-switch-gated HEAD probe against the real external host, decoupled from the async startLookup()/getResult() contract"

key-files:
  created:
    - app/Services/LiveSourceAdapter.php
    - app/Services/PollingPlaceResolutionResult.php
    - tests/Feature/Services/RegistraduriaServiceReachabilityTest.php
    - tests/Feature/Services/PollingPlaceResolutionResultTest.php
  modified:
    - app/Services/RegistraduriaService.php
    - config/services.php
    - .env
    - .env.example

key-decisions:
  - "isReachable() uses ->withoutRedirecting() before checking $response->redirect() — Guzzle's default redirect-following would otherwise chase a self-referential Location header into a 5-redirect ConnectionException, producing a false negative on real 3xx responses (auto-fixed, Rule 1)."

requirements-completed: [LIVE-01, LIVE-03]

# Metrics
duration: 15min
completed: 2026-07-25
---

# Phase 8 Plan 1: LiveSourceAdapter Contract & Reachability Probe Summary

**LiveSourceAdapter interface, a real HTTP reachability probe on RegistraduriaService (kill-switch gated, decoupled from the always-200 Python microservice), and the PollingPlaceResolutionResult VO that Plans 08-02/08-03 will build against**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-07-25 (session start)
- **Completed:** 2026-07-25
- **Tasks:** 2
- **Files modified:** 8 (4 created, 4 modified)

## Accomplishments
- `LiveSourceAdapter` interface defined as the seam for interchangeable live-source adapters (LIVE-01)
- `RegistraduriaService::isReachable()` added — a cheap, kill-switch-gated probe against the actual `apiweb-eleccionescolombia.infovotantes.com` host, never touching the always-200 Python microservice's `startLookup()` (LIVE-03's "never blocks" guard)
- `REGISTRADURIA_LIVE_ENABLED` kill switch and `REGISTRADURIA_PROBE_URL` config wired through `config/services.php`, `.env`, and `.env.example`
- `PollingPlaceResolutionResult` readonly VO created, deliberately named to avoid collision with the existing `App\Models\PollingPlaceResolution` audit model, with dedicated constructor-shape test coverage

## Task Commits

Each task was committed atomically:

1. **Task 1: LiveSourceAdapter interface + RegistraduriaService reachability probe + kill switch config** - `2e66463` (feat)
2. **Task 2: PollingPlaceResolutionResult value object** - `4171418` (feat)

_Note: Task 1 included an inline TDD-discovery fix (redirect-following bug) folded into the same commit — see Deviations below._

## Files Created/Modified
- `app/Services/LiveSourceAdapter.php` - New interface: `startLookup(string): string`, `getResult(string): array`, `isReachable(): bool`
- `app/Services/RegistraduriaService.php` - Now `implements LiveSourceAdapter`; added `isReachable()` gated by the kill switch, probing `services.registraduria.probe_url` with `withoutRedirecting()` + a 2s connect / 3s total timeout
- `config/services.php` - Added `registraduria.live_enabled` and `registraduria.probe_url` config keys
- `.env` / `.env.example` - Added `REGISTRADURIA_LIVE_ENABLED=true` and `REGISTRADURIA_PROBE_URL=https://apiweb-eleccionescolombia.infovotantes.com`
- `app/Services/PollingPlaceResolutionResult.php` - New `final readonly class` VO: `source` (`PollingPlaceSource`), `fields` (`array`), `pollingPlaceId` (`?int`), `tableNumber` (`?string`)
- `tests/Feature/Services/RegistraduriaServiceReachabilityTest.php` - 4 tests: kill-switch-off (no HTTP call), success, redirect, connection failure
- `tests/Feature/Services/PollingPlaceResolutionResultTest.php` - 3 tests: constructor shape, defaults, readonly-ness via `ReflectionClass::isReadOnly()`

## Decisions Made
- Added `->withoutRedirecting()` to the `isReachable()` HTTP call before evaluating `$response->redirect()` (see Deviations — Rule 1 auto-fix).
- Kept the plan's `<interfaces>` block code for `PollingPlaceResolutionResult` verbatim, including its docblock mention of `App\Models\PollingPlaceResolution` for context — see Deviations for the resulting acceptance-criteria discrepancy.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `isReachable()` followed a self-referential redirect into a false negative**
- **Found during:** Task 1 (writing and running the reachability test for the 3xx-redirect case)
- **Issue:** The plan's specified implementation called `Http::head()` without disabling redirect-following. Guzzle's default HTTP client follows redirects automatically; the test's faked 302 response pointed its `Location` header back at the same probe URL, so the client chased it 5 times and threw `Illuminate\Http\Client\ConnectionException: Will not follow more than 5 redirects.` — caught by the existing `catch (ConnectionException)` block and silently returning `false` instead of `true` for a legitimately-reachable (redirecting) host.
- **Fix:** Added `->withoutRedirecting()` to the HTTP client chain before `->head()`, so the raw 3xx status is returned directly and evaluated by `$response->redirect()` without the client attempting to follow it.
- **Files modified:** `app/Services/RegistraduriaService.php`
- **Verification:** `php artisan test tests/Feature/Services/RegistraduriaServiceReachabilityTest.php` — all 4 tests pass, including the redirect case.
- **Committed in:** `2e66463` (part of Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Necessary for correctness — without the fix, `isReachable()` would report `false` for any gov.co-style host that legitimately redirects on a HEAD request, defeating the reachability check's purpose. No scope creep.

## Issues Encountered

**Acceptance-criteria / interface-block inconsistency in Task 2 (not a code defect):** The plan's Task 2 acceptance criteria state the file "does NOT contain the string `App\Models\PollingPlaceResolution`", but the plan's own `<interfaces>` reference block (which Task 2's action explicitly instructs to copy verbatim) includes that exact string inside the constructor's PHPDoc, explaining why `pollingPlaceId` maps to the audit model's column. `app/Services/PollingPlaceResolutionResult.php` was written matching the plan's interface block exactly (needed verbatim by Plans 08-02/08-03), so a literal grep for that string still matches — inside a comment only. There is no `use App\Models\PollingPlaceResolution;` import, no class alias, and no runtime reference to the Model class anywhere in the file; the actual intent of the criterion (no import/alias collision) is fully satisfied and verified by `ReflectionClass` in the test suite. No code change made; flagging here for plan-authoring awareness.

## User Setup Required

None - no external service configuration required. `REGISTRADURIA_LIVE_ENABLED` and `REGISTRADURIA_PROBE_URL` were added to both `.env` and `.env.example` with working defaults; no manual action needed.

## Next Phase Readiness
- `LiveSourceAdapter` and `PollingPlaceResolutionResult` are both ready for Plan 08-02 (resolver core) to consume directly.
- `RegistraduriaService::isReachable()` gives the resolver a safe, non-blocking way to decide whether to attempt a live lookup at all, satisfying LIVE-03's "never blocks" guard at its source.
- No blockers for 08-02/08-03.

---
*Phase: 08-resilient-pollingplaceresolver-service*
*Completed: 2026-07-25*

## Self-Check: PASSED

All 6 claimed files found on disk. Both task commits (`2e66463`, `4171418`) found in git history.
