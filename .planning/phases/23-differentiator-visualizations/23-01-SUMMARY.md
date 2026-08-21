---
phase: 23-differentiator-visualizations
plan: 01
subsystem: ui
tags: [react, recharts, chartjs-adapter, filament-widgets, vite]

# Dependency graph
requires:
  - phase: 21-migrate-existing-charts-to-react-recharts
    provides: ChartRouter.jsx dispatch pattern, chartjs-adapter.js data-shaping helpers, rankedMonochromeFill palette, ChartTooltip.jsx, ChartCard.jsx empty-state mechanism
  - phase: 22-table-stakes-new-visualizations
    provides: established "shared chart-kind library first" plan-ordering pattern (kind components before PHP widgets consume them)
provides:
  - SankeyChart.jsx (Recharts native Sankey wrapper, {nodes, links} shape)
  - TreemapChart.jsx (Recharts type=nest drill-down with per-level sibling-rank fills)
  - HeatmapChart.jsx (hand-rolled CSS-grid heatmap with manually-positioned tooltip)
  - StackedAreaChart.jsx (Area variant of StackedBarChart)
  - ChartRouter.jsx wired with sankey/treemap/heatmap/stacked-area kinds
  - isChartDataEmpty() sankey/treemap/heatmap data-shape branches
  - ChartCard.jsx EMPTY_STATE_COPY keys: no_happy_path_voters, no_transitions, no_rejections
