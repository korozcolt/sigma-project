---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 02
subsystem: ui
tags: [react, recharts, motion, filament, blade, alpine]

# Dependency graph
requires:
  - phase: 21-migrate-existing-charts-to-react-recharts (plan 01)
    provides: ChartRouter, chartjs-adapter's isChartDataEmpty, D-03 palette, 4 Recharts kind components
provides:
  - "ChartCard.jsx rewritten as the shared MonoCharts chrome shell (error/empty/entrance-animation/14px inner chart-area) wrapping ChartRouter, with emptyReason-aware empty-state copy"
  - "react-chart.blade.php generalized to read each widget's real getHeading()/getDescription() instead of a hardcoded PoC string, plus data-chart-kind/data-question-id test-scoping attributes"
affects: [21-03, 21-04, 21-05, 21-06, 21-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ChartCard kind==='poc' transitional branch preserves Phase 20's exact PoC testids/copy until Phase 21's final cleanup plan (21-07) deletes ReactIslandPocWidget"
    - "Empty-state copy selected via data.emptyReason ('no_campaign' -> distinct copy, absent/other -> default zero-records copy) — read-only in this plan, producer is Plan 21-05's sparkline widgets"

key-files:
  created: []
  modified:
    - resources/js/charts/components/ChartCard.jsx
    - resources/views/filament/widgets/react-chart.blade.php

key-decisions:
  - "Deferred marking MIGR-01/MIGR-02 complete in REQUIREMENTS.md — this plan only builds the shared chrome+blade infra every migrated widget will render through; the requirements' literal wording (specific widgets rendering through the new pipeline) only becomes true once Plans 21-03 through 21-06 actually migrate those widgets. Matches the project's established split-requirement precedent (Phases 05.1, 10-19)."

patterns-established:
  - "Every migrated/new ChartWidget in Plans 21-03 through 21-06 renders through this exact ChartCard.jsx + react-chart.blade.php pair unmodified"

requirements-completed: []

# Metrics
duration: 15min
completed: 2026-08-21
---

# Phase 21 Plan 02: MonoCharts Chrome Shell + Generalized Blade Summary

**Rewrote ChartCard.jsx from a PoC-only hardcoded bar chart into the shared MonoCharts chrome shell (error/empty/entrance-animation states wrapping ChartRouter) and generalized react-chart.blade.php to read each widget's real Filament heading/description with new data-chart-kind/data-question-id test attributes.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-08-21T02:41:24Z (approx, following 21-01 completion)
- **Completed:** 2026-08-21T02:44:06Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `ChartCard.jsx` now wraps `ChartRouter` for every real chart kind, with a 14px-radius `#f4f4f6`/`#0f0f10` chart-area container, `duration:0.35 ease:[0,0,0.2,1]` entrance animation, and an empty state that distinguishes `no_campaign` from the default zero-records copy via `data.emptyReason`.
- The transitional `kind === 'poc'` branch stays byte-compatible with Phase 20's `ReactIslandPocWidgetTest.php` (same testids, same copy) — verified passing unchanged.
- `react-chart.blade.php` now calls `$this->getHeading()`/`$this->getDescription()` dynamically instead of a hardcoded `"React Island PoC"` string, and exposes `data-chart-kind` (always) and `data-question-id` (conditional, for `SurveyResultsWidget`'s Plan 21-04 test) for Browser test scoping.

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite ChartCard.jsx as the shared MonoCharts chrome shell** - `a823c9d` (feat)
2. **Task 2: Generalize react-chart.blade.php heading/description and add test-scoping attributes** - `580547a` (feat)

**Plan metadata:** (this commit) `docs(21-02): complete plan`

## Files Created/Modified
- `resources/js/charts/components/ChartCard.jsx` - Rewritten as the MonoCharts chrome shell wrapping ChartRouter, with error/empty/entrance-animation states and emptyReason-aware empty-state copy
- `resources/views/filament/widgets/react-chart.blade.php` - Generalized heading/description, added data-chart-kind/data-question-id attributes

## Decisions Made
- Deferred marking MIGR-01/MIGR-02 complete — see `key-decisions` above and `.planning/REQUIREMENTS.md` (left `Pending`, this plan only builds shared infra, not the actual widget migrations).

## Deviations from Plan

None - plan executed exactly as written (literal code blocks from the plan's `<action>` sections were used verbatim).

## Issues Encountered

None. `npm run build` succeeded cleanly, `php artisan test tests/Browser/ReactIslandPocWidgetTest.php` passed unchanged (1 passed, 4 assertions), and the full `php artisan test tests/Browser/` suite passed (9 passed, 31 assertions) confirming no regression to any other Browser test.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

`ChartCard.jsx` and `react-chart.blade.php` are now the stable shared infra every migrated/new widget in Plans 21-03 through 21-06 will render through unmodified. No blockers for Wave 3 (widget migration plans).

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-21*

## Self-Check: PASSED

- FOUND: resources/js/charts/components/ChartCard.jsx
- FOUND: resources/views/filament/widgets/react-chart.blade.php
- FOUND commit: a823c9d
- FOUND commit: 580547a
