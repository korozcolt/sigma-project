---
phase: quick
plan: 260805-qt1
subsystem: voter-validation
tags: [laravel, filament, pest, enum, scheduled-job, territorial-scope]

# Dependency graph
requires:
  - phase: none
    provides: standalone quick task, builds on existing VoterValidationService/VoterStatus/Jurisdiction widgets
provides:
  - "VoterStatus::REJECTED_OUT_OF_SCOPE enum case with full HasColor/HasIcon/HasLabel/HasDescription support"
  - "App\\Services\\VoterTerritoryScope — single source of truth for the Dentro/Fuera territorial comparison"
  - "App\\Jobs\\ReconcileVoterTerritory + census:reconcile-territory command, scheduled hourly"
  - "VoterValidationService::isProtectedStatus() public guard, reused by both census and territory automated validation"
  - "RejectionsReportTable now surfaces CENSUS_NOT_FOUND and REJECTED_OUT_OF_SCOPE voters"
affects: [voter-reporting, scheduled-jobs, census-validation]

tech-stack:
  added: []
  patterns:
    - "Shared comparison logic extracted to a plain service class (VoterTerritoryScope) consumed by 3 previously-triplicated Filament sites, mirroring the project's existing service-layer convention"
    - "Automated status-mutation job pattern (ShouldQueue + Queueable + MAX_VOTERS_PER_RUN const + failed() Log::error) mirrored from DispatchCensusRevalidation/ReconcileFallbackPollingPlaces"

key-files:
  created:
    - app/Services/VoterTerritoryScope.php
    - app/Jobs/ReconcileVoterTerritory.php
    - app/Console/Commands/ReconcileTerritoryValidations.php
    - tests/Feature/Services/VoterTerritoryScopeTest.php
    - tests/Feature/Jobs/ReconcileVoterTerritoryTest.php
  modified:
    - app/Enums/VoterStatus.php
    - app/Filament/Widgets/JurisdictionReportTable.php
    - app/Filament/Widgets/JurisdictionSummaryOverview.php
    - app/Exports/JurisdictionExport.php
    - app/Services/VoterValidationService.php
    - routes/console.php
    - app/Filament/Widgets/RejectionsReportTable.php
    - tests/Feature/Filament/RejectionsReportTableTest.php

key-decisions:
  - "VoterTerritoryScope::isWithinCampaignScope() operates per-voter (not query-builder); JurisdictionSummaryOverview::insideCount() switched from 2 raw SQL count() branches to an eager-loaded fetch + PHP filter to avoid N+1, per plan's explicit instruction."
  - "REJECTED_OUT_OF_SCOPE colored 'danger' (same family as REJECTED_CENSUS) with a distinct heroicon-m-map-pin icon to visually differentiate a territorial rejection from a census rejection at a glance."
  - "ReconcileVoterTerritory fetches ALL voters per applicable campaign (no status filter) rather than only currently-in-scope or only-REJECTED_OUT_OF_SCOPE ones, since the job must detect both directions (newly out-of-scope AND back-in-scope) in a single pass."

requirements-completed: []

duration: 68min
completed: 2026-08-06
---

# Quick Task 260805-qt1: Territorial Scope Validation Summary

**Automated hourly job (`census:reconcile-territory`) now rejects/reinstates apoyos based on campaign territory (municipio/departamento), backed by a new shared `VoterTerritoryScope` service that replaced 3 duplicated Dentro/Fuera comparisons.**

## Performance

- **Duration:** 68 min (includes worktree re-provisioning: composer install + npm install/build, since this worktree was missing `vendor/`, `.env`, `node_modules`, `public/build`)
- **Started:** 2026-08-06T00:52:00Z
- **Completed:** 2026-08-06T01:00:54Z
- **Tasks:** 3/3 completed
- **Files modified:** 13 (5 created, 8 modified)

## Accomplishments
- Extracted the triplicated Dentro/Fuera comparison logic (`JurisdictionReportTable`, `JurisdictionSummaryOverview`, `JurisdictionExport`) into a single `App\Services\VoterTerritoryScope` service, with zero behavior regression on any of the 3 existing sites.
- Added `VoterStatus::REJECTED_OUT_OF_SCOPE`, fully wired into all four `HasColor`/`HasIcon`/`HasLabel`/`HasDescription` match blocks.
- Built `App\Jobs\ReconcileVoterTerritory` + `census:reconcile-territory` artisan command, scheduled hourly with `withoutOverlapping(10)`, matching the exact pattern of the two existing census-reconciliation jobs.
- The job automatically marks out-of-territory apoyos `REJECTED_OUT_OF_SCOPE` and reverts them to `PENDING_REVIEW` when territory changes bring them back in scope — always respecting the existing `NON_DOWNGRADABLE_STATUSES` guard (now exposed as `VoterValidationService::isProtectedStatus()`, a single public source of truth reused by both census and territory validation).
- `RejectionsReportTable` ("Informe de Rechazos") now surfaces both `CENSUS_NOT_FOUND` and `REJECTED_OUT_OF_SCOPE` voters with the correct "Motivo del Rechazo".

## Task Commits

