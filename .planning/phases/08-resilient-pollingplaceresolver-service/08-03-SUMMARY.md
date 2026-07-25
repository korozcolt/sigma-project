---
phase: 08-resilient-pollingplaceresolver-service
plan: 03
subsystem: filament
tags: [laravel, filament, livewire, pest, resolver, cascade, no-downgrade-guard, cache]

# Dependency graph
requires:
  - phase: 08-01
    provides: LiveSourceAdapter interface, PollingPlaceResolutionResult VO, RegistraduriaService::isReachable()
  - phase: 08-02
    provides: PollingPlaceResolver (cascade + no-downgrade guard + audit-transition persist())
provides:
  - HasRegistraduriaPolling trait fully delegating cascade decisions and persistence to PollingPlaceResolver (no cascade logic duplicated in the Filament layer)
  - PollingPlaceResolver bound in AppServiceProvider with RegistraduriaService as its sole live adapter (one-line extension point for a future wsp adapter)
  - Save-button leak fix — applyResolvedFields() reverts save-bound identity fields when persist() blocks a downgrade
  - Cache-mislabeling fix — the DB-reconstruction tier never writes to the shared live-only registraduria:cedula:{cedula} cache key
affects: [10-operator-provenance-fallback-controls, 11-scheduled-reconciliation-job]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament trait delegates to a resolver service via app(PollingPlaceResolver::class) instead of owning cascade/persistence logic itself"
    - "Pre-lookup snapshot + conditional revert of save-bound form fields to prevent a resolver-level guard decision from being silently overridden by the next ordinary Save"
    - "Cache keys shared across resolution tiers must only ever be written by the tier whose label the cache-hit branch assumes true"

key-files:
  created: []
  modified:
    - app/Providers/AppServiceProvider.php
    - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
    - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php

key-decisions:
  - "forceRefreshFromRegistraduria() still respects REGISTRADURIA_LIVE_ENABLED even though it bypasses the no-downgrade guard (D-10) — if live is globally disabled there is nothing to force-refresh to, so gating it here is not a new restriction on the operator's override."

requirements-completed: [CENSO-01, SRC-02, LIVE-01, LIVE-03]

# Metrics
duration: 12min
completed: 2026-07-25
---

# Phase 8 Plan 3: Wire PollingPlaceResolver into the Interactive Filament Lookup Summary

**HasRegistraduriaPolling now delegates all cascade decisions and persistence to PollingPlaceResolver — live-first with DB/snapshot fallback, a no-downgrade guard that also reverts Filament's save-bound form fields on block, and a cache-mislabeling fix so a DB-reconstruction result can never be silently promoted to LIVE on a later lookup — while both pre-existing Filament actions stay pixel-identical for every case that already worked**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-07-25 (session start)
- **Completed:** 2026-07-25
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- `PollingPlaceResolver` bound in `AppServiceProvider::register()` with `RegistraduriaService` as its sole live adapter (LIVE-01 — adding a second adapter later is a one-line array change)
- `HasRegistraduriaPolling::openRegistraduriaBrowser()` reordered to cache -> live (if reachable) -> DB reconstruction -> national snapshot (D-01/CENSO-01), never opening the live modal when live is unreachable or kill-switched (LIVE-03)
- `applyResolvedFields()` added: snapshots the six save-bound identity fields (`polling_place_id`, `municipality_id`, `department_id`, `polling_table_number`, `address`, `detailed_address`) before `fillPollingPlaceFields()` mutates them, and reverts them if `PollingPlaceResolver::persist()` blocks the write — so a guard-blocked automatic downgrade can never leak into a saved voter record through the ordinary Save button (SRC-02)
- DB-reconstruction tier deliberately no longer writes to the shared `registraduria:cedula:{cedula}` Redis key — only a genuine live result (`handleRegistraduriaResult()`) warms it, so a cache hit's `PollingPlaceSource::LIVE` label is always accurate (SRC-02, cache-mislabeling fix)
- `resolveFromDatabase()` (the old private cascade method) removed from the trait entirely — its logic now lives solely in `PollingPlaceResolver::resolveFromCampaignCensus()` (Plan 08-02), eliminating a second copy of the cascade
- 7 new Pest tests added through real Filament/Livewire component calls, including a genuine `->call('save')` regression proving a blocked downgrade never persists, and a genuine second-lookup regression proving the DB-reconstruction tier can never be mislabeled LIVE via a stale cache hit
- All 4 pre-existing `VoterRegistraduriaRefreshTest.php` tests pass unmodified, proving both Filament actions are pixel-identical to before this phase for every case that already worked (D-09)

## Task Commits

Each task was committed atomically:

1. **Task 1: Bind PollingPlaceResolver and refactor HasRegistraduriaPolling to delegate** - `5d21266` (feat)
2. **Task 2: New interactive-cascade coverage (reorder, snapshot fallback, no-downgrade guard, cache regression)** - `b7fa973` (test)

## Files Created/Modified

- `app/Providers/AppServiceProvider.php` - `register()` now binds `PollingPlaceResolver::class` with `[RegistraduriaService::class]` as its `liveAdapters` array
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` - Full rewrite: removed `resolveFromDatabase()` and direct `RegistraduriaService` calls; `openRegistraduriaBrowser()`/`forceRefreshFromRegistraduria()` now call `app(PollingPlaceResolver::class)`; added `applyResolvedFields()` (guarded-field revert-on-block) and `GUARDED_IDENTITY_FIELDS`; DB-reconstruction branch no longer calls `Cache::put()`; `fillPollingPlaceFields()` moved verbatim, unchanged
- `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` - Added 7 new `it(...)` tests: live-first ordering, DB-reconstruction fallback, national-snapshot fallback, guard-blocked-downgrade Save-action regression, force-refresh guard-bypass, force-refresh kill-switch, and the cache-mislabeling second-lookup regression

## Decisions Made

- `forceRefreshFromRegistraduria()` keeps respecting `REGISTRADURIA_LIVE_ENABLED` even though D-10 exempts it from the no-downgrade guard — this was already the plan's inferred decision and needed no revisiting; verified by Test F (`live_enabled = false` -> warning shown, `startLookup()` never called) and Test E (`live_enabled = true` on an already-`live` voter -> `startLookup()` still called, guard bypassed).

## Deviations from Plan

None - plan executed exactly as written. The `HasRegistraduriaPolling.php` rewrite, `AppServiceProvider` binding, and all 7 new test cases were implemented per the plan's `<action>` blocks and `<behavior>` spec with no bug fixes, missing-functionality additions, or blocking-issue workarounds required.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration or environment variables introduced by this plan.

## Next Phase Readiness

- CENSO-01, SRC-02, LIVE-01, and LIVE-03 are now all observable from the operator-facing interactive lookup workflow, completing Phase 8's scope end-to-end.
- Phase 10 (Operator Provenance & Fallback Controls) can build the visible source badge directly on top of `voters.polling_place_source`, which this plan's cascade now keeps correctly gated against silent downgrades.
- Phase 11 (Scheduled Reconciliation Job) can call `PollingPlaceResolver::resolveAutomated()` directly — it was already built and tested in Plan 08-02 and needed no changes in this plan.
- No blockers for Phase 9, 10, or 11.

---
*Phase: 08-resilient-pollingplaceresolver-service*
*Completed: 2026-07-25*

## Self-Check: PASSED

All 3 claimed files found on disk (`app/Providers/AppServiceProvider.php`, `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php`, `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php`). Both task commits (`5d21266`, `b7fa973`) found in git history.
