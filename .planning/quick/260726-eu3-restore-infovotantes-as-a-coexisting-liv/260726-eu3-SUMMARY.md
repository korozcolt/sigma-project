---
phase: quick
plan: 260726-eu3
subsystem: api
tags: [flask, playwright, 2captcha, http-client, laravel, live-source-adapter, pest]

requires:
  - phase: 08-resilient-pollingplaceresolver-service
    provides: LiveSourceAdapter interface + PollingPlaceResolver cascade (isReachable/startLookup/getResult, liveAdapters iterable)
provides:
  - Second LiveSourceAdapter (InfovotantesService) coexisting with RegistraduriaService, registered ahead of it
  - registraduria-service/app.py serving two independent lookup flows (wsp + infovotantes) from one Flask process
affects: [pollingplaceresolver, registraduria-service, live-source-adapters]

tech-stack:
  added: []
  patterns:
    - "Multiple LiveSourceAdapter implementations coexist behind PollingPlaceResolver's ordered liveAdapters iterable — array order IS the priority, no resolver code changes needed to reorder/add adapters"
    - "Python microservice serves multiple independent lookup flows from one Flask process/shared sessions dict, differentiated only by route path"

key-files:
  created:
    - app/Services/InfovotantesService.php
    - tests/Feature/Services/InfovotantesServiceTest.php
    - tests/Feature/Services/PollingPlaceResolverPriorityTest.php
  modified:
    - registraduria-service/app.py
    - app/Providers/AppServiceProvider.php
    - config/services.php
    - .env.example
    - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
    - app/Services/PollingPlaceResolver.php
    - tests/Feature/Services/PollingPlaceResolverTest.php

key-decisions:
  - "InfovotantesService registered FIRST in liveAdapters (ahead of RegistraduriaService) per explicit user priority — infovotantes is tried first when reachable, wsp is the fallback"
  - "Python flow restored verbatim from pre-Phase-9 commit ac1dd5a as an ADDITIVE second route (/lookup/infovotantes), sharing the existing sessions dict/lock and /result/<session_id> route; the wsp /lookup route and its functions are byte-for-byte unchanged (git diff confirms zero deletions)"
  - "InfovotantesService.getResult() is a pure pass-through of the Python service's JSON (no HTML parsing needed, unlike RegistraduriaService), since the infovotantes API already returns structured fields"
  - "PollingPlaceResolver::startLiveLookup() fixed (post-review) to pick the first REACHABLE adapter instead of blindly the first configured adapter — matches isLiveReachable()/attemptLiveAutomated()'s existing isReachable()-gated pattern"

requirements-completed: []

duration: 50min
completed: 2026-07-26
---

# Quick Task 260726-eu3: Restore Infovotantes as a Coexisting Live Source Summary

**Restored the pre-Phase-9 2captcha + Playwright-fetch infovotantes flow as a second LiveSourceAdapter (InfovotantesService), registered ahead of RegistraduriaService (wsp) in PollingPlaceResolver's priority-ordered liveAdapters list — purely additive, wsp's Phase 9/11 behavior is completely unchanged.**

## Performance

- **Duration:** ~50 min (includes worktree staleness repair: fast-forward merge + composer install + .env copy, plus a post-execution code-review fix)
- **Tasks:** 3 (plus 1 auto-fixed regression, plus 1 post-review bug fix)
- **Files modified:** 10 (3 created, 7 modified)

## Accomplishments

