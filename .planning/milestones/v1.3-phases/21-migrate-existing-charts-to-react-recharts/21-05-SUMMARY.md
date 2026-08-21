---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 05
subsystem: ui
tags: [filament, chartwidget, recharts, campaign-isolation, sparkline]

# Dependency graph
requires:
  - phase: 21-migrate-existing-charts-to-react-recharts
    provides: "21-02's react-chart.blade.php shared view (generalized heading + data-chart-kind attribute) and ChartRouter's sparkline kind"
provides:
  - "CampaignVotersSparklineWidget — dedicated ChartWidget rendering CampaignStatsOverview's 7-day voter-growth trend via React/Recharts, registered on all 5 panels"
  - "SurveyResponsesSparklineWidget — dedicated ChartWidget rendering SurveyStatsOverview's 7-day survey-responses trend via React/Recharts, registered on Admin + Reports, campaign-scoped via CampaignContext::currentCampaign() + Survey::forCampaign()"
  - "CampaignStatsOverview::getVotersGrowthChart() and SurveyStatsOverview::getResponsesChart() widened to public, bodies unchanged, reused unduplicated by the new widgets"
affects: [21-06, 21-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dedicated small ChartWidget subclasses placed next to their parent StatsOverviewWidget in ->widgets([...]) arrays, reusing the parent's now-public chart-data method instead of duplicating query logic (D-01)"
    - "Sparkline emptyReason='no_campaign' contract for ChartCard.jsx's dedicated empty state"

key-files:
  created:
    - app/Filament/Widgets/CampaignVotersSparklineWidget.php
    - app/Filament/Widgets/SurveyResponsesSparklineWidget.php
    - tests/Browser/CampaignVotersSparklineWidgetTest.php
    - tests/Browser/SurveyResponsesSparklineWidgetTest.php
  modified:
    - app/Filament/Widgets/CampaignStatsOverview.php
    - app/Filament/Widgets/SurveyStatsOverview.php
    - app/Providers/Filament/AdminPanelProvider.php
    - app/Providers/Filament/ReportsPanelProvider.php
    - app/Providers/Filament/CoordinatorPanelProvider.php
    - app/Providers/Filament/AreaCoordinatorPanelProvider.php
    - app/Providers/Filament/LeaderPanelProvider.php

key-decisions:
  - "SurveyResponsesSparklineWidget resolves its Survey exclusively through CampaignContext::currentCampaign() + Survey::forCampaign(), never an unscoped global Survey:: query, preserving strict campaign isolation while making the previously-dead getResponsesChart() sparkline live for the first time"

patterns-established:
  - "Playwright strict-mode selector scoping for widgets sharing the same [data-chart-kind] value on one dashboard: scope to the widget's own <section class=\"fi-section\"> via :has-text(heading)"

requirements-completed: [MIGR-02]

duration: 35min
completed: 2026-08-20
---

# Phase 21 Plan 05: Campaign Voters and Survey Responses Sparkline Widgets Summary

**Two dedicated Recharts-backed sparkline `ChartWidget`s (`CampaignVotersSparklineWidget`, `SurveyResponsesSparklineWidget`) reuse their parents' unchanged chart-data methods and are registered across the Admin/Reports/Coordinator/AreaCoordinator/Leader panels per MIGR-02's D-01.**

## Performance

- **Duration:** 35 min
- **Started:** 2026-08-20T00:00:00Z (approx, worktree sync + setup included)
- **Completed:** 2026-08-20
- **Tasks:** 3
- **Files modified:** 11 (2 created widgets, 2 created tests, 2 widened methods, 5 panel providers)

## Accomplishments
- `CampaignVotersSparklineWidget` renders `CampaignStatsOverview::getVotersGrowthChart()`'s 7-day trend via the React/Recharts pipeline, campaign-scoped, on all 5 panels
- `SurveyResponsesSparklineWidget` renders `SurveyStatsOverview::getResponsesChart()`'s 7-day trend, making a previously dead-code sparkline (RESEARCH.md Finding 4) live for the first time, strictly scoped to the current campaign's own survey
- Both widgets reuse their parents' existing methods (`getVotersGrowthChart()`, `getResponsesChart()`) unchanged in body, only widened from `protected` to `public`
- 2 new Pest 4 Browser tests, both passing alongside the pre-existing `ReactIslandPocWidgetTest` with zero cross-test pollution

## Task Commits

Each task was committed atomically:

1. **Task 1: Build CampaignVotersSparklineWidget and SurveyResponsesSparklineWidget, widening their parents' chart-data methods to public** - `9412463` (feat)
2. **Task 2: Register both new sparkline widgets on their parents' panels** - `8011c95` (feat)
3. **Task 3: Pest 4 Browser tests for both new sparkline widgets** - `4251944` (test)

## Files Created/Modified
- `app/Filament/Widgets/CampaignVotersSparklineWidget.php` - New ChartWidget, kind `sparkline`, wraps `CampaignStatsOverview::getVotersGrowthChart()`'s oldest-first array into `{points: [{label, value}]}`, `emptyReason: 'no_campaign'` when no active campaign
- `app/Filament/Widgets/SurveyResponsesSparklineWidget.php` - New ChartWidget, kind `sparkline`, resolves the active/latest `Survey` scoped to `CampaignContext::currentCampaign()` via `Survey::forCampaign()`, wraps `SurveyStatsOverview::getResponsesChart()`'s array the same way
- `app/Filament/Widgets/CampaignStatsOverview.php` - `getVotersGrowthChart()` visibility widened `protected` → `public`, body untouched
- `app/Filament/Widgets/SurveyStatsOverview.php` - `getResponsesChart()` visibility widened `protected` → `public`, body untouched
- `app/Providers/Filament/AdminPanelProvider.php` - Registered both new widgets next to their parents
- `app/Providers/Filament/ReportsPanelProvider.php` - Registered both new widgets next to their parents
- `app/Providers/Filament/CoordinatorPanelProvider.php` - Registered `CampaignVotersSparklineWidget` next to `CampaignStatsOverview`
- `app/Providers/Filament/AreaCoordinatorPanelProvider.php` - Registered `CampaignVotersSparklineWidget` next to `CampaignStatsOverview`
- `app/Providers/Filament/LeaderPanelProvider.php` - Registered `CampaignVotersSparklineWidget` next to `CampaignStatsOverview`
- `tests/Browser/CampaignVotersSparklineWidgetTest.php` - Real-browser test: heading + scoped sparkline selector + no JS errors
- `tests/Browser/SurveyResponsesSparklineWidgetTest.php` - Real-browser test: campaign-scoped survey fixture, heading + scoped sparkline selector + no JS errors

## Decisions Made
- `SurveyResponsesSparklineWidget` deliberately queries `Survey::forCampaign($campaign->id)` only after a `CampaignContext::currentCampaign()` check, exactly matching the plan's corrected interface spec (the plan text explicitly notes an earlier draft had this as a real bug) — this is the isolation-critical decision this plan turns on.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed Playwright strict-mode selector collision in the plan's own literal test code**
- **Found during:** Task 3 (Browser tests)
- **Issue:** The plan's literal test code used the bare selector `[data-chart-kind="sparkline"]` in `assertVisible()`. Once both new widgets are registered on the same Admin dashboard (Task 2), this selector resolves to 2 elements, and Playwright's strict mode throws instead of picking one — a direct consequence of this plan bundling two sparkline widgets together, not present when either widget is tested alone.
- **Fix:** Scoped each test's selector to its own widget's `<section class="fi-section">` via `:has-text(heading)`, e.g. `section.fi-section:has-text("Apoyos — últimos 7 días") [data-chart-kind="sparkline"]` — matches the project's established Playwright strict-mode scoping precedent (Phase 19 Plan 05).
- **Files modified:** `tests/Browser/CampaignVotersSparklineWidgetTest.php`, `tests/Browser/SurveyResponsesSparklineWidgetTest.php`
- **Verification:** Both tests pass individually and together with `ReactIslandPocWidgetTest` (3 passed, 10 assertions, zero cross-test pollution)
- **Committed in:** `4251944` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Necessary for the tests to pass correctly once both widgets share a dashboard; no scope creep, no production code affected.

## Issues Encountered
- This worktree (`agent-aa6340f402eaa4b4c`) was stale at session start — on a commit predating Phase 21 entirely (missing all of Phase 21's planning corpus and code, plus `vendor/`, `node_modules/`, `.env`, `public/build/`). Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install` (reverted a spurious `package-lock.json` `name` field change to `agent-aa6340f402eaa4b4c` via `git checkout -- package-lock.json`, matching prior phases' documented precedent), `npm run build`. No `findProjectRoot()` redirection issue was hit this session — `gsd-tools` calls for STATE/ROADMAP/REQUIREMENTS updates below all target this worktree correctly.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `CampaignVotersSparklineWidget` and `SurveyResponsesSparklineWidget` are live on their respective panels, both verified in a real browser with real data.
- Plan 21-06 (`CallCenterStatsWidget`'s sparkline) is unblocked — it depends on a separate, currently-nonexistent page registration and touches a disjoint file set from this plan.
- No blockers or concerns for downstream plans.

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-20*

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/CampaignVotersSparklineWidget.php
- FOUND: app/Filament/Widgets/SurveyResponsesSparklineWidget.php
- FOUND: tests/Browser/CampaignVotersSparklineWidgetTest.php
- FOUND: tests/Browser/SurveyResponsesSparklineWidgetTest.php
- FOUND: 9412463
- FOUND: 8011c95
- FOUND: 4251944
