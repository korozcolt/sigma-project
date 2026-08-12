---
phase: 19-articulador-panel-human-uat-closure
plan: 01
subsystem: reporting
tags: [filament, livewire, widgets, articulador, area_coordinator, authorization-scoping]

# Dependency graph
requires:
  - phase: 15-articulador-self-service-panel
    provides: "User::teamCoordinatorUserIds() already established as the transitive-team resolution mechanism, first consumed by TopLeadersTable"
provides:
  - "CampaignStatsOverview scoped to an articulador's transitive team (scopedVoterQuery() + getActiveLeadersStat())"
  - "TerritorialDistributionChart scoped to an articulador's transitive team (getData())"
  - "Regression + new-behavior test coverage proving cross-articulador isolation on both widgets"
affects: [19-articulador-panel-human-uat-closure remaining plans, any future dashboard/report widget touching Voter or leader/coordinator scoping]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "AREA_COORDINATOR scoping branch mirrors the already-established COORDINATOR pattern via User::teamCoordinatorUserIds(), collapsed into a single hasAnyRole([COORDINATOR, AREA_COORDINATOR]) branch wherever the resulting query shape is identical for both roles"

key-files:
  created: []
  modified:
    - app/Filament/Widgets/CampaignStatsOverview.php
    - app/Filament/Widgets/TerritorialDistributionChart.php
    - tests/Feature/OwnershipScopedWidgetsTest.php

key-decisions:
  - "TerritorialDistributionChart::getData() is protected (base Filament ChartWidget signature) — tested via ReflectionMethod::setAccessible(true) rather than a direct ->instance()->getData() call, since Livewire's Testable magic __call cannot invoke non-public methods."
  - "CampaignStatsOverview::scopedVoterQuery()'s COORDINATOR-only branch was collapsed into a single hasAnyRole([COORDINATOR, AREA_COORDINATOR]) branch using teamCoordinatorUserIds() — behaviorally identical to the old coordinador-only query since teamCoordinatorUserIds() returns [$this->id] for a lone coordinador."
  - "getActiveLeadersStat() keeps a separate, explicit AREA_COORDINATOR branch (not collapsed with COORDINATOR) because its Stat description differs slightly in what it counts (leaders resolved via User::whereIn('coordinator_user_id', ...) rather than $user->leaders()), matching the plan's literal interface spec."

patterns-established: []

requirements-completed: []

# Metrics
duration: 25min
completed: 2026-08-12
---

# Phase 19 Plan 01: Articulador Dashboard Widget Scoping Summary

**Closed a real cross-articulador data leak: `CampaignStatsOverview` and `TerritorialDistributionChart` now scope to an articulador's own transitive team (their coordinadores' leaders' voters) via `User::teamCoordinatorUserIds()`, matching the already-correct `TopLeadersTable` pattern — an articulador no longer sees full-campaign totals.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-08-12T04:39:30Z (approx, worktree environment setup included)
- **Completed:** 2026-08-12T05:04:00Z (approx)
- **Tasks:** 2 (1 code + tests, 1 regression verification)
- **Files modified:** 3

