---
phase: 23-differentiator-visualizations
plan: 02
subsystem: ui
tags: [filament, react, recharts, funnel-chart, voter-lifecycle, chart-widget]

# Dependency graph
requires:
  - phase: 22-table-stakes-new-visualizations
    provides: "funnel chart kind already registered in ChartRouter.jsx (FunnelChartKind), RejectionsCountersOverview chrome pattern to mirror"
provides:
  - "VoterHappyPathFunnelChart admin widget: cumulative-subset 4-stage funnel (PENDING_REVIEW -> VERIFIED_CENSUS -> CONFIRMED -> VOTED)"
  - "VoterLifecycleBranchCountersOverview admin widget: StatsOverviewWidget with 6 branch/terminal VoterStatus counters"
  - "Both widgets registered on Admin dashboard, real-browser tested"
affects: [23-differentiator-visualizations, future-voter-lifecycle-reporting]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cumulative-subset happy-path funnel: each stage's status set is a strict subset of the previous stage's status set, so counts are monotonically non-increasing by construction"

key-files:
  created:
    - app/Filament/Widgets/VoterHappyPathFunnelChart.php
    - app/Filament/Widgets/VoterLifecycleBranchCountersOverview.php
    - tests/Browser/VoterHappyPathFunnelChartTest.php
  modified:
    - app/Providers/Filament/AdminPanelProvider.php

key-decisions:
  - "Happy path is 4 stages only (PENDING_REVIEW, VERIFIED_CENSUS, CONFIRMED, VOTED), deliberately excluding VERIFIED_REGISTRADURIA/VERIFIED_CALL per D-01 - a voter reaching CONFIRMED via those paths still counts cumulatively at each subset stage without ever having the literal VERIFIED_CENSUS status"
  - "Branch/terminal states (REJECTED_CENSUS, REJECTED_OUT_OF_SCOPE, CENSUS_NOT_FOUND, DUPLICATE, CORRECTION_REQUIRED, DID_NOT_VOTE) render as a separate counter row widget, never forced into the funnel shape (D-02/D-03)"

patterns-established: []

requirements-completed: [VIZ-06]

# Metrics
duration: 25min
completed: 2026-08-21
---

# Phase 23 Plan 02: Voter Happy-Path Funnel Summary

**Admin-only campaign-wide funnel showing cumulative-subset counts through the Voter lifecycle's happy path (PENDING_REVIEW → VERIFIED_CENSUS → CONFIRMED → VOTED), with a separate StatsOverviewWidget counter row for the 6 branch/terminal VoterStatus states, both reusing the existing `funnel` chart kind and `VoterStatus` enum metadata with zero new frontend code.**

## Performance

- **Duration:** ~25 min (including stale-worktree recovery)
- **Started:** 2026-08-21T10:39:24-05:00
- **Completed:** 2026-08-21T10:52:04-05:00
- **Tasks:** 3
- **Files modified:** 4 (2 created widgets, 1 registration edit, 1 new Browser test)

## Accomplishments
- `VoterHappyPathFunnelChart` computes each stage's count as "voters whose current status is stage N or any later happy-path stage" (cumulative subset, not a `ValidationHistory` traversal), reusing the `funnel` chart kind already registered in `ChartRouter.jsx` since Phase 22 — zero new frontend JS/JSX required.
- `VoterLifecycleBranchCountersOverview` is a plain Filament `StatsOverviewWidget` (not a React chart) showing the 6 branch/terminal `VoterStatus` states as clickable counters linking to filtered `VoterResource` table views, reusing `VoterStatus`'s own `getLabel()`/`getDescription()`/`getIcon()`/`getColor()` methods verbatim.
- Both widgets registered on the Admin dashboard directly after `CoordinatorTeamStackedBarChart` and before `BirthdayWidget`, matching the plan's registration-order convention.
- New Browser test (`VoterHappyPathFunnelChartTest.php`) proves both widgets render with real `VoterStatus` data (a `PENDING_REVIEW`, a `VOTED`, and a `REJECTED_CENSUS` voter) with zero JavaScript console errors.

## Task Commits

Each task was committed atomically:

1. **Task 1: VoterHappyPathFunnelChart.php** - `a872fa6` (feat)
2. **Task 2: VoterLifecycleBranchCountersOverview.php** - `0c54ec4` (feat)
3. **Task 3: Register on Admin dashboard and Browser test** - `564c294` (feat)

**Plan metadata:** (this commit) `docs(23-02): complete voter happy-path funnel plan`

## Files Created/Modified
- `app/Filament/Widgets/VoterHappyPathFunnelChart.php` - Cumulative-subset 4-stage funnel `ChartWidget`, `getChartKind()` returns `'funnel'`
- `app/Filament/Widgets/VoterLifecycleBranchCountersOverview.php` - `StatsOverviewWidget` with 6 branch/terminal `VoterStatus` counters, each linking to a filtered `VoterResource::index` view
- `app/Providers/Filament/AdminPanelProvider.php` - Registered both widgets on the Admin dashboard, alphabetized imports
- `tests/Browser/VoterHappyPathFunnelChartTest.php` - Real-Chromium test proving funnel + counters render with real `VoterStatus` data, zero JS errors

## Decisions Made
None beyond what was already specified in `23-CONTEXT.md` (D-01 through D-04) — plan executed exactly as written, following the interfaces given verbatim (`CallContactabilityFunnelChart.php` shape for the funnel, `RejectionsCountersOverview.php` chrome for the counter row).

## Deviations from Plan

None — plan executed exactly as written. All 3 tasks matched their literal `<action>` blocks verbatim (file contents, registration placement, test content).

### Worktree Environment Recovery (not a plan deviation, standard recurring workaround)

This worktree (`agent-abe62690cc6b5f47b`) was 89 commits behind `main` at session start — missing the entire Phase 23 planning corpus (including this plan's own `23-02-PLAN.md`) plus `.env`, `vendor/`, `node_modules/`, `public/build/` — the same recurring worktree-staleness class documented repeatedly in `STATE.md` across nearly every prior phase's plan executions. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD refs/heads/main`), `git merge --ff-only refs/heads/main`, copied `.env` and `public/build/` from the main checkout, `composer install --no-interaction`, `npm install` (reverted a spurious `package-lock.json` `name` field change afterward, matching prior-phase precedent). `php artisan migrate:status` showed zero pending migrations — the shared `sigma_betha_backup` DB was already current. No `gsd-tools` CLI state/roadmap commands were run against this recurring `findProjectRoot()` bug in this session; STATE.md/ROADMAP.md/REQUIREMENTS.md were updated by hand-editing this worktree's own copies directly per the established precedent.

## Issues Encountered
None — the Browser test passed on the first run (1 passed, 5 assertions, ~12s).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
VIZ-06 is fully shipped: admin-only happy-path funnel + branch/terminal counter row, both live on the Admin dashboard with real-browser proof. No blockers for the remaining Phase 23 plans (23-01, 23-03 through 23-05), which build independent widgets (Sankey, treemap, heatmap, stacked-area) on the same Admin dashboard/panel infrastructure.

---
*Phase: 23-differentiator-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

All 4 claimed files found on disk (`app/Filament/Widgets/VoterHappyPathFunnelChart.php`, `app/Filament/Widgets/VoterLifecycleBranchCountersOverview.php`, `tests/Browser/VoterHappyPathFunnelChartTest.php`, `app/Providers/Filament/AdminPanelProvider.php`). All 3 task commits found in git history (`a872fa6`, `0c54ec4`, `564c294`).