affects: [23-02, 23-03, 23-04, 23-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "New chart kinds register in ChartRouter.jsx's KIND_COMPONENTS map, and any kind with a data shape other than {labels, datasets} MUST add an isChartDataEmpty() branch before the labels/datasets fallback, or it will always report empty"
    - "Recharts Treemap type=nest nestIndexContent must be passed as a function (not a JSX element) — passing an element hits a real Recharts internal bug where the function/else branch unconditionally overwrites element-based content back to the plain node name"
    - "Charts with no native chart-context wrapper (e.g. a CSS-grid heatmap) cannot use Recharts' <Tooltip> (it reads Recharts' internal Redux store) — tooltip must be manually positioned via onMouseMove + useState"

key-files:
  created:
    - resources/js/charts/components/SankeyChart.jsx
    - resources/js/charts/components/TreemapChart.jsx
    - resources/js/charts/components/HeatmapChart.jsx
    - resources/js/charts/components/StackedAreaChart.jsx
  modified:
    - resources/js/charts/ChartRouter.jsx
    - resources/js/charts/lib/chartjs-adapter.js
    - resources/js/charts/components/ChartCard.jsx

key-decisions:
  - "Sankey deliberately does not use rankedMonochromeFill — it's a flow diagram, not a ranked list, per 23-UI-SPEC.md's structural-color note"
  - "Treemap tiles are pre-decorated with __siblingIndex/__siblingTotal before being passed to Recharts, since Recharts spreads the original node object into content's nodeProps but exposes no sibling-count API directly"
  - "HeatmapChart cells carry data-caller-id/data-hour attributes specifically so later plans' Browser tests can target a specific cell for a mousemove dispatch, since the tooltip only appears on hover with no static per-cell text"

patterns-established:
  - "Pattern: any chart kind with a non-labels/datasets data shape needs its own isChartDataEmpty() branch, added before the generic labels/datasets fallback"

requirements-completed: [VIZ-06, VIZ-07, VIZ-08, VIZ-09, VIZ-10]

# Metrics
duration: 20min
completed: 2026-08-21
---

# Phase 23 Plan 01: Differentiator Chart-Kind Components Summary

**Built 4 new React/Recharts chart-kind components (sankey, treemap, heatmap, stacked-area), wired them into ChartRouter.jsx, and fixed a real empty-state bug in isChartDataEmpty() that would have made every sankey/treemap/heatmap widget always show "Sin datos" regardless of real data.**

## Performance

- **Duration:** 20 min
- **Started:** 2026-08-21T15:31:00Z
- **Completed:** 2026-08-21T15:51:24Z
- **Tasks:** 3
- **Files modified:** 7 (4 created, 3 modified)

## Accomplishments
- SankeyChart.jsx, TreemapChart.jsx, HeatmapChart.jsx, StackedAreaChart.jsx all created matching the plan's literal interface contract
- ChartRouter.jsx's KIND_COMPONENTS now dispatches `sankey`/`treemap`/`heatmap`/`stacked-area` to real components instead of silently falling back to BarChartKind
- `isChartDataEmpty()` now recognizes the sankey `{nodes, links}`, treemap `{tree}`, and heatmap `{cells}` data shapes instead of only checking `labels`/`datasets` (which are absent from all three)
- `npm run build` succeeds and produces a Vite manifest with all 4 new components bundled

## Task Commits

Each task was committed atomically:

1. **Task 1: SankeyChart.jsx + StackedAreaChart.jsx** - `d9a6ffa` (feat)
2. **Task 2: TreemapChart.jsx + HeatmapChart.jsx** - `e3b7921` (feat)
3. **Task 3: Wire ChartRouter.jsx + chartjs-adapter.js + ChartCard.jsx** - `38e2634` (feat)

_Note: This plan has `commit_docs: true` in config but per parallel-executor instructions all commits used `--no-verify`; the orchestrator validates hooks once after all agents complete._

## Files Created/Modified
- `resources/js/charts/components/SankeyChart.jsx` - Recharts native Sankey wrapper consuming `{nodes, links}` shape, no ranked-fill (structural flow diagram, not a ranked list)
- `resources/js/charts/components/TreemapChart.jsx` - Recharts `type="nest"` drill-down wrapper; pre-decorates nodes with sibling index/total for per-level `rankedMonochromeFill`; `nestIndexContent` passed as a function (required — Recharts overwrites element-based content otherwise) with a `'Todos'` root breadcrumb
- `resources/js/charts/components/HeatmapChart.jsx` - Hand-rolled CSS-grid heatmap (rows=callers, columns=business hours), no Recharts primitive exists for this; manually-positioned tooltip via `onMouseMove`/`useState`; distinct no-data cell shade; `data-caller-id`/`data-hour` attributes on every cell
- `resources/js/charts/components/StackedAreaChart.jsx` - `Area`-instead-of-`Bar` variant of `StackedBarChart.jsx`, reuses `toSeriesRows()`/`rankedMonochromeFill()`/shared `stackId`
- `resources/js/charts/ChartRouter.jsx` - 4 new imports + 4 new `KIND_COMPONENTS` entries
- `resources/js/charts/lib/chartjs-adapter.js` - `isChartDataEmpty()` gains `sankey`/`treemap`/`heatmap` branches before the `labels`/`datasets` fallback
- `resources/js/charts/components/ChartCard.jsx` - `EMPTY_STATE_COPY` gains `no_happy_path_voters`, `no_transitions`, `no_rejections` (does not duplicate the already-existing `no_voters`/`no_calls`, reused by 23-04/23-05 per the UI spec)

## Decisions Made
- Followed the plan's literal code blocks verbatim for all 4 components and all 3 wiring edits — no deviation from the specified implementation
- No PHP/Blade files touched; this plan is the shared frontend interface contract that 23-02 through 23-05 build PHP widgets against, matching the established Phase 21/22 "shared chart-kind library first" pattern

## Deviations from Plan

None - plan executed exactly as written. All code (component implementations, ChartRouter wiring, isChartDataEmpty branches, EMPTY_STATE_COPY keys) matches the plan's literal `<action>` blocks.

## Issues Encountered

- **Worktree staleness (recurring, documented extensively elsewhere in STATE.md):** This worktree (`agent-a031d136861034499`) was checked out at a commit that predated the entire Phase 20-23 planning corpus and Phase 20-22 implementation work (missing `resources/js/charts/`, `23-01-PLAN.md` itself, etc.), plus `vendor/`, `node_modules/`, `.env`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD refs/heads/main`), `git merge --ff-only refs/heads/main`, copied `.env` from the main checkout, symlinked `node_modules/` and `vendor/` from the main checkout (both are gitignored, shared dependency trees, no plan-specific changes to either) rather than a full reinstall, since this plan's own verify commands need `esbuild`/`vite`/`npm run build` to succeed and the main checkout already had them installed.
- **Plan's verify commands hardcoded the main checkout's absolute path** (`cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project"`) instead of the worktree path. Per this session's parallel-executor sandboxing (which blocks `cd` to a different git worktree), ran the equivalent `esbuild`/grep/`npm run build` commands directly from the current working directory (already the worktree root) instead of following the literal `cd` prefix. Same effective verification, no path deviation in outcome.
- The first `npm run build` attempt failed with `Can't resolve '../../../vendor/filament/filament/resources/css/theme.css'` — a pre-existing, unrelated missing-`vendor/`-directory issue (this plan makes no PHP changes) caused by the worktree staleness above. Resolved by the `vendor/` symlink described above; a second `npm run build` run succeeded cleanly (manifest.json + all expected assets emitted, zero errors, only a pre-existing chunk-size-warning unrelated to this plan's files).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All 4 new chart kinds (`sankey`, `treemap`, `heatmap`, `stacked-area`) are registered in `ChartRouter.jsx` and their empty-state detection is correct, unblocking 23-02 through 23-05's PHP widgets from being built against a working frontend contract
- No blockers or concerns for downstream plans in this phase

## Self-Check: PASSED

All 7 created/modified files confirmed present on disk (SankeyChart.jsx, TreemapChart.jsx, HeatmapChart.jsx, StackedAreaChart.jsx, ChartRouter.jsx, chartjs-adapter.js, ChartCard.jsx) and this SUMMARY.md itself. All 3 task commits (`d9a6ffa`, `e3b7921`, `38e2634`) confirmed present in `git log`.
