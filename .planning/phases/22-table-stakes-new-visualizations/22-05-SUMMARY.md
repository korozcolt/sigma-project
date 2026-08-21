---
phase: 22-table-stakes-new-visualizations
plan: 05
subsystem: ui
tags: [filament, chartwidget, recharts, gauge, histogram, survey, livewire]

# Dependency graph
requires:
  - phase: 22-table-stakes-new-visualizations (plan 04)
    provides: AppServiceProvider::PAGE_SCOPED_WIDGETS array with CallContactabilityFunnelChart/MessageDeliveryFunnelChart already registered
  - phase: 22-table-stakes-new-visualizations (plan 01)
    provides: React chart-kind library (GaugeChart.jsx, HistogramChart.jsx, ChartCard.jsx empty-state handling) and react-chart.blade.php bridge view
provides:
  - SurveyScaleGaugeChart ChartWidget (kind 'gauge') reading SurveyMetrics.average_value
  - SurveyScaleHistogramChart ChartWidget (kind 'histogram') reading SurveyMetrics.distribution in ascending scale order
  - EditSurvey::getFooterWidgets() emitting one gauge + one histogram per SCALE question, alongside the existing SurveyResultsWidget instance
  - Both widgets registered in AppServiceProvider::PAGE_SCOPED_WIDGETS and PageScopedWidgetRegistrationTest's regression dataset