- `registraduria-service/app.py` now serves both flows from the same Flask process: `/lookup` (wsp, untouched) and the new `/lookup/infovotantes` route, sharing the sessions dict/lock and `/result/<session_id>`.
- New `App\Services\InfovotantesService` implements `LiveSourceAdapter`, mirroring `RegistraduriaService`'s structure but with a pass-through `getResult()` (no HTML parsing) and its own independent `isReachable()` probe against the eleccionescolombia/infovotantes domain.
- `AppServiceProvider`'s `liveAdapters` binding is now `[InfovotantesService, RegistraduriaService]` — the entire priority mechanism, no `PollingPlaceResolver` code changes.
- New `services.infovotantes` config block + `.env.example` entries, fully independent from the existing `services.registraduria` block.
- Pest coverage: `InfovotantesServiceTest` (isReachable/startLookup/getResult) and `PollingPlaceResolverPriorityTest` (proves infovotantes tried first when reachable — wsp lookup endpoint never hit — and wsp used as fallback when infovotantes is unreachable — its lookup endpoint never hit — plus a direct binding-order regression test).
- Fixed a regression the adapter reorder caused in the pre-existing `VoterRegistraduriaRefreshTest` (interactive Filament path) — see Deviations below.
- **Post-execution code review caught a real bug**: `PollingPlaceResolver::startLiveLookup()` (the interactive Filament flow's entry point, used by "Consultar Registraduría"/"Actualizar datos") always called the FIRST configured adapter unconditionally, never checking reachability — unlike `isLiveReachable()` and the automated cascade (`attemptLiveAutomated()`/`resolveAutomated()`), which both correctly skip unreachable adapters. This became live-impacting the moment this task reordered `liveAdapters` to `[InfovotantesService, RegistraduriaService]`: with infovotantes currently DNS-dead and wsp reachable, `isLiveReachable()` correctly returned `true` (gating the modal open), but `startLiveLookup()` would still have blindly invoked the dead infovotantes flow instead of the working wsp fallback. Fixed to iterate `liveAdapters` and use the first adapter whose `isReachable()` is `true`, matching the existing pattern elsewhere in the class.

## Task Commits

Each task was committed atomically:

1. **Task 1: Restore infovotantes flow as a second Flask route** - `f29ceba` (feat)
2. **Task 2: InfovotantesService adapter + config + priority-ordered registration** - `8878182` (feat)
3. **Task 3: Pest coverage — adapter behavior + resolver priority ordering** - `84332e8` (test)
4. **Auto-fix: VoterRegistraduriaRefreshTest adapter-reorder regression** - `5d04a99` (fix)
5. **Post-review fix: startLiveLookup() now skips unreachable adapters** - `12bde1a` (fix)

## Files Created/Modified

- `registraduria-service/app.py` - Added `_lookup_infovotantes_async`/`_run_infovotantes`/`POST /lookup/infovotantes`, ported verbatim from pre-Phase-9 commit ac1dd5a; wsp flow untouched (git diff shows only additions)
- `app/Services/InfovotantesService.php` - New `LiveSourceAdapter` implementation for the infovotantes endpoint
- `app/Providers/AppServiceProvider.php` - `liveAdapters: [InfovotantesService, RegistraduriaService]`
- `config/services.php` - New sibling `infovotantes` config block
- `.env.example` - New `INFOVOTANTES_SERVICE_URL`/`INFOVOTANTES_LIVE_ENABLED`/`INFOVOTANTES_PROBE_URL` entries
- `tests/Feature/Services/InfovotantesServiceTest.php` - New adapter coverage
- `tests/Feature/Services/PollingPlaceResolverPriorityTest.php` - New cascade-priority coverage
- `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` - Updated 4 tests to mock the now-actually-invoked `InfovotantesService` (first adapter) instead of/alongside `RegistraduriaService`, and to stub `InfovotantesService::isReachable()` so 4 other tests no longer depend on a real (currently DNS-dead) external domain; further updated (post-review fix) to stub `isReachable()` on 3 additional `startLiveLookup()`/`forceRefreshFromRegistraduria()` tests that only stubbed `startLookup()` before
- `app/Services/PollingPlaceResolver.php` - `startLiveLookup()` now iterates `liveAdapters` and returns the first adapter whose `isReachable()` is `true`, instead of unconditionally using the first configured adapter
- `tests/Feature/Services/PollingPlaceResolverTest.php` - Updated "Test 6" (previously asserted the first adapter is always used unconditionally) to assert priority is preserved only when the first adapter is reachable; added two new tests covering skip-unreachable-first-adapter and the no-reachable-adapter exception path
- `tests/Feature/Services/PollingPlaceResolverPriorityTest.php` - Added two new tests covering `startLiveLookup()`'s adapter selection: skips an unreachable infovotantes and uses wsp, and uses infovotantes when it is reachable (priority preserved)

## Decisions Made

- Registered `InfovotantesService` ahead of `RegistraduriaService` per the user's explicit trust/priority decision (documented in the plan's `must_haves`).
- `getResult()` on `InfovotantesService` is a pure JSON pass-through — no HTML-parsing code path exists on this class, verified by a dedicated test.
- Kept `RegistraduriaService` and its Python `/lookup` route byte-for-byte unchanged, confirmed via `git diff` showing zero deletions/modifications to existing wsp-related lines.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Adapter reorder broke 4 pre-existing interactive-path tests in `VoterRegistraduriaRefreshTest`**
- **Found during:** Final verification (`php artisan test --filter=Registraduria`)
- **Issue:** `PollingPlaceResolver::startLiveLookup()` always calls the FIRST configured adapter unconditionally (pre-existing, documented behavior, unrelated to this plan). Once `InfovotantesService` became the first adapter, 4 existing tests that only mocked `RegistraduriaService::startLookup()`/`isReachable()` broke: the real (unmocked) `InfovotantesService` was invoked instead, making a real outbound HTTP call that failed/returned no session_id.
- **Fix:** Updated the 4 affected tests to mock `InfovotantesService` (the adapter actually invoked for the interactive live-lookup path) instead of `RegistraduriaService`. Also added a parallel `InfovotantesService::isReachable()->andReturn(false)` mock to the 4 "unreachable → falls back to DB/snapshot" tests, which previously passed only by coincidence (the real infovotantes domain happens to be DNS-dead right now) — this removes a latent flakiness/real-network dependency that would have broken those tests the moment the domain comes back online.
- **Files modified:** `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php`
- **Verification:** `php artisan test tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` — 18/18 pass, 0 real network dependency remaining
- **Committed in:** `5d04a99`

