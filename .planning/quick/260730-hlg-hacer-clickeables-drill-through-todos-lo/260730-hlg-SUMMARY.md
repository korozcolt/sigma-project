---
phase: quick-260730-hlg
plan: 01
subsystem: ui
tags: [filament, livewire, tables, widgets, drill-through, reports]

requires:
  - phase: quick-260730-fkf
    provides: reports panel + VoterResource registered on it (reused unchanged by this task's new drill-throughs)
provides:
  - recordUrl-based drill-through on TerritorialOwnershipTable, TopCoordinatorsTable, TopPollingPlacesTable, JurisdictionReportTable, RejectionsReportTable, ApoyosLideresCoordinadoresTable
  - Stat->url() drill-through on CampaignStatsOverview's Total de Apoyos and Apoyos Confirmados
  - new polling_place_id SelectFilter on VotersTable enabling the polling-place drill-through target
affects: [reports-panel, admin-dashboard, voters-resource]

tech-stack:
  added: []
  patterns:
    - "Aggregate-row widgets (User/PollingPlace) -> ->recordUrl() to VoterResource::getUrl('index', ['tableFilters' => [...]])"
    - "Per-voter-row widgets (flat Voter tables) -> ->recordUrl() to VoterResource::getUrl('view', ['record' => $record])"
    - "Coordinator 'whole team' filter value = $coordinator->leaders()->pluck('id')->push($coordinator->id)->all()"

key-files:
  created: []
  modified:
    - app/Filament/Widgets/TerritorialOwnershipTable.php
    - app/Filament/Widgets/TopCoordinatorsTable.php
    - app/Filament/Widgets/CampaignStatsOverview.php
    - app/Filament/Widgets/TopPollingPlacesTable.php
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - app/Filament/Widgets/JurisdictionReportTable.php
    - app/Filament/Widgets/RejectionsReportTable.php
    - app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php
    - tests/Feature/WidgetDrillThroughTest.php

key-decisions:
  - "TerritorialOwnershipTable's single recordUrl closure branches coordinator-first (hasRole(COORDINATOR) -> team url) since a row can hold both roles and the team view is the superset, matching the plan's explicit branch order."
  - "DuplicatesReportTable intentionally left untouched (no recordUrl) — cross-campaign isolation exception, locked in by a new Pest case asserting its recordUrl is null."
  - "No url added to Líderes Activos / Progreso de Validación stats — no natural single filtered-list target exists on VotersTable for either metric."

patterns-established:
  - "getTable()->getRecordUrl($record) is the standard Pest assertion pattern for verifying a widget's row-level drill-through, matching the existing TopLeadersTable precedent."

requirements-completed: [HLG-DRILL]

duration: 45min
completed: 2026-07-30
---

# Quick Task 260730-hlg: Hacer clickeables los drill-through en todos los widgets del panel de reportes Summary

**Added recordUrl/Stat->url() drill-throughs to 7 report-panel widgets plus a new polling_place_id VotersTable filter, so every aggregate row and headline stat now opens the underlying filtered Voters list, and every per-voter report row opens that voter's detail view.**

## Performance

- **Duration:** 45 min
- **Started:** 2026-07-30 (session start)
- **Completed:** 2026-07-30
- **Tasks:** 4/4 completed
- **Files modified:** 9

## Accomplishments
- TerritorialOwnershipTable and TopCoordinatorsTable now branch/resolve to the correct leader vs. coordinator-team filtered Voters list.
- CampaignStatsOverview's "Total de Apoyos" and "Apoyos Confirmados" stats now link to the campaign-wide and status=confirmed Voters lists respectively.
- Added a new `polling_place_id` SelectFilter to VotersTable and wired TopPollingPlacesTable's rows to it.
- JurisdictionReportTable, RejectionsReportTable, and ApoyosLideresCoordinadoresTable rows now link to each voter's own detail view page.
- DuplicatesReportTable's deliberate non-clickable design is now locked in by a regression test asserting its `recordUrl` is null.

## Task Commits

Each task was committed atomically (TDD RED -> GREEN per task):

1. **Task 1: Coordinator/leader team drill-through on TerritorialOwnershipTable + TopCoordinatorsTable**
   - `c432738` test(quick-260730-hlg): add failing tests for leader/coordinator drill-through
   - `4f080f0` feat(quick-260730-hlg): add leader/coordinator-team drill-through to TerritorialOwnershipTable and TopCoordinatorsTable
2. **Task 2: Total de Apoyos + Apoyos Confirmados stat drill-through on CampaignStatsOverview**
   - `82934f9` test(quick-260730-hlg): add failing test for CampaignStatsOverview stat drill-through
   - `751ce28` feat(quick-260730-hlg): add stat drill-through to Total de Apoyos and Apoyos Confirmados
3. **Task 3: Polling-place drill-through — polling_place_id filter + TopPollingPlacesTable recordUrl**
   - `1fb813b` test(quick-260730-hlg): add failing test for TopPollingPlacesTable drill-through
   - `75bf014` feat(quick-260730-hlg): add polling_place_id filter and TopPollingPlacesTable drill-through
4. **Task 4: Per-voter view drill-through on Jurisdiction/Rejections/ApoyosLideres; lock Duplicates skip**
   - `4904bf1` test(quick-260730-hlg): add failing tests for per-voter view drill-through + duplicates skip lock
   - `a41c4f8` feat(quick-260730-hlg): add per-voter view drill-through to Jurisdiction/Rejections/ApoyosLideres tables

**Plan metadata:** this commit (docs: complete quick task)

## Files Created/Modified
- `app/Filament/Widgets/TerritorialOwnershipTable.php` - branching recordUrl (coordinator-team vs. leader)
- `app/Filament/Widgets/TopCoordinatorsTable.php` - coordinator-team recordUrl
- `app/Filament/Widgets/CampaignStatsOverview.php` - ->url() on Total de Apoyos / Apoyos Confirmados stats
- `app/Filament/Widgets/TopPollingPlacesTable.php` - polling-place recordUrl
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - new polling_place_id SelectFilter
- `app/Filament/Widgets/JurisdictionReportTable.php` - per-voter view recordUrl
- `app/Filament/Widgets/RejectionsReportTable.php` - per-voter view recordUrl
- `app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php` - per-voter view recordUrl
- `tests/Feature/WidgetDrillThroughTest.php` - 9 new Pest cases covering every new drill-through plus the Duplicates null-recordUrl lock

## Decisions Made
- TerritorialOwnershipTable's recordUrl branches coordinator-first (`hasRole(UserRole::COORDINATOR->value)` checked before falling through to the leader case), since a user row could theoretically hold both roles and the team view is the superset — matches the plan's specified branch order exactly.
- Test setup for the coordinator-team cases attaches both leaders to the active campaign (`$leader->campaigns()->attach($this->campaign)`), because `User::leaders()` is filtered by the pre-existing `CampaignMembershipScope` global scope — without this, `leaders()->pluck('id')` returns empty regardless of the `coordinator_user_id` foreign key, which would have made the "team" assertion vacuously trivial (team = coordinator only). This mirrors how `TopLeadersTable`'s pre-existing test already attaches its leader to the campaign.
- No url added to `getActiveLeadersStat()` ('Líderes Activos') or `getValidationProgressStat()` ('Progreso de Validación') — no natural single filtered-list target exists on VotersTable for either metric (a user headcount and a call_verified_at-derived percentage respectively), per the plan's explicit deliberate-skip guidance.
- DuplicatesReportTable left completely untouched — its cross-campaign-isolation exception (documented in its own class docblock) means no safe VoterResource drill-through target exists; locked in with a new Pest case asserting `getRecordUrl($voter)` is null.

## Deviations from Plan

None - plan executed exactly as written. The only adjustment was in the new test file's own setup fixtures (attaching leaders to the active campaign so the pre-existing `CampaignMembershipScope` didn't silently empty the `leaders()` relationship in the coordinator-team test cases) — not a deviation from the plan's behavior/interface contract, since the plan's `<interfaces>` section already documented `$coordinator->leaders()->pluck('id')->push($coordinator->id)->all()` as the exact formula to implement and test against.

