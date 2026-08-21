---
phase: 23-differentiator-visualizations
plan: 04
subsystem: ui
tags: [filament-widgets, recharts, treemap, territorial]

# Dependency graph
requires:
  - phase: 23-differentiator-visualizations
    plan: 01
    provides: TreemapChart.jsx (Recharts type=nest drill-down component) registered in ChartRouter.jsx under the 'treemap' kind
provides:
  - "TerritorialDistributionChart.php returns a 3-level nested {tree} shape (Departamento -> Municipio -> Barrio) instead of a flat top-10 {labels, datasets} bar shape"
  - "Sin barrio fallback leaf for voters with a null neighborhood_id, so they are never silently dropped from municipio totals"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "3-level LEFT JOIN aggregation (departments -> municipalities -> LEFT JOIN neighborhoods) grouped into a nested Collection tree via chained ->groupBy()->map() for a Recharts type=nest Treemap consumer"

key-files:
  created: []
  modified:
    - app/Filament/Widgets/TerritorialDistributionChart.php
    - tests/Browser/TerritorialDistributionChartTest.php
    - tests/Feature/DashboardWidgetsTest.php
    - tests/Feature/OwnershipScopedWidgetsTest.php
    - tests/Browser/ArticuladorDashboardWidgetScopingTest.php

key-decisions:
  - "Recharts' nest-mode Treemap resets depth to 0 for the new currentRoot on every click (Treemap.js handleClick() -> computeNode({ depth: 0, ... })), so every drill level renders under .recharts-treemap-depth-1, never a cumulative .recharts-treemap-depth-2 — confirmed by reading node_modules/recharts/es6/chart/Treemap.js directly after the plan's literal depth-2 selector produced a null querySelector"

requirements-completed: [VIZ-08]

# Metrics
duration: 35min
completed: 2026-08-21
---

# Phase 23 Plan 04: Territorial Distribution Treemap Summary

**Replaced TerritorialDistributionChart's flat top-10-municipios bar list with a 3-level drill-down treemap (Departamento -> Municipio -> Barrio) in the exact same widget slot, using a LEFT JOIN aggregation that buckets voters with no assigned neighborhood into an explicit "Sin barrio" leaf instead of dropping them.**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-08-21T20:32:00Z
- **Completed:** 2026-08-21T21:07:00Z
- **Tasks:** 2 (plan) + 1 deviation-fix commit
- **Files modified:** 5 (2 in plan scope, 3 pre-existing regression fixes)

## Accomplishments
- `TerritorialDistributionChart::getData()` now runs a single 3-level `LEFT JOIN` aggregation query (`departments` -> `municipalities` -> `LEFT JOIN neighborhoods`) and reshapes the flat result set into a nested `{tree: [{name, children: [{name, children: [{name, value}]}]}]}` structure that `TreemapChart.jsx` consumes directly
- `getChartKind()` now returns `'treemap'`; heading changed from the now-inaccurate "Top 10 Municipios con más Apoyos" to "Distribución Territorial"
- Role-scoping (`LEADER` -> own registered voters, `COORDINATOR`/`AREA_COORDINATOR` -> team via `teamCoordinatorUserIds()`, admin/super_admin -> full campaign) carried over unchanged, verified by the pre-existing `OwnershipScopedWidgetsTest` articulador-scoping test (updated for the new shape, still asserts the same team total)
- Voters with `neighborhood_id = null` bucket into an explicit `"Sin barrio"` leaf tile rather than being silently dropped from their municipio's total
- Widget slot/sort position unchanged (`$sort = 2`), no `AdminPanelProvider.php` registration changes needed
- Rewritten `tests/Browser/TerritorialDistributionChartTest.php` proves a real 2-level drill-down (Departamento click -> Municipio tile appears -> Municipio click -> "Sin barrio" tile appears) against a genuine Chromium session, plus zero JS errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Swap TerritorialDistributionChart.php to the treemap shape** - `a861415` (feat)
2. **Task 2: Rewrite the Browser test for the treemap + drill-down** - `404ea9d` (test)
3. **Deviation fix: update pre-existing tests broken by the shape/heading change** - `9b35082` (fix)

_Note: This plan has `commit_docs: true` in config but per parallel-executor instructions all commits used `--no-verify`; the orchestrator validates hooks once after all agents complete._