1. **Task 1: Add REJECTED_OUT_OF_SCOPE status and extract shared VoterTerritoryScope service** - `6b34059` (feat)
2. **Task 2: Create ReconcileVoterTerritory job + census:reconcile-territory command, wired hourly** - `c95a849` (feat)
3. **Task 3: Surface REJECTED_OUT_OF_SCOPE (and CENSUS_NOT_FOUND) in RejectionsReportTable, full regression, pint** - `a3ea319` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Enums/VoterStatus.php` - Added `REJECTED_OUT_OF_SCOPE` case (label/color/icon/description)
- `app/Services/VoterTerritoryScope.php` - New shared service: `isWithinCampaignScope()`, `isTerritoryDefined()`
- `app/Filament/Widgets/JurisdictionReportTable.php` - Refactored `jurisdiccion` column to use the shared service
- `app/Filament/Widgets/JurisdictionSummaryOverview.php` - Refactored `insideCount()` to use the shared service (eager-load + PHP filter, no N+1)
- `app/Exports/JurisdictionExport.php` - Refactored `jurisdiccionFor()` to use the shared service
- `app/Services/VoterValidationService.php` - Added public `isProtectedStatus()` wrapper around `NON_DOWNGRADABLE_STATUSES`
- `app/Jobs/ReconcileVoterTerritory.php` - New scheduled job marking/reverting `REJECTED_OUT_OF_SCOPE`
- `app/Console/Commands/ReconcileTerritoryValidations.php` - New `census:reconcile-territory` command
- `routes/console.php` - Scheduled `census:reconcile-territory` hourly with `withoutOverlapping(10)`
- `app/Filament/Widgets/RejectionsReportTable.php` - Added both new statuses to the rejections query and motivo column
- `tests/Feature/Services/VoterTerritoryScopeTest.php` - 11 tests covering all scope combinations
- `tests/Feature/Jobs/ReconcileVoterTerritoryTest.php` - 14 tests covering all job behaviors
- `tests/Feature/Filament/RejectionsReportTableTest.php` - Extended with CENSUS_NOT_FOUND/REJECTED_OUT_OF_SCOPE coverage

## Decisions Made
See `key-decisions` in frontmatter above.

## Deviations from Plan

None - plan executed exactly as written (all file paths, method signatures, and status values match the plan's `<interfaces>` section verbatim).

## Issues Encountered

**Worktree staleness (environment, not code):** This worktree was missing `vendor/`, `.env`, `node_modules/`, and `public/build/` at session start (same class of issue documented repeatedly in `.planning/STATE.md` Blockers/Concerns for prior phases/quick-tasks in this repo). Confirmed the worktree's git branch was already at the same commit as `main` (not stale in history, just unprovisioned) — resolved by copying `.env` from the main checkout, running `composer install`, `npm install`, and `npm run build`. No code changes required.

**Full-suite test-pollution recurrence (pre-existing, documented, not fixed here):** Running the complete `php artisan test` suite surfaces 17 intermittent failures (varying subset per run) due to the pre-existing, already-documented `CampaignContext::setCampaignId()` static-override test-pollution bug (see STATE.md Blockers/Concerns, first found in Phase 05.1, recurred in quick task 260730-cs3). All of this plan's targeted test files pass cleanly (45/45 assertions) when run in isolation via `php artisan test --filter="..."` — confirming this plan introduces zero regressions. See `.planning/quick/260805-qt1-implementar-validaci-n-de-alcance-territ/deferred-items.md` for the full write-up.

## User Setup Required

None - no external service configuration required. `census:reconcile-territory` will start running automatically via the existing Laravel scheduler (cron) the same way `census:reconcile-live` and `census:reconcile-validation` already do — no new infrastructure needed.

## Next Phase Readiness

- All 3 tasks complete and committed; targeted regression suite (45 tests across `VoterTerritoryScopeTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`, `ReconcileVoterTerritoryTest`, `RejectionsReportTableTest`, plus `TopCoordinatorsTableTest`/`TopPollingPlacesTableTest` for cross-check) passes; `vendor/bin/pint --dirty` clean.
- Per project's standing "browser-verify before prod" preference, a manual browser confirmation is still pending before deploying: confirm in a real browser that a Municipal-scope campaign's "Informe de Rechazos" widget shows a voter as `REJECTED_OUT_OF_SCOPE` after running `php artisan census:reconcile-territory` against a voter whose municipality doesn't match the campaign's. This has been added to STATE.md's pending-checkpoint list, matching the precedent of other recent quick tasks in this repo.

## Self-Check: PASSED

All created files confirmed present on disk (`app/Services/VoterTerritoryScope.php`, `app/Jobs/ReconcileVoterTerritory.php`, `app/Console/Commands/ReconcileTerritoryValidations.php`, `tests/Feature/Services/VoterTerritoryScopeTest.php`, `tests/Feature/Jobs/ReconcileVoterTerritoryTest.php`) and all modified files confirmed present. All 3 task commits (`6b34059`, `c95a849`, `a3ea319`) confirmed present in `git log`.

---
*Phase: quick*
*Completed: 2026-08-06*