## Issues Encountered
None beyond the test-fixture adjustment described above (caught immediately via the TDD RED run, corrected before GREEN).

## User Setup Required
None - no external service configuration required.

## Full Test Suite / Environment Notes

- `php artisan test --filter=WidgetDrillThroughTest` — all 11 cases (2 pre-existing + 9 new) pass.
- Also ran the full related-widget/table test files (`DashboardWidgetsTest`, `OwnershipScopedWidgetsTest`, `PageScopedWidgetRegistrationTest`, `RejectionsReportTableTest`, `ReportsPanelTest`, `JurisdictionReportTableTest`, `DuplicatesReportTableTest`) together — 57/57 pass, no regressions.
- `vendor/bin/pint --dirty` clean on every commit.
- Full-suite (`php artisan test`) run showed 10-11 failures that varied in composition and count between two consecutive runs (`UserResourceTest > can update user campaigns`, `VoterResourceTest > creating a voter with...`, plus disjoint subsets of `DuplicatesReportTableTest`/`JurisdictionReportTableTest`/`RejectionsReportTableTest`/`TopCoordinatorsTableTest`/`TopPollingPlacesTableTest`) — every one of these passed cleanly when re-run in isolation (confirmed: `php artisan test tests/Feature/Filament/DuplicatesReportTableTest.php tests/Feature/Filament/JurisdictionReportTableTest.php tests/Feature/Filament/RejectionsReportTableTest.php tests/Feature/Filament/TopCoordinatorsTableTest.php tests/Feature/Filament/TopPollingPlacesTableTest.php` -> 14/14 pass). This matches the already-documented pre-existing `CampaignContext` static-override test-pollution issue in STATE.md's Blockers/Concerns section (non-deterministic, disjoint failure sets between runs, always passes alone) — not a regression introduced by this task.
- **Worktree staleness recurred again** (worktree `agent-a87bbc7958750c776`): missing `vendor/`, `.env`, `node_modules/`, `public/build/`, and this task's own uncommitted `.planning/quick/260730-hlg-.../260730-hlg-PLAN.md` (which existed only as an untracked file in the main checkout, never committed, so it wasn't visible to any worktree). Resolved by copying the plan directory from the main checkout, then `composer install`, `.env` copy, `npm install && npm run build` (discarding the resulting cosmetic `package-lock.json` name-field diff via `git checkout --`, same as prior tasks).
- `gsd-tools init execute-phase` reconfirmed the previously-documented `findProjectRoot()` root-resolution bug in this session too (`project_root` resolved to the main checkout, not this worktree) — STATE.md updates for this task were hand-edited directly in the worktree, per the established workaround, not via the CLI.

## Next Phase Readiness
Code + tests are complete and committed. Per the user's standing "browser-verify before prod" preference and this plan's own `<verification>` section, a real-browser click-through (leader, coordinator, Total de Apoyos stat, a polling place, and a jurisdiction/rejection row on both /reports and /admin) is still recommended before this is deployed to sigma-betha production — not yet performed as part of this quick-task's automated scope (no browser-automation tool was available in this execution session).

---
*Phase: quick-260730-hlg*
*Completed: 2026-07-30*

## Self-Check: PASSED

All 9 created/modified files confirmed present on disk; all 8 task commit hashes (`c432738`, `4f080f0`, `82934f9`, `751ce28`, `1fb813b`, `75bf014`, `4904bf1`, `a41c4f8`) confirmed present in git history.