## Files Created/Modified
- `app/Filament/Widgets/TerritorialDistributionChart.php` - `getData()` rewritten to a 3-level `LEFT JOIN` aggregation building a nested tree; `getChartKind()` returns `'treemap'`; heading/description text updated
- `tests/Browser/TerritorialDistributionChartTest.php` - fully rewritten; drops the retired `data-chart-kind="bar"` assertion, seeds a null-neighborhood voter, drives 2 real DOM clicks against Recharts' rendered `<rect>` elements to prove the drill-down and the "Sin barrio" fallback
- `tests/Feature/DashboardWidgetsTest.php` - `assertSee('Top 10 Municipios con más Apoyos')` updated to `assertSee('Distribución Territorial')` (Rule 1 — directly broken by Task 1's heading change)
- `tests/Feature/OwnershipScopedWidgetsTest.php` - articulador-scoping assertion rewritten to recursively sum `value` across the new nested `{tree}` shape instead of reading the retired `{datasets: [{data: [...]}]}` shape; same expected total (8), same team-scoping guarantee proven (Rule 1 — directly broken by Task 1's data-shape change)
- `tests/Browser/ArticuladorDashboardWidgetScopingTest.php` - one `assertSee('Top 10 Municipios con más Apoyos')` updated to `assertSee('Distribución Territorial')` (Rule 1 — same heading-text break)

## Decisions Made
- Followed the plan's literal `getData()`/`getChartKind()` code block verbatim — no deviation in the production widget implementation
- The plan's own suggested Task 2 test code used `.recharts-treemap-depth-2 rect` for the second drill-down click. Reading `node_modules/recharts/es6/chart/Treemap.js`'s `handleClick()` directly showed nest-mode Treemap always resets `depth: 0` for the new `currentRoot` on every click, so every drill level (including the second) renders under `.recharts-treemap-depth-1`, never a cumulative `-depth-2`. Fixed the test's second selector to `.recharts-treemap-depth-1 rect` — the test now passes and still proves the same behavior (2-level drill-down + "Sin barrio" fallback).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Plan's own literal test selector (`.recharts-treemap-depth-2`) never matches — fixed to `.recharts-treemap-depth-1`**
- **Found during:** Task 2 (first `php artisan test` run failed with `TypeError: Cannot read properties of null (reading 'dispatchEvent')`)
- **Issue:** The plan's Task 2 code assumed cumulative per-level depth classes (`depth-1` for Departamentos, `depth-2` for Municipios) on the second drill click. Recharts' `Treemap.js` `handleClick()` calls `computeNode({ depth: 0, ... })` for the new `currentRoot` on every click, so each drilled-into level's children are always rendered as `depth-1` relative to that new root, not a global cumulative depth.
- **Fix:** Changed the second click's selector from `.recharts-treemap-depth-2 rect` to `.recharts-treemap-depth-1 rect`, with an inline comment explaining why, citing the exact `Treemap.js` mechanism.
- **Files modified:** `tests/Browser/TerritorialDistributionChartTest.php`
- **Commit:** `404ea9d`

**2. [Rule 1 - Bug] Pre-existing tests broken by this task's own heading/data-shape change**
- **Found during:** Post-task regression sweep (`grep -rln "TerritorialDistributionChart"` across `app`/`tests`, then running the 3 matching pre-existing test files)
- **Issue:** `tests/Feature/DashboardWidgetsTest.php` and `tests/Browser/ArticuladorDashboardWidgetScopingTest.php` both asserted the retired heading text `"Top 10 Municipios con más Apoyos"`; `tests/Feature/OwnershipScopedWidgetsTest.php` read the retired `$data['datasets'][0]['data']` flat-bar shape directly via reflection on `getData()`. All 3 are out-of-scope files per the plan's `files_modified` frontmatter but were directly broken by this plan's own Task 1 change — in scope for a Rule 1 fix, not deferred.
- **Fix:** Updated the two heading-text assertions to `"Distribución Territorial"`; rewrote the `OwnershipScopedWidgetsTest` assertion to recursively sum every leaf `value` across the new nested `{tree}` shape, preserving the exact same team-scoped total (8) and scoping guarantee.
- **Files modified:** `tests/Feature/DashboardWidgetsTest.php`, `tests/Feature/OwnershipScopedWidgetsTest.php`, `tests/Browser/ArticuladorDashboardWidgetScopingTest.php`
- **Commit:** `9b35082`

## Issues Encountered

- **Worktree staleness (recurring, documented extensively elsewhere in STATE.md):** This worktree (`agent-a7ced4d188e33723c`) was 1 merge-commit behind `main` at session start — missing Phase 23's entire planning corpus (including this plan's own `23-04-PLAN.md`), plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install` (reverted a spurious `package-lock.json` `name` field change afterward, matching prior sessions' precedent), `npx playwright install chromium` (already cached, no-op).
- **`public/build/` copied from the main checkout was stale relative to source** — despite being freshly modified-timestamped, it predated Phase 23's `resources/js/charts/` changes (grep for `Treemap`/`Sankey` in the bundled JS returned 0 matches). Ran `npm run build` in this worktree instead of relying on the copy; the resulting bundle correctly includes `Treemap` (confirmed via grep). This is a new variant of the general "copy `public/build` from main" workaround — the main checkout's own local build artifact was itself out of date relative to its own committed source, not just relative to this worktree.
- `php artisan migrate:status` showed zero pending migrations (shared DB already at the latest schema from prior sessions).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- VIZ-08 fully implemented and verified: 3-level treemap, LEFT JOIN + "Sin barrio" fallback, unchanged role-scoping, unchanged widget slot/sort
- No blockers or concerns for downstream plans in this phase

## Self-Check: PASSED

Confirmed `app/Filament/Widgets/TerritorialDistributionChart.php` and `tests/Browser/TerritorialDistributionChartTest.php` present on disk with the expected content. Confirmed all 3 commits (`a861415`, `404ea9d`, `9b35082`) present in `git log --oneline`.
