---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 06
subsystem: ui
tags: [filament, chartwidget, livewire, recharts, sparkline]

# Dependency graph
requires:
  - phase: 21-migrate-existing-charts-to-react-recharts (plan 21-02)
    provides: Generalized react-chart.blade.php shared view + ChartCard/ChartRouter chrome shell
provides:
  - CallCenterCallsSparklineWidget — the 3rd and final dedicated sparkline ChartWidget required by MIGR-02
  - CallCenterStatsWidget reachable on a real routed Filament page for the first time (ListVerificationCalls)
  - Extension of the AppServiceProvider::PAGE_SCOPED_WIDGETS pattern to cover both new widgets
affects: [21-07 (decommission ReactIslandPocWidget + full regression), phase completion sign-off for MIGR-02]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Page-scoped ChartWidget/StatsOverviewWidget subclasses attached via a Page's getHeaderWidgets() must be added to AppServiceProvider::PAGE_SCOPED_WIDGETS and explicitly registered via Livewire::component(), or they throw ComponentNotFoundException on the wire:poll follow-up request (first render succeeds, poll fails) — pre-existing documented pattern, extended here rather than reinvented"

key-files:
  created:
    - app/Filament/Widgets/CallCenterCallsSparklineWidget.php
    - tests/Browser/CallCenterCallsSparklineWidgetTest.php
  modified:
    - app/Filament/Widgets/CallCenterStatsWidget.php
    - app/Filament/Resources/VerificationCalls/Pages/ListVerificationCalls.php
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "CallCenterStatsWidget::getLastWeekCallsChart() widened from protected to public (body unchanged) so the new sparkline widget can call it directly from a freshly-instantiated instance, matching the plan's literal interface"
  - "CallCenterStatsWidget's own StatsOverviewWidget is registered alongside the new sparkline on ListVerificationCalls, not just the sparkline alone — this is CallCenterStatsWidget's first page registration anywhere in the codebase, closing the same class of unregistered-widget gap RESEARCH.md documents for SurveyResultsWidget/SurveyStatsOverview"

patterns-established:
  - "Any future page-scoped chart/stat widget in this codebase must be added to AppServiceProvider::PAGE_SCOPED_WIDGETS — confirmed as a hard requirement (not optional) by reproducing the ComponentNotFoundException firsthand for both new widgets before fixing it"

requirements-completed: []

# Metrics
duration: 4min
completed: 2026-08-21
---

# Phase 21 Plan 06: CallCenterCallsSparklineWidget + CallCenterStatsWidget Wiring Summary

**Third and final MIGR-02 sparkline (`CallCenterCallsSparklineWidget`) wired onto `ListVerificationCalls` alongside its previously-unregistered parent `CallCenterStatsWidget`, both fixed for page-scoped Livewire component resolution.**

## Performance

- **Duration:** ~4 min (task commits) + environment setup (worktree was 2 phases behind `main`)
- **Started:** 2026-08-20T21:47:00-05:00 (approx, first task commit)
- **Completed:** 2026-08-20T21:50:31-05:00
- **Tasks:** 3/3 completed
- **Files modified:** 5 (2 created, 3 modified)

