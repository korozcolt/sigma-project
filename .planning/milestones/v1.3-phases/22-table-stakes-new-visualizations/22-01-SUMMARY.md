---
phase: 22-table-stakes-new-visualizations
plan: 01
subsystem: ui
tags: [react, recharts, vite, chart-library]

# Dependency graph
requires:
  - phase: 21-migrate-existing-charts-to-react-recharts
    provides: "ChartRouter.jsx, chartjs-adapter.js, ChartCard.jsx, palette.js, formatters.js, ChartTooltip.jsx — the base React/Recharts chart-kind library (line/bar/pie/sparkline)"
provides:
  - "toOrderedRows() row adapter — order-preserving sibling to toNameValueRows() for kinds that must never be re-ranked by value"
  - "4 new Recharts chart-kind components: StackedBarChart, FunnelChart, GaugeChart, HistogramChart"
  - "ChartRouter.jsx routing for 'stacked-bar' | 'funnel' | 'gauge' | 'histogram' kinds"
  - "5 new Spanish empty-state copy entries in ChartCard's EMPTY_STATE_COPY (no_voters, no_coordinators, no_calls, no_messages, no_survey_responses)"
affects: [22-02, 22-04, 22-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Order-preserving vs. ranked row adapters: toOrderedRows() (no sort) for funnel/histogram where PHP-emitted label order is semantic; toNameValueRows() (sorted desc) for bar/pie where rank/size ordering is semantic"
    - "Gauge payload shape is {labels, datasets, min, max} (not {value, min, max}) so the existing unmodified isChartDataEmpty() can detect the gauge's empty state via its labels.length === 0 branch"
    - "Horizontal-scroll wrapper (overflow-x-auto + minWidth sized to bar count) for stacked-bar to handle an unbounded coordinator count without illegible compression"

key-files:
  created:
    - resources/js/charts/components/StackedBarChart.jsx
    - resources/js/charts/components/FunnelChart.jsx
    - resources/js/charts/components/GaugeChart.jsx
    - resources/js/charts/components/HistogramChart.jsx
  modified:
    - resources/js/charts/lib/chartjs-adapter.js
    - resources/js/charts/components/ChartCard.jsx
    - resources/js/charts/ChartRouter.jsx

key-decisions:
  - "Built a dedicated 'histogram' kind using toOrderedRows() instead of reusing 'bar' (which sorts by value via toNameValueRows()) — deviating from 22-RESEARCH.md Pattern 4's literal suggestion, per the plan's own explicit critical design note, to avoid silently reordering a SCALE histogram's 1-5 buckets by frequency"
  - "GaugeChart reads data.datasets[0].data[0], never data.value, so the unmodified isChartDataEmpty() correctly detects the gauge's empty state"

requirements-completed: []  # VIZ-01..05 are NOT closed by this plan alone — this is pure JS/interface foundation work; the PHP ChartWidget subclasses that actually consume these kinds are built in downstream plans 22-02/22-04/22-05

# Metrics
duration: ~25min
completed: 2026-08-21
---

# Phase 22 Plan 01: Chart-Kind Library Foundation Summary

**4 new Recharts chart-kind components (stacked-bar, funnel, gauge, histogram) registered in ChartRouter, plus an order-preserving row adapter and 5 new Spanish empty-state copy keys — the shared JS contract every Phase 22 PHP widget plan will consume with zero further JS changes.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-08-21T13:47:20Z
- **Completed:** 2026-08-21T13:49:38Z
- **Tasks:** 2
- **Files modified:** 7 (2 modified, 4 created, 1 modified for routing)

## Accomplishments
- `toOrderedRows()` adapter added to `chartjs-adapter.js` — deliberately unsorted sibling to `toNameValueRows()`, so funnel stages and histogram buckets preserve PHP-emitted order instead of being re-ranked by value
- 4 new Recharts components built exactly per plan spec: `StackedBarChart` (horizontal-scroll, stacked by coordinator), `FunnelChart` (native Funnel primitive, order-preserving), `GaugeChart` (2-segment semicircle Pie), `HistogramChart` (order-preserving bar, distinct from ranked `bar`)
- `ChartRouter.jsx` now routes 8 total kinds (4 existing + 4 new): `line`, `bar`, `pie`, `sparkline`, `stacked-bar`, `funnel`, `gauge`, `histogram`
- `ChartCard.jsx`'s `EMPTY_STATE_COPY` extended with all 5 Phase 22 Spanish empty-state keys (`no_voters`, `no_coordinators`, `no_calls`, `no_messages`, `no_survey_responses`), 2 existing keys preserved unchanged

## Task Commits

Each task was committed atomically:

1. **Task 1: Add toOrderedRows() adapter + extend ChartCard empty-state copy** - `dc830a8` (feat)
2. **Task 2: Build 4 new chart-kind components and register them in ChartRouter** - `5047528` (feat)

**Plan metadata:** (pending — see final commit below)

## Files Created/Modified
- `resources/js/charts/lib/chartjs-adapter.js` - Added `toOrderedRows()` export (order-preserving row adapter)
- `resources/js/charts/components/ChartCard.jsx` - `EMPTY_STATE_COPY` extended with 5 new Spanish keys
- `resources/js/charts/components/StackedBarChart.jsx` - New stacked-bar Recharts component with horizontal-scroll wrapper
- `resources/js/charts/components/FunnelChart.jsx` - New funnel Recharts component (native Funnel/FunnelChart primitive)
- `resources/js/charts/components/GaugeChart.jsx` - New gauge Recharts component (2-segment semicircle Pie)
- `resources/js/charts/components/HistogramChart.jsx` - New order-preserving histogram bar chart
- `resources/js/charts/ChartRouter.jsx` - Registers all 4 new kinds in `KIND_COMPONENTS`

## Decisions Made
- Followed the plan's literal code blocks verbatim for all 4 new components, the adapter function, and the `EMPTY_STATE_COPY` extension — no production-code deviation from the plan's `<interfaces>`/`<action>` specification.
- Confirmed via `npx vite build --mode production`: 1032 modules transformed, manifest written, zero build errors/warnings about unresolved imports.

## Deviations from Plan

None in production code - plan executed exactly as written (all 4 component files and both adapter/copy edits match the plan's literal code blocks verbatim).

### Environment-only deviations (not production code)

**1. Stale worktree — 68 commits behind main**
- **Found during:** Pre-execution setup
- **Issue:** This worktree (`agent-a7d0f1401f0a15bf3`) was 68 commits behind `main` at session start, missing the entire Phase 22 planning corpus (including this plan's own `22-01-PLAN.md`), `.env`, `vendor/`, `node_modules/`, `public/build/` — the same recurring class documented repeatedly across nearly every prior phase's SUMMARY.md in this milestone (`gsd-tools`' `findProjectRoot()` bug misdirecting worktree state).
- **Fix:** Confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` from the main checkout. Since `package.json`/`package-lock.json` are byte-identical to the main checkout (confirmed via `diff`) and this plan makes zero dependency changes, symlinked `node_modules/` and `vendor/` from the main checkout instead of a full reinstall (same precedent as Phase 21 Plan 01).
- **Verification:** `npx vite build --mode production` succeeded cleanly against the symlinked `node_modules`, confirming `recharts`/`vite`/`esbuild` were already present at the versions this plan needs.

**2. Acceptance-criteria grep counts off-by-one (plan-authoring artifact, not a code issue)**
- **Found during:** Task 2 verification
- **Issue:** The plan's stated acceptance criteria (`grep -c "toSeriesRows" StackedBarChart.jsx` returns `1`, similarly for `toOrderedRows` in FunnelChart/HistogramChart) actually return `2` — both the `import { toSeriesRows } ...` line and the `const rows = toSeriesRows(...)` usage line contain the string, since `grep -c` counts matching lines, not occurrences. This is inherent to the plan's own literal "exact content" code blocks, not a deviation introduced during execution.
- **Fix:** None needed — code matches the plan's literal specification exactly. Verified via the more meaningful criteria instead: files exist, `ChartRouter.jsx` registers all 4 kinds, neither `FunnelChart.jsx` nor `HistogramChart.jsx` reuses the sorting `toNameValueRows()` adapter (0 matches, as required), gauge reads `datasets[0].data[0]`.
- **Impact:** None — purely a documentation/acceptance-criteria wording nuance in the plan, correctly identified during self-check rather than treated as a real defect.

---

**Total deviations:** 0 production-code deviations. 2 environment/process notes (worktree staleness workaround, acceptance-criteria wording nuance) — both standard/expected, no scope creep.

## Issues Encountered
None beyond the standard stale-worktree setup documented above.

## Next Phase Readiness
- The full JS chart-kind contract for Phase 22 is now in place: `stacked-bar`, `funnel`, `gauge`, `histogram` all resolve to real Recharts components via `ChartRouter`, `toOrderedRows()` is available for any future order-preserving kind, and all 5 Phase 22 empty-state Spanish copy keys exist in `ChartCard`.
- Downstream plans 22-02, 22-04, 22-05 (the PHP `ChartWidget` subclasses that pass `kind: 'stacked-bar' | 'funnel' | 'gauge' | 'histogram'`) can now proceed with zero further JS changes required, per this plan's explicit interface-first design.
- No blockers.

---
*Phase: 22-table-stakes-new-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

All 8 files confirmed present on disk (7 code files + this SUMMARY.md); both task commits (`dc830a8`, `5047528`) confirmed present in git history.
