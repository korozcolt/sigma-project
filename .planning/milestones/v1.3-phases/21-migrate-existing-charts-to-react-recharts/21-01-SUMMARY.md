---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 01
subsystem: ui
tags: [recharts, react, chart-adapter, monochrome-palette, es-CO-formatting]

# Dependency graph
requires:
  - phase: 20-react-island-infrastructure
    provides: React island bridge (main.jsx Alpine bridge, ChartCard.jsx PoC, react-chart.blade.php shared view)
provides:
  - "chartjs-adapter.js: toSeriesRows/toNameValueRows/isChartDataEmpty pure functions reshaping PHP Chart.js-shaped getData() payloads for Recharts, entirely client-side"
  - "palette.js: rankedMonochromeFill() implementing D-03's single-accent + descending-zinc-opacity ramp"
  - "formatters.js: formatNumber() applying es-CO locale formatting"
  - "ChartTooltip.jsx: shared themed tooltip component (8px radius, 14px Body text per UI-SPEC)"
  - "LineChart.jsx, BarChart.jsx, PieChart.jsx, SparklineChart.jsx: 4 real Recharts kind components"
  - "ChartRouter.jsx: kind -> component dispatcher (line/bar/pie/sparkline/poc), poc aliased to SparklineChart transitionally"
affects: [21-02-chrome-refactor, 21-03-through-21-06-widget-migrations, 21-07-poc-cleanup]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Chart.js-shape-to-Recharts adapter: PHP getData() bodies stay frozen (MIGR-01); all row-shape reshaping happens in JS via toSeriesRows/toNameValueRows"
    - "D-03 monochrome ramp: index 0 (or total<=1) gets accent #f97316, all other indices get a zinc rgba ramp scaled from 100% down to 15% opacity by rank"
    - "toNameValueRows sorts descending by value before ranking, so BarChart/PieChart always assign accent to the largest segment regardless of getData()'s emission order"

key-files:
  created:
    - resources/js/charts/lib/chartjs-adapter.js
    - resources/js/charts/lib/palette.js
    - resources/js/charts/lib/formatters.js
    - resources/js/charts/components/ChartTooltip.jsx
    - resources/js/charts/components/LineChart.jsx
    - resources/js/charts/components/BarChart.jsx
    - resources/js/charts/components/PieChart.jsx
    - resources/js/charts/components/SparklineChart.jsx
    - resources/js/charts/ChartRouter.jsx
  modified: []

key-decisions:
  - "None beyond the plan's own literal code — this is a contract-defining plan, no PHP/Blade/main.jsx/ChartCard.jsx changes"

patterns-established:
  - "All chart-kind components accept a uniform {kind, data, theme} contract; ChartRouter is the single dispatch point future widget migrations (21-02+) wire into"
  - "Tooltip radius uses the explicit rounded-[8px] arbitrary-value class, never rounded-lg, since this project's theme.css redefines --radius-lg to 14px"

requirements-completed: [MIGR-01, MIGR-02]

# Metrics
duration: ~5min
completed: 2026-08-20
---

# Phase 21 Plan 01: Chart Library Foundation Summary

**Chart.js-to-Recharts data adapter, D-03 monochrome palette, es-CO formatter, and 4 real Recharts kind components (line/bar/pie/sparkline) dispatched via a new `ChartRouter`, with zero PHP-side reshaping.**

## Performance

- **Duration:** ~5 min (commit-to-commit)
- **Started:** 2026-08-20T16:48Z (session start)
- **Completed:** 2026-08-20T16:52Z
- **Tasks:** 2
- **Files modified:** 9 (all new)

## Accomplishments
- Built the exact data/color contract (`chartjs-adapter.js`, `palette.js`, `formatters.js`) that every later Phase 21 plan (widget migrations, chrome refactor, sparkline widgets) consumes without re-deriving it
- Built 4 real Recharts kind components adapted from MonoCharts research patterns: multi-series `LineChart`, ranked single-series `BarChart`, donut `PieChart`, and inline `SparklineChart`
- Built `ChartRouter.jsx`, the single `kind -> component` dispatcher `ChartCard.jsx` will be rewired to consume in Plan 21-02
- Confirmed via `git status --short` and a direct diff check that no existing file (`main.jsx`, `ChartCard.jsx`, any PHP/Blade file) was touched — this plan is contract-only, as specified