---

**2. [Rule 1 - Bug] `startLiveLookup()` always used the first configured adapter unconditionally, never checking reachability**
- **Found during:** Post-execution code review, after this plan's commits had already landed
- **Issue:** `PollingPlaceResolver::startLiveLookup()` (called directly by `HasRegistraduriaPolling::openRegistraduriaBrowser()` and `forceRefreshFromRegistraduria()`, the interactive Filament flow) returned on the FIRST iteration of `liveAdapters` regardless of `isReachable()` — unlike `isLiveReachable()` and `attemptLiveAutomated()`/`resolveAutomated()`, which both correctly gate on `isReachable()` before attempting a lookup. This was pre-existing, latent behavior, but this plan's `liveAdapters` reorder to `[InfovotantesService, RegistraduriaService]` made it live-impacting: with infovotantes currently DNS-dead and wsp reachable, `isLiveReachable()` (called first in `openRegistraduriaBrowser()`) correctly returns `true` because wsp is up, but the immediately-following `startLiveLookup()` call would still have blindly invoked the dead infovotantes adapter instead of falling through to wsp — meaning the operator-facing "Consultar Registraduría" button would open a live-lookup session against a service guaranteed to fail/timeout, instead of the one that actually works.
- **Fix:** `startLiveLookup()` now iterates `liveAdapters` and returns the first adapter whose `isReachable()` is `true` (still no try/catch around `startLookup()` itself — only adapter *selection* is gated by reachability, matching `attemptLiveAutomated()`'s existing pattern). Updated the pre-existing "always uses the first adapter" test (`PollingPlaceResolverTest`) to assert the new "first reachable adapter" guarantee instead, added two new tests there (skip-unreachable-first-adapter, and the no-reachable-adapter `RuntimeException` path), and added two new tests to `PollingPlaceResolverPriorityTest` covering the exact infovotantes-down/wsp-up scenario this fix addresses. Also updated 3 `VoterRegistraduriaRefreshTest` cases whose `InfovotantesService` mocks only stubbed `startLookup()` (not `isReachable()`), which the new reachability check now requires.
- **Files modified:** `app/Services/PollingPlaceResolver.php`, `tests/Feature/Services/PollingPlaceResolverTest.php`, `tests/Feature/Services/PollingPlaceResolverPriorityTest.php`, `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php`
- **Verification:** `php artisan test --filter=PollingPlaceResolver` (24 passed), `--filter=Registraduria` (37 passed), `--filter=Infovotantes` (12 passed), `--filter=VoterResourceTest` (28 passed), `vendor/bin/pint --dirty --test` (clean) — zero failures
- **Committed in:** `12bde1a`

---

**Total deviations:** 2 auto-fixed (both Rule 1 - bugs; the first directly caused by this plan's AppServiceProvider binding change, the second a pre-existing bug this plan's binding change made live-impacting)
**Impact on plan:** Necessary for correctness — without these fixes, the interactive "Actualizar datos desde Registraduría" / "Consultar Registraduría" Filament actions would either have been left with a broken/flaky pre-existing test suite, or would have started routing operator-facing live lookups to the currently-dead infovotantes flow instead of the working wsp fallback. No scope creep — both fixes are scoped exclusively to the code/tests whose behavior/assumptions this plan's adapter reorder invalidated.

## Issues Encountered

- **Stale worktree at session start:** the execution worktree was checked out at `403e0f0` (pre-dating Phase 11's completion), missing `vendor/`, `.env`, and Phase 11's migrations/plans. Confirmed `403e0f0` is a fast-forward ancestor of main's `f5ae97e` HEAD; resolved via `git merge --ff-only`, `composer install --no-interaction`, and copying `.env` from the main checkout. DB was already migrated (shared DB across worktrees) so no `migrate` was needed. This is the same class of worktree-staleness issue documented repeatedly in STATE.md's Blockers/Concerns.

## User Setup Required

None - no external service configuration required. The infovotantes-related env vars (`INFOVOTANTES_SERVICE_URL`, `INFOVOTANTES_LIVE_ENABLED`, `INFOVOTANTES_PROBE_URL`) default sensibly and only need overriding if a real infovotantes deployment target differs from `wsp`'s.

## Next Phase Readiness

- InfovotantesService is live-registered and will automatically be tried first the moment the eleccionescolombia/infovotantes domain comes back online (per project's documented DNS-dead status) — no further code changes needed to activate it.
- Until infovotantes comes back online, both the interactive path (`startLiveLookup()`, post-review fix) and the automated path (`resolveAutomated()`, pre-existing) now correctly skip it and use wsp instead — no operator-facing regression from the reorder.
- `registraduria-service/app.py` needs its Python dependencies (`aiohttp`, `flask`, `playwright`) already present — no new pip installs required, confirmed by the plan.
- Full targeted suite (Registraduria/Infovotantes/PollingPlaceResolver/VoterResourceTest, 61 tests total across the four filters) passes with zero regressions; a broader `php artisan test` run shows pre-existing, unrelated failures (missing Vite build manifest in this fresh worktree + the previously-documented `UserResourceTest > can update user campaigns` flake) — none touch Registraduria/Infovotantes/PollingPlaceResolver code paths.

---
*Quick task: 260726-eu3*
*Completed: 2026-07-26*

## Self-Check: PASSED

All 10 created/modified files confirmed present on disk. All 5 commit hashes (`f29ceba`, `8878182`, `84332e8`, `5d04a99`, `12bde1a`) confirmed present in git log.
