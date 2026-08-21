---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 03
subsystem: ui
tags: [react, recharts, filament, chart-widget, livewire]

# Dependency graph
requires:
  - phase: 21-migrate-existing-charts-to-react-recharts (plan 02)
    provides: ChartCard.jsx MonoCharts chrome shell, generalized react-chart.blade.php with data-chart-kind/data-question-id attributes
provides:
  - "ValidationProgressChart repointed to the React/Recharts pipeline as a 2-series line chart, getData() byte-identical to before"
  - "TerritorialDistributionChart repointed to the React/Recharts pipeline as a single-series ranked bar chart, getData() byte-identical to before"
  - "2 new Pest 4 Browser tests proving real rendered content for both widgets against real campaign data"
affects: [21-04, 21-05, 21-06, 21-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Fixed-chartKind ChartWidget migration pattern: $view repointed to filament.widgets.react-chart, getType() delegates to a new getChartKind() returning a hardcoded string, getOptions() deleted entirely (unread by the new view — Chart.js-only)"

key-files:
  created:
    - tests/Browser/ValidationProgressChartTest.php
    - tests/Browser/TerritorialDistributionChartTest.php
  modified:
    - app/Filament/Widgets/ValidationProgressChart.php
    - app/Filament/Widgets/TerritorialDistributionChart.php

key-decisions:
  - "MIGR-01 NOT marked complete in REQUIREMENTS.md — it covers all 3 widgets (ValidationProgressChart, TerritorialDistributionChart, SurveyResultsWidget); this plan only migrates the first two, SurveyResultsWidget's dynamic-kind migration is Plan 21-04's job. Deferred requirement sign-off to phase completion, matching the project's established split-requirement precedent."

patterns-established:
  - "Lazy-loaded (x-intersect) Filament widgets positioned below the fold on a long dashboard need an explicit scroll-to-bottom + short wait in their Browser test before asserting on rendered content, since the widget's AJAX-fetched content doesn't exist in the DOM until its placeholder actually intersects the viewport"

requirements-completed: []

# Metrics
duration: ~35min
completed: 2026-08-20
---

# Phase 21 Plan 03: Migrate ValidationProgressChart and TerritorialDistributionChart Summary

**Repointed the 2 simplest existing Chart.js widgets (fixed chart kind, no dynamic-type branching) onto the Phase 20/21 React island pipeline with zero changes to either widget's `getData()` body, backed by 2 new real-browser Pest tests.**

## Performance

- **Duration:** ~35 min (includes stale-worktree recovery)
- **Started:** 2026-08-20T (session start)
- **Completed:** 2026-08-20T (session end)
- **Tasks:** 2
- **Files modified:** 4 (2 widgets modified, 2 test files created)

## Accomplishments
- `ValidationProgressChart` and `TerritorialDistributionChart` both render via `filament.widgets.react-chart` (the shared React island view from Plan 21-02) instead of Chart.js, with `getChartKind()` returning `'line'`/`'bar'` respectively.
- Both widgets' `getOptions()` methods (Chart.js-only legend/scale/interaction config) were deleted — nothing in the new pipeline reads them; the equivalent visual intent (legend visibility, hover-all-series behavior) is already expressed directly in `LineChart.jsx`/`BarChart.jsx` from Plan 21-01.
- Neither widget's `getData()` method body changed — verified byte-identical via `git diff` (only the class-property/method-signature lines around it changed).
- 2 new Pest 4 Browser tests (`ValidationProgressChartTest.php`, `TerritorialDistributionChartTest.php`) each visit the real `filament.admin.pages.dashboard` route with real fixture data and assert against `[data-chart-kind="line"]`/`[data-chart-kind="bar"]`, real heading/municipio text, and zero JS console errors.
- All 3 dashboard Browser tests (this plan's 2 + Plan 21-02's transitional PoC test) coexist in one process with zero cross-test pollution.

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate ValidationProgressChart and TerritorialDistributionChart to the React pipeline** - `07ba2ec` (feat)
2. **Task 2: Pest 4 Browser tests proving real rendered content for both widgets** - `5f38a8e` (test)

**Plan metadata:** (this commit) `docs(21-03): complete plan`

## Files Created/Modified
- `app/Filament/Widgets/ValidationProgressChart.php` - `$view` repointed to `filament.widgets.react-chart`; `getType()` delegates to new `getChartKind(): 'line'`; `getOptions()` deleted; `getData()` unchanged
- `app/Filament/Widgets/TerritorialDistributionChart.php` - Same pattern with `getChartKind(): 'bar'`
- `tests/Browser/ValidationProgressChartTest.php` - Real-browser proof of line-kind rendering with real 30-day validation data
- `tests/Browser/TerritorialDistributionChartTest.php` - Real-browser proof of bar-kind rendering with real municipio-ranked data

## Decisions Made
- MIGR-01 deferred — see `key-decisions` in frontmatter. This plan closes the first half (the 2 fixed-kind widgets); `SurveyResultsWidget` (dynamic chart-kind switching) is Plan 21-04's scope.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `TerritorialDistributionChartTest` needed a scroll + wait before asserting visibility**
- **Found during:** Task 2 (Browser test creation)
- **Issue:** The plan's literal test code called `$page->assertVisible('[data-chart-kind="bar"]')` immediately after `visit()`. `TerritorialDistributionChart` is a lazy-loaded Filament widget (`x-intersect`-driven) positioned well below the fold on the admin dashboard (a long page with ~18 registered widgets); its content is only fetched/rendered via a Livewire AJAX round trip once its placeholder actually intersects the viewport. `assertVisible()` does a single immediate check with no retry/wait, so it failed against the still-unrendered `fi-loading-section` placeholder. Confirmed via a disposable debug test dumping `$page->content()` — zero `data-chart-kind` attributes existed in the DOM at that point for either lazy widget on the page. `ValidationProgressChart` happened to sit high enough in initial page layout to already be in the default viewport, so its identical lazy-load flag never caused an observable issue.
- **Fix:** Added `$page->script('window.scrollTo(0, document.body.scrollHeight)')` followed by `$page->wait(2)` before the assertions, giving the IntersectionObserver time to fire and the resulting Livewire AJAX fetch time to complete.
- **Files modified:** `tests/Browser/TerritorialDistributionChartTest.php`
- **Verification:** Test passes individually and alongside the other 2 Browser tests in the same process (3 passed, 10 assertions).
- **Committed in:** `5f38a8e` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug, test-only — no production code change)
**Impact on plan:** Test-only fix necessary for the test to reliably prove real rendered content; no scope creep, no production behavior change.