## Task Commits

1. **Task 1: Build the data adapter, D-03 monochrome palette, and es-CO formatter** - `ceeafc5` (feat)
2. **Task 2: Build the 4 Recharts kind components, shared tooltip, and ChartRouter dispatcher** - `d09fb59` (feat)

**Plan metadata:** (this commit) `docs(21-01): complete plan`

## Files Created/Modified
- `resources/js/charts/lib/chartjs-adapter.js` - `toSeriesRows`, `toNameValueRows`, `isChartDataEmpty` pure functions
- `resources/js/charts/lib/palette.js` - `rankedMonochromeFill()` D-03 ramp generator
- `resources/js/charts/lib/formatters.js` - `formatNumber()` es-CO locale formatting
- `resources/js/charts/components/ChartTooltip.jsx` - shared themed tooltip
- `resources/js/charts/components/LineChart.jsx` - multi-series line, last dataset = accent
- `resources/js/charts/components/BarChart.jsx` - ranked single-series bar with per-`Cell` fill
- `resources/js/charts/components/PieChart.jsx` - donut with centered total display
- `resources/js/charts/components/SparklineChart.jsx` - inline single-row pattern
- `resources/js/charts/ChartRouter.jsx` - `kind -> component` dispatcher

## Decisions Made
None - followed plan as specified. All file contents match the plan's literal `<action>` code blocks verbatim.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed the plan's own literal Task 2 verify script (esbuild multi-entry-point call)**
- **Found during:** Task 2 verification
- **Issue:** The plan's `<verify><automated>` esbuild smoke-check script called `esbuild.buildSync({ entryPoints: [...6 files], bundle: false, write: false, ... })` without an `outdir`. esbuild's API requires `outdir` whenever there are multiple entry points, even with `write: false` — the script as written always throws `Must use "outdir" when there are multiple input files"` regardless of whether the 6 `.jsx` files are valid.
- **Fix:** Added `outdir: 'out'` to the `buildSync` call (esbuild computes output paths internally for validation but never writes to disk since `write: false`). This is a verification-script-only change — no production file was touched.
- **Files modified:** None (ad hoc verification command only, not committed as a file)
- **Verification:** Corrected script printed `ESBUILD_SYNTAX_OK`, confirming all 6 new `.jsx` files parse as valid JSX/ESM
- **Committed in:** N/A (not a file change)

**2. Worktree environment setup (not a plan deviation, standard recurring workaround)**
- This worktree (`agent-a50b3fcd1eb6fdab9`) was 5 commits behind `main` at session start — missing all of Phase 21's planning corpus (`21-CONTEXT.md`, `21-RESEARCH.md`, `21-UI-SPEC.md`, all 7 `21-*-PLAN.md` files) plus `vendor/`, `.env`, `node_modules/`, `public/build/` — the same recurring class documented repeatedly in `.planning/STATE.md`'s decision log across nearly every prior phase. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, then symlinked `node_modules/` and `vendor/` from the main checkout (rather than a full reinstall, since this plan makes no dependency changes) and copied `.env`. `npx vite build --mode production` succeeded cleanly against the symlinked `node_modules`, confirming `recharts`/`vite`/`esbuild` were already present at the versions this plan needs.

---

**Total deviations:** 1 auto-fixed (1 blocking, verify-script-only), plus the standard recurring worktree-staleness workaround.
**Impact on plan:** No production code deviated from the plan's literal specification. No scope creep.

## Issues Encountered
None beyond the worktree staleness and verify-script fix documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `ChartRouter.jsx` and all 4 kind components are ready for Plan 21-02 to wire into `ChartCard.jsx`/`main.jsx` (the chrome refactor plan) — this plan deliberately left both files untouched
- The `'poc'` kind alias to `SparklineChart` is in place, so Plan 21-02's chrome refactor does not need to touch any PHP widget until Plan 21-07 removes `ReactIslandPocWidget`
- No blockers or concerns for downstream Phase 21 plans

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-20*

## Self-Check: PASSED

All 9 created files verified present on disk. Both task commits (`ceeafc5`, `d09fb59`) verified present in `git log`.