## Accomplishments
- `CampaignStatsOverview::scopedVoterQuery()` now branches on `hasAnyRole([COORDINATOR, AREA_COORDINATOR])`, resolving voter ownership through `User::whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())->pluck('id')` instead of leaking the full campaign total to an articulador.
- `CampaignStatsOverview::getActiveLeadersStat()` gained a dedicated `AREA_COORDINATOR` branch (before the generic full-campaign fallback) so "Líderes Activos" also reflects the articulador's own team, not every leader in the campaign.
- `TerritorialDistributionChart::getData()` gained the same `hasAnyRole([COORDINATOR, AREA_COORDINATOR])` scoping clause, so the top-10-municipios chart no longer shows campaign-wide totals to an articulador.
- `tests/Feature/OwnershipScopedWidgetsTest.php` extended with two `areaCoordinatorA`/`areaCoordinatorB` fixtures (each owning one of the existing `coordinatorA`/`coordinatorB` fixtures) and 3 new tests: articulador-scoped total on `CampaignStatsOverview`, cross-articulador isolation (a second articulador does not see the first's total), and articulador-scoped municipality data on `TerritorialDistributionChart`.
- Confirmed zero regressions across the full articulador/coordinador scoping surface: `OwnershipScopedWidgetsTest` (8), `ArticuladorTeamResolutionTest` (5), `AreaCoordinatorPanelAccessTest` (6), `AreaCoordinatorHierarchyTest` (5) — 24/24 passing.

## Task Commits

1. **Task 1: Add AREA_COORDINATOR scoping to CampaignStatsOverview and TerritorialDistributionChart** - `a3a04e3` (test+feat, combined — RED confirmed interactively before the fix, then test+implementation committed together)
2. **Task 2: Regression check across the full articulador/coordinador widget-scoping surface** - no additional commit (verification-only task; `vendor/bin/pint --dirty` had already produced clean output as part of Task 1, and the regression suite required no code changes)

**Plan metadata:** (this commit, docs: complete plan)

_Note: Task 1 combined RED (test-only) and GREEN (implementation) into a single commit rather than two separate TDD commits — RED state was verified interactively (`php artisan test --filter=OwnershipScopedWidgetsTest` showing 3 failing / 5 passing) before writing the fix, but both the test file and the two widget files were staged together at commit time._

## Files Created/Modified
- `app/Filament/Widgets/CampaignStatsOverview.php` - Added `AREA_COORDINATOR` branch to `scopedVoterQuery()` (collapsed with `COORDINATOR` via `teamCoordinatorUserIds()`) and a dedicated `AREA_COORDINATOR` branch to `getActiveLeadersStat()`.
- `app/Filament/Widgets/TerritorialDistributionChart.php` - Added `use App\Models\User;` import and extended the `COORDINATOR`-only `->when()` clause in `getData()` to `hasAnyRole([COORDINATOR, AREA_COORDINATOR])` via `teamCoordinatorUserIds()`.
- `tests/Feature/OwnershipScopedWidgetsTest.php` - Added `areaCoordinatorA`/`areaCoordinatorB` fixtures in `beforeEach` and 3 new tests proving articulador-scoped totals and cross-articulador isolation on both widgets.

## Decisions Made
- `TerritorialDistributionChart::getData()` is `protected` on the base Filament `ChartWidget` — the plan's literal test spec (`->instance()->getData()`) does not work as written since Livewire's `Testable` magic `__call` only proxies to public methods. Used `ReflectionMethod::setAccessible(true)` to invoke it directly on the resolved component instance instead, preserving the test's intent (assert on the raw `datasets[0].data` array sum) without changing the widget's method visibility.
- Collapsed `scopedVoterQuery()`'s coordinator branch with the new articulador branch (single `hasAnyRole()` check) since the query shape is byte-for-byte equivalent for a lone coordinador (`teamCoordinatorUserIds()` returns `[$this->id]` in that case). Left `getActiveLeadersStat()`'s two branches separate per the plan's literal interface spec, since that method's Stat computation differs slightly in shape from the coordinador branch.

## Deviations from Plan

None — plan executed as written. The one interface-literal issue (protected `getData()`) was resolved via reflection, which is a test-implementation detail, not a deviation from the plan's specified behavior or files.

## Issues Encountered
- **Stale worktree (recurring, previously documented class):** This worktree was checked out at the Phase 15 completion commit (`6dd2f24`), missing all of Phases 16-19's planning corpus (including this plan's own `19-01-PLAN.md`), plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, and copied `public/build/` from the main checkout (this plan makes no frontend asset changes, so `npm run build` was not needed — the copy alone resolved 1 spurious `AreaCoordinatorPanelAccessTest` "Vite manifest not found" failure during the Task 2 regression run, confirmed unrelated to this plan's own files).

## Next Phase Readiness
- `CampaignStatsOverview` and `TerritorialDistributionChart` are now both genuinely scoped for articuladores — Phase 19's remaining browser/UAT-closure plans can assert on correct scoped behavior for these two widgets rather than documenting a known bug.
- No blockers identified for subsequent Phase 19 plans.

---
*Phase: 19-articulador-panel-human-uat-closure*
*Completed: 2026-08-12*

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/CampaignStatsOverview.php
- FOUND: app/Filament/Widgets/TerritorialDistributionChart.php
- FOUND: tests/Feature/OwnershipScopedWidgetsTest.php
- FOUND: commit a3a04e3