## Issues Encountered

This worktree (`agent-adf2325401a1839f3`) was stale at session start — checked out at a pre-v1.3 commit (`9ba4267`), missing all of Phase 20/21's planning corpus and completed work (including this plan's own `21-03-PLAN.md`), plus `vendor/`, `.env`, `node_modules/`, `public/build/`. Resolved with the established workaround (documented repeatedly in prior phases' STATE.md decisions): confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD refs/heads/main`), `git merge --ff-only refs/heads/main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install && npm run build`. `npm install` regenerated a spurious `package-lock.json` `name` field change (worktree directory name) — reverted via `git checkout -- package-lock.json` before any commit, per established precedent. Playwright's Chromium cache was already present and compatible (confirmed by the pre-existing `ReactIslandPocWidgetTest.php` passing on first run) — no `npx playwright install` was needed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

`ValidationProgressChart` and `TerritorialDistributionChart` are fully migrated and browser-verified. `SurveyResultsWidget` (dynamic chart-kind switching, Plan 21-04) remains the only widget still on Chart.js. No blockers for Plan 21-04.

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-20*

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/ValidationProgressChart.php
- FOUND: app/Filament/Widgets/TerritorialDistributionChart.php
- FOUND: tests/Browser/ValidationProgressChartTest.php
- FOUND: tests/Browser/TerritorialDistributionChartTest.php
- FOUND commit: 07ba2ec
- FOUND commit: 5f38a8e