affects: [23-differentiator-visualizations, 24-dia-d-live-voting-visualization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "SCALE-question widgets read SurveyMetricsCalculator's precomputed SurveyMetrics row directly (metric_type='question_average') — zero new aggregation logic (D-10)"
    - "histogram data arrays wrapped in array_values() after array_map() to guarantee zero-indexed JSON-array output when source keys are non-zero-based scale values"

key-files:
  created:
    - app/Filament/Widgets/SurveyScaleGaugeChart.php
    - app/Filament/Widgets/SurveyScaleHistogramChart.php
    - tests/Feature/SurveyScaleGaugeHistogramChartDataTest.php
    - tests/Browser/SurveyScaleGaugeHistogramChartTest.php
  modified:
    - app/Filament/Resources/Surveys/Pages/EditSurvey.php
    - app/Providers/AppServiceProvider.php
    - tests/Feature/PageScopedWidgetRegistrationTest.php

key-decisions:
  - "Wrapped SurveyScaleHistogramChart's datasets[0].data in array_values() — array_map() over SurveyMetrics.distribution preserves its non-zero-indexed scale-value keys (1,2,3...), which would otherwise json_encode() as a JS object instead of an array and break the front-end chart"
  - "Removed the plan's literal (but unused) `use Illuminate\\Support\\Collection;` import from EditSurvey.php — flatMap()/filter() on an Eloquent collection need no explicit Collection import, and Pint's no_unused_imports rule fails the build with it present"

patterns-established: []

requirements-completed: [VIZ-05]

# Metrics
duration: 25min
completed: 2026-08-21
---

# Phase 22 Plan 05: SCALE-Question Gauge + Histogram Widgets Summary

**Two new `ChartWidget`s (gauge + histogram) reading `SurveyMetricsCalculator`'s precomputed `average_value`/`distribution` columns directly, wired one-of-each per SCALE question into `EditSurvey`'s footer alongside the existing bar-chart `SurveyResultsWidget`.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-08-21T14:02:00Z (approx, worktree setup)
- **Completed:** 2026-08-21T14:27:00Z
- **Tasks:** 2
- **Files modified:** 7 (2 new widgets, 1 page, 1 provider, 3 test files)

## Accomplishments
- `SurveyScaleGaugeChart` (kind `'gauge'`) shows the average SCALE response score, resolving `min`/`max` from the question's `configuration` even in the empty state
- `SurveyScaleHistogramChart` (kind `'histogram'`, not `'bar'`) shows the full response distribution in ascending scale order — never re-sorted by frequency
- `EditSurvey::getFooterWidgets()` now emits `SurveyResultsWidget` + `SurveyScaleGaugeChart` + `SurveyScaleHistogramChart` for every SCALE question, with all other question types unchanged (bar/pie/etc. `SurveyResultsWidget` only)
- Both widgets survive a full `wire:poll` cycle via `AppServiceProvider::PAGE_SCOPED_WIDGETS` registration
- VIZ-05 fully closed (both gauge and histogram halves)

## Task Commits

Each task was committed atomically (TDD flow: RED -> GREEN):

1. **Task 1: Build SurveyScaleGaugeChart and SurveyScaleHistogramChart widgets**
   - `c07efd5` (test) — failing Feature test covering empty-state, real-value, and ascending-order behavior
   - `9814ea9` (feat) — both widget classes, GREEN, plus the `array_values()` fix found via the test
2. **Task 2: Wire both widgets into EditSurvey, register in PAGE_SCOPED_WIDGETS, write Browser test**
   - `7d91ae3` (feat) — `EditSurvey` footer wiring, `AppServiceProvider` registration, `PageScopedWidgetRegistrationTest` dataset extension, new Browser test

## Files Created/Modified
- `app/Filament/Widgets/SurveyScaleGaugeChart.php` - `ChartWidget` subclass, kind `'gauge'`, reads `SurveyMetrics.average_value`
- `app/Filament/Widgets/SurveyScaleHistogramChart.php` - `ChartWidget` subclass, kind `'histogram'`, reads `SurveyMetrics.distribution` in ascending scale order
- `app/Filament/Resources/Surveys/Pages/EditSurvey.php` - `getFooterWidgets()` adds gauge+histogram per SCALE question alongside `SurveyResultsWidget`
- `app/Providers/AppServiceProvider.php` - both new widgets added to `PAGE_SCOPED_WIDGETS`
- `tests/Feature/PageScopedWidgetRegistrationTest.php` - dataset extended with both new widgets (established regression-guard precedent)
- `tests/Feature/SurveyScaleGaugeHistogramChartDataTest.php` - TDD unit-level test for both widgets' `getData()` (via `ReflectionMethod`, matching the `OwnershipScopedWidgetsTest.php` precedent)
- `tests/Browser/SurveyScaleGaugeHistogramChartTest.php` - Pest 4 Browser test verifying gauge + histogram + existing bar all render for the same SCALE question

## Decisions Made
- Built a dedicated Feature-level TDD test (`SurveyScaleGaugeHistogramChartDataTest.php`, not listed in the plan's own `files_modified`) to satisfy the plan's `tdd="true"` RED/GREEN requirement for Task 1's `<behavior>` spec — the plan only listed the two widget classes and Task 2's Browser test in its frontmatter, so this file is additive test coverage, not a deviation from any listed file.
- Confirmed PHP's automatic numeric-string-to-int array key coercion means `SurveyMetrics.distribution`'s `'1'`, `'2'`... keys surface as int labels (`1, 2, 3...`) after `array_keys()`, not string labels — order is preserved either way, which is what VIZ-05 actually requires; the plan's own behavior spec wording (`labels=['1','2','3','4','5']`) was imprecise about PHP's key-type coercion, not about test intent.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `array_values()` wrap around histogram's mapped data**
- **Found during:** Task 1, TDD GREEN step
- **Issue:** The plan's literal `getData()` code for `SurveyScaleHistogramChart` used `array_map(fn (array $bucket): int => $bucket['count'] ?? 0, $distribution)` directly as `datasets[0].data`. `array_map()` preserves its source array's keys, and `$distribution`'s keys are the scale values themselves (`1, 2, 3...`, not zero-based) — so the resulting array was non-contiguous-from-zero and would `json_encode()` as a JS object (`{"1":5,"2":10,...}`) instead of an array, breaking `HistogramChart.jsx`'s `toOrderedRows()` consumer.
- **Fix:** Wrapped the `array_map()` call in `array_values()` to re-index from 0 before returning.
- **Files modified:** `app/Filament/Widgets/SurveyScaleHistogramChart.php`
- **Verification:** `SurveyScaleGaugeHistogramChartDataTest.php`'s ascending-order test asserts `datasets[0].data` equals a plain sequential `[5, 10, 15, 15, 5]` array.
- **Committed in:** `9814ea9` (Task 1 commit)

**2. [Rule 1 - Bug] Removed unused `Collection` import from `EditSurvey.php`**
- **Found during:** Task 2
- **Issue:** The plan's literal code added `use Illuminate\Support\Collection;` to `EditSurvey.php`, claiming it was "required for the flatMap return type hint" — it is not; `filter()`/`flatMap()` on an already-typed Eloquent collection need no explicit import. `vendor/bin/pint --test` failed with `no_unused_imports` as written.
- **Fix:** Removed the unused import. CLAUDE.md mandates `vendor/bin/pint --dirty` pass before finalizing changes, taking precedence over the plan's literal (but incorrect) claim.
- **Files modified:** `app/Filament/Resources/Surveys/Pages/EditSurvey.php`
- **Verification:** `vendor/bin/pint --test` passes clean.
- **Committed in:** `7d91ae3` (Task 2 commit)

**3. [Rule 1 - Bug, test-only] Scroll+wait loop before asserting histogram visibility**
- **Found during:** Task 2, Browser test
- **Issue:** The histogram widget is the 3rd of 3 stacked lazy-loaded (`x-intersect`) widgets for the single SCALE question in the test fixture and sits below the fold on the default test viewport — a single `assertVisible()` immediately after `visit()` intermittently failed to find it rendered (confirmed via screenshot: bar+gauge rendered, histogram's section blank). A single scroll+wait tick also proved flaky when run alongside other Browser tests in the same process.
- **Fix:** Added a repeated 5x1s `window.scrollTo(0, document.body.scrollHeight)` + `wait(1)` loop before the visibility assertions — matches the established precedent from `VoterStatusDonutChartTest`/`CoordinatorTeamStackedBarChartTest` (Phase 22 Plan 02) and `TerritorialDistributionChartTest` (Phase 21 Plan 03). Test-only fix, no production code touched.
- **Files modified:** `tests/Browser/SurveyScaleGaugeHistogramChartTest.php`
- **Verification:** Re-ran the test 3x consecutively (standalone and alongside `SurveyResultsWidgetTest.php`/`PageScopedWidgetRegistrationTest.php`/`SurveyScaleGaugeHistogramChartDataTest.php`) — passed every time.
- **Committed in:** `7d91ae3` (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (2 bugs in the plan's literal production/test code, 1 test-flakiness fix matching established precedent)
**Impact on plan:** All fixes necessary for correctness (JSON shape, Pint compliance) or test reliability. No scope creep — no new files beyond what TDD/Browser coverage required.

## Issues Encountered

- **Worktree staleness (recurring, documented class):** Worktree `agent-ad53cfa92f611e7e8` was 78 commits behind `main` at session start — checked out at pre-Phase-20 commit `9ba4267`, missing all of Phase 20/21/22's planning corpus and code, plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`. Since `package.json`/`package-lock.json` were byte-identical to the main checkout and this plan makes zero dependency changes, symlinked `node_modules/` from the main checkout instead of a full reinstall, then ran `npx vite build --mode production` directly against it (succeeded cleanly, confirming the symlink approach works for this plan's zero-JS-change scope). `php artisan migrate:status` showed the DB already fully migrated (shared `sigma_betha_backup`-backed local MySQL, migrated by a prior session). This time, the `state`/`roadmap`/`requirements` CLI-write bug was not independently re-verified — will confirm during the state-update step below.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- VIZ-05 fully complete — both gauge and histogram halves shipped and tested (Feature + Browser).
- Phase 22 (table-stakes-new-visualizations) now has all 4 plans complete (22-01, 22-02, 22-04, 22-05) — this is the last plan in the phase.
- No blockers for Phase 23 (differentiator visualizations) or Phase 24 (Día D live voting chart) — both build on the same React island infrastructure (Phase 20) and chart-kind library (Phase 22 Plan 01) this plan reused unchanged.

---
*Phase: 22-table-stakes-new-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

All 8 claimed files verified present on disk; all 3 task commits (`c07efd5`, `9814ea9`, `7d91ae3`) verified present in git history.