## Accomplishments
- `CallCenterCallsSparklineWidget` built as a `kind='sparkline'` `ChartWidget`, reusing `CallCenterStatsWidget::getLastWeekCallsChart()` unchanged and wrapping its oldest-first `array<float>` into the `['points' => [['label' => string, 'value' => float], ...]]` shape
- `CallCenterStatsWidget` — previously registered on zero panels/resources anywhere in the codebase — is now reachable on a real routed page (`ListVerificationCalls`) for the first time, alongside the new sparkline
- Fixed a real, reproducible `ComponentNotFoundException` on the `wire:poll` follow-up request for both new widgets by extending the codebase's existing `AppServiceProvider::PAGE_SCOPED_WIDGETS` registration pattern
- 1 new Pest 4 Browser test passes, visiting the real `filament.admin.resources.verification-calls.index` route and asserting a genuine `[data-chart-kind="sparkline"]` element with no JS console errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Build CallCenterCallsSparklineWidget, widening CallCenterStatsWidget's chart-data method to public** - `f925fe6` (feat)
2. **Task 2: Register both CallCenterStatsWidget and CallCenterCallsSparklineWidget on ListVerificationCalls** - `6218e98` (feat)
3. **Task 3: Pest 4 Browser test for CallCenterCallsSparklineWidget** - `d78a493` (test, includes the Rule 3 AppServiceProvider fix required to make the test pass)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Filament/Widgets/CallCenterCallsSparklineWidget.php` - New `ChartWidget`, `kind='sparkline'`, wraps `CallCenterStatsWidget::getLastWeekCallsChart()` into the `points` shape
- `app/Filament/Widgets/CallCenterStatsWidget.php` - `getLastWeekCallsChart()` widened `protected` -> `public` (body unchanged)
- `app/Filament/Resources/VerificationCalls/Pages/ListVerificationCalls.php` - Added `getHeaderWidgets()` registering both `CallCenterStatsWidget::class` and `CallCenterCallsSparklineWidget::class`
- `app/Providers/AppServiceProvider.php` - Added both new widget classes to `PAGE_SCOPED_WIDGETS` so Livewire can resolve them on the `wire:poll` follow-up request
- `tests/Browser/CallCenterCallsSparklineWidgetTest.php` - New Pest 4 Browser test

## Decisions Made
- `pollingInterval = '30s'` kept consistent between `CallCenterStatsWidget` and the new sparkline, matching the plan's stated cadence rationale.
- Followed the plan's literal task code for the widget, page registration, and test verbatim — no deviation from the specified shapes.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Registered both new widgets in `AppServiceProvider::PAGE_SCOPED_WIDGETS`**
- **Found during:** Task 3 (Browser test)
- **Issue:** Both `CallCenterStatsWidget` and `CallCenterCallsSparklineWidget` are page-scoped (attached via `ListVerificationCalls::getHeaderWidgets()`), not panel-globally-declared. This codebase has a documented, pre-existing constraint (see the in-file comment on `AppServiceProvider::PAGE_SCOPED_WIDGETS`): Livewire's alias<->class resolution only auto-registers classes under `config('livewire.class_namespace')` (`App\Livewire`); `App\Filament\Widgets` classes resolve fine on first render but throw `ComponentNotFoundException` on the `wire:poll` follow-up request unless explicitly registered. The Browser test failed on first run with exactly this error for both widgets in turn.
- **Fix:** Added `CallCenterStatsWidget::class` and `CallCenterCallsSparklineWidget::class` to the existing `PAGE_SCOPED_WIDGETS` array and let the existing `boot()` loop register them via `Livewire::component()` — no new mechanism invented, purely extending the established, already-documented pattern.
- **Files modified:** `app/Providers/AppServiceProvider.php`
- **Verification:** `php artisan test tests/Browser/CallCenterCallsSparklineWidgetTest.php tests/Browser/ReactIslandPocWidgetTest.php` — both pass, no cross-test pollution.
- **Committed in:** `d78a493` (Task 3 commit)

**2. [CLAUDE.md-mandated] Pint auto-fixed pre-existing blank-line style issue in `CallCenterStatsWidget.php`**
- **Found during:** Task 1
- **Issue:** `vendor/bin/pint --dirty` (mandated by `CLAUDE.md`) removed two pre-existing blank lines immediately after the class's opening brace (unrelated pre-existing style debt, not introduced by this plan). The plan's own `<verification>` section says "Confirm `git diff` shows ONLY the single visibility-keyword change" — CLAUDE.md's mandatory Pint rule takes precedence per this agent's standing instructions.
- **Fix:** Ran Pint as required; confirmed via `git diff` that the only functional change is `protected` -> `public`, with the extra diff being a whitespace-only cleanup of pre-existing debt in the same touched region.
- **Files modified:** `app/Filament/Widgets/CallCenterStatsWidget.php`
- **Verification:** `git diff app/Filament/Widgets/CallCenterStatsWidget.php` reviewed manually — confirmed no other functional change.
- **Committed in:** `f925fe6` (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 CLAUDE.md-mandated style cleanup)
**Impact on plan:** Both necessary — the first for correctness (widget would be completely broken on poll), the second for compliance with a hard project rule. No scope creep.

## Issues Encountered

- **Worktree staleness (recurring, previously documented class):** This worktree (`agent-a21c58849e39680d7`) was 2 commits behind `main` at session start — missing Phase 20 entirely, Phase 21 plans 21-01 through 21-07, `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install && npm run build`. `npm install` regenerated a spurious `package-lock.json` `name` field change (worktree directory name) — reverted via `git checkout -- package-lock.json` before any task work, per the established precedent.
- MySQL (`DB_CONNECTION=mysql` in `.env`) was not reachable in this worktree's environment, causing `php artisan migrate:status` / `php artisan optimize:clear` to fail on the `cache` table query — irrelevant to this plan since `phpunit.xml` forces `DB_CONNECTION=sqlite`/`:memory:` for all test runs; confirmed the pre-existing `ReactIslandPocWidgetTest` and this plan's new test both pass cleanly against sqlite.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- MIGR-02 is NOT marked complete in `REQUIREMENTS.md` by this plan alone — the requirement's literal text covers all 3 embedded sparklines (`CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`); this plan closes the `CallCenterStatsWidget` sparkline only. Plan 21-05 (`CampaignVotersSparklineWidget` + `SurveyResponsesSparklineWidget`) had not yet produced a SUMMARY.md at the time this plan executed (parallel wave-3 sibling worktree). Deferred requirement sign-off to phase completion, matching this project's established split-requirement precedent (documented extensively in `.planning/STATE.md`'s Decisions log for prior phases).
- Plan 21-07 (decommission `ReactIslandPocWidget` + full regression + human browser checkpoint) can proceed once all of 21-03/21-04/21-05/21-06 land — this plan's own scope is fully done and regression-clean (`CallCenter*`/`VerificationCall*` Feature suites all green, both this plan's and the pre-existing PoC Browser test pass together with no pollution).
- No blockers or concerns for downstream phases.

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-21*

## Self-Check: PASSED

All created files and commit hashes verified present on disk / in git history.
