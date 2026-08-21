---
phase: 21-migrate-existing-charts-to-react-recharts
verified: 2026-08-21T00:00:00Z
status: passed
score: 3/3 must-haves verified (retroactive verification)
notes:
  retroactive: true
  reason: "Phase 21 completed 2026-08-20/21 with 7 SUMMARY.md files but no VERIFICATION.md was ever generated. This gap was discovered during the v1.3 milestone audit and closed retroactively without re-executing any plans."
  superseded_artifact:
    widget: "TerritorialDistributionChart"
    original_kind: "bar (Phase 21-03, commit 07ba2ec)"
    superseded_by: "Phase 23 Plan 23-04 (VIZ-08), commit a861415 — rewritten to kind='treemap' with a 3-level nested {tree} shape"
    verdict: "Phase 21's own delivery (bar-kind migration + passing Browser test) is verified as correctly shipped at the time. The current live code differs because of a later, deliberate, requirement-satisfying (VIZ-08) rewrite in Phase 23, not a Phase 21 regression or gap. Confirmed via git log --follow and git diff against the correct immediate parent commit (a3a04e3), showing getData() was byte-identical in the actual 21-03 migration commit."
  documentation_issue:
    file: "21-01-SUMMARY.md"
    issue: "Frontmatter claims requirements-completed: [MIGR-01, MIGR-02], but 21-01 was a contract-only plan (shared chart-kind library, ChartRouter) that touched zero PHP/Blade widget files — none of the 3 named ChartWidgets or 3 sparklines were migrated by this plan. MIGR-01/MIGR-02's literal wording ('X widgets render through the new pipeline') was not yet true at 21-01's completion. Note: the task brief described this as 21-01's own body text 'explicitly deferring' the requirements — that deferral language is not actually present in 21-01-SUMMARY.md's body; it appears in 21-02-SUMMARY.md's key-decisions instead ('Deferred marking MIGR-01/MIGR-02 complete in REQUIREMENTS.md ... matches the project's established split-requirement precedent'). 21-01's frontmatter is simply inconsistent with its own plan scope."
    impact: "Documentation-only. The actual requirement completion happened correctly and verifiably later: 21-04-SUMMARY.md correctly marks requirements-completed: [MIGR-01] (after SurveyResultsWidget, the last of the 3 ChartWidgets, was migrated), 21-05-SUMMARY.md correctly marks requirements-completed: [MIGR-02] (after 2 of 3 sparklines landed), and 21-07-SUMMARY.md correctly closes both MIGR-01 and MIGR-02 at phase completion. REQUIREMENTS.md's final state (MIGR-01/MIGR-02 both 'Complete', Phase 21) is accurate. No gap-closure action needed — flagging only for hygiene."
---

# Phase 21: Migrate Existing Charts to React/Recharts Verification Report

**Phase Goal:** The 3 existing `ChartWidget`s (`ValidationProgressChart`, `TerritorialDistributionChart`, `SurveyResultsWidget`) and 3 embedded sparklines (`CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`) render through the new React/Recharts pipeline instead of Chart.js, with existing campaign/role-scoped `getData()` queries unchanged, each with a real Pest 4 Browser test.
**Verified:** 2026-08-21 (retroactive — phase execution completed 2026-08-20/21)
**Status:** passed
**Re-verification:** No — initial verification (retroactive, first VERIFICATION.md ever created for this phase)

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `ValidationProgressChart` renders via Recharts (`line` kind) with `getData()` unchanged | VERIFIED | `$view = 'filament.widgets.react-chart'`, `getChartKind()` returns `'line'`; `git diff` against immediate parent commit shows only `$view`/`getType→getChartKind`/`getOptions()`-deletion changes, `getData()` body byte-identical |
| 2 | `TerritorialDistributionChart` was migrated to Recharts (`bar` kind) by this phase, with `getData()` unchanged at time of migration | VERIFIED (historical) | Commit `07ba2ec` (21-03) confirmed via git diff against correct parent (`a3a04e3`) — `getData()` byte-identical, only view/type-delegation changed. **Subsequently and legitimately superseded** by Phase 23-04 (commit `a861415`, VIZ-08) to `kind='treemap'` with a rewritten `getData()` — this is a documented, deliberate replacement, not a Phase 21 defect. See `notes.superseded_artifact` in frontmatter. |
| 3 | `SurveyResultsWidget` renders via Recharts with dynamic pie/bar kind switching preserved, `getData()`/`getQuestionData()`/`getOverallSurveyData()` unchanged | VERIFIED | `git diff` against immediate parent commit (`a757eb3`) confirms only `$view`, `getType()→getChartKind()` delegation, and `getOptions()` deletion; all 3 data methods byte-identical. Now also live on a real routed page (`EditSurvey` footer) for the first time. |
| 4 | The 3 embedded sparklines render through the new pipeline (`CampaignVotersSparklineWidget`, `SurveyResponsesSparklineWidget`, `CallCenterCallsSparklineWidget`) | VERIFIED | All 3 widget files exist, `kind='sparkline'`, `$view='filament.widgets.react-chart'`, each wraps its parent `StatsOverviewWidget`'s chart-data method (widened `protected`→`public`, body unchanged, confirmed via grep of current source) |
| 5 | Each of the 6 widgets has a real Pest 4 Browser test proving rendered chart content | VERIFIED | 6 Browser test files exist and all 6 pass when run right now (see Behavioral Spot-Checks below) |
| 6 | Campaign/role-scoped queries remain unchanged/correctly isolated | VERIFIED | `OwnershipScopedWidgetsTest` (8/8 passing) independently confirms role-scoped visibility for `CampaignStatsOverview`/`TerritorialDistributionChart`/`TopLeadersTable` is intact |

**Score:** 6/6 truths verified (3 aggregate must-haves: MIGR-01 widgets, MIGR-02 sparklines, Browser test coverage)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `resources/js/charts/lib/chartjs-adapter.js` | `toSeriesRows`/`toNameValueRows`/`isChartDataEmpty` | VERIFIED | Present, exports confirmed via 21-01 plan verify script (ran at execution time) |
| `resources/js/charts/lib/palette.js` | `rankedMonochromeFill()` D-03 ramp | VERIFIED | Present |
| `resources/js/charts/ChartRouter.jsx` | `kind → component` dispatcher | VERIFIED | Present; now dispatches 12 kinds (line/bar/pie/sparkline + 8 added by later phases 22/23); `line`/`bar`/`pie`/`sparkline` all map to real components |
| `app/Filament/Widgets/ValidationProgressChart.php` | React pipeline, `line` kind | VERIFIED | `$view` + `getChartKind()` confirmed, `getData()` unchanged |
| `app/Filament/Widgets/TerritorialDistributionChart.php` | React pipeline, originally `bar` kind | VERIFIED (superseded) | Phase 21 delivery confirmed via git history; current live code is Phase 23's `treemap` rewrite (VIZ-08) |
| `app/Filament/Widgets/SurveyResultsWidget.php` | React pipeline, dynamic pie/bar | VERIFIED | Confirmed, plus newly registered on `EditSurvey` footer |
| `app/Filament/Widgets/CampaignVotersSparklineWidget.php` | New sparkline ChartWidget | VERIFIED | Exists, wired to all 5 panels |
| `app/Filament/Widgets/SurveyResponsesSparklineWidget.php` | New sparkline ChartWidget | VERIFIED | Exists, wired to Admin + Reports panels |
| `app/Filament/Widgets/CallCenterCallsSparklineWidget.php` | New sparkline ChartWidget | VERIFIED | Exists, wired to `ListVerificationCalls`, registered in `AppServiceProvider::PAGE_SCOPED_WIDGETS` |
| `tests/Browser/{6 files}` | Pest 4 Browser tests, one per widget | VERIFIED | All 6 exist and pass (see below) |
| `app/Filament/Widgets/ReactIslandPocWidget.php` | Deleted (Phase 20 PoC decommissioned) | VERIFIED | Confirmed absent; zero references anywhere in `app/`, `resources/`, `tests/` |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `ChartRouter.jsx` | `chartjs-adapter.js`/`palette.js` | import | WIRED | Confirmed in `BarChart.jsx`/`LineChart.jsx`/`PieChart.jsx` source |
| `ValidationProgressChart.php` | `filament.widgets.react-chart` view | `$view` property | WIRED | Confirmed |
| `TerritorialDistributionChart.php` (Phase 21 version) | `filament.widgets.react-chart` view | `$view` property | WIRED (historical) | Confirmed in commit `07ba2ec`; current code (post-23-04) still uses this view for its `treemap` successor |
| `SurveyResultsWidget.php` | `EditSurvey::getFooterWidgets()` | `WidgetConfiguration` | WIRED | Confirmed in `app/Filament/Resources/Surveys/Pages/EditSurvey.php` |
| `CampaignVotersSparklineWidget` / `SurveyResponsesSparklineWidget` | 5 Panel Providers | `->widgets([...])` array | WIRED | Confirmed via grep across `AdminPanelProvider`, `ReportsPanelProvider`, `CoordinatorPanelProvider`, `AreaCoordinatorPanelProvider`, `LeaderPanelProvider` |
| `CallCenterCallsSparklineWidget` | `ListVerificationCalls::getHeaderWidgets()` | array | WIRED | Confirmed |
| All page-scoped polling widgets | `AppServiceProvider::PAGE_SCOPED_WIDGETS` | `Livewire::component()` registration | WIRED | Confirmed present for `SurveyResultsWidget`, `CallCenterStatsWidget`, `CallCenterCallsSparklineWidget` |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|---------------------|--------|
| `ValidationProgressChart::getData()` | `$totalData`/`$validatedData` | `Voter::where(...)->whereDate(...)->count()` (role-scoped) | Yes | FLOWING |
| `SurveyResultsWidget::getData()` | `$labels`/`$data` | `SurveyMetrics::where(...)->first()->distribution` | Yes | FLOWING |
| `CampaignVotersSparklineWidget::getData()` | `points` | `CampaignStatsOverview::getVotersGrowthChart()` → `Voter::...->groupBy('date')` | Yes | FLOWING |
| `SurveyResponsesSparklineWidget::getData()` | `points` | `SurveyStatsOverview::getResponsesChart()` → `SurveyResponse::where(...)->distinct('voter_id')->count()` | Yes | FLOWING |
| `CallCenterCallsSparklineWidget::getData()` | `points` | `CallCenterStatsWidget::getLastWeekCallsChart()` → `VerificationCall::query()->groupBy('date')` | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| `ValidationProgressChartTest` renders real line chart | `php artisan test tests/Browser/ValidationProgressChartTest.php` | 1 passed | PASS |
| `TerritorialDistributionChartTest` renders real chart (current treemap version) | `php artisan test tests/Browser/TerritorialDistributionChartTest.php` | 1 passed | PASS |
| `SurveyResultsWidgetTest` renders pie+bar dynamic switching | `php artisan test tests/Browser/SurveyResultsWidgetTest.php` | 1 passed | PASS |
| `CampaignVotersSparklineWidgetTest` renders real sparkline | `php artisan test tests/Browser/CampaignVotersSparklineWidgetTest.php` | 1 passed | PASS |
| `SurveyResponsesSparklineWidgetTest` renders real sparkline | `php artisan test tests/Browser/SurveyResponsesSparklineWidgetTest.php` | 1 passed | PASS |
| `CallCenterCallsSparklineWidgetTest` renders real sparkline | `php artisan test tests/Browser/CallCenterCallsSparklineWidgetTest.php` | 1 passed | PASS |
| Role/campaign scoping unchanged | `php artisan test --filter=OwnershipScopedWidgetsTest` | 8 passed (25 assertions) | PASS |
| Page-scoped widget Livewire registration | `php artisan test tests/Feature/PageScopedWidgetRegistrationTest.php` | 14 passed | PASS |
| Phase 20 PoC fully decommissioned | `grep -rn "ReactIslandPocWidget" app/ resources/ tests/` | zero matches | PASS |

All 6 named phase-21 Browser tests plus 2 supporting regression suites pass cleanly right now (20.84s total for the 6 chart tests), confirming the migration is not just historically documented but currently live and working.

### Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|--------------|--------|----------|
| MIGR-01 | 21-01, 21-02, 21-03, 21-04, 21-07 | 3 ChartWidgets render through React/Recharts, `getData()` unchanged | SATISFIED | `ValidationProgressChart`/`TerritorialDistributionChart` (21-03), `SurveyResultsWidget` (21-04) all migrated with byte-identical `getData()`; correctly closed in `21-04-SUMMARY.md` and reconfirmed in `21-07-SUMMARY.md`. `TerritorialDistributionChart` later superseded by Phase 23-04 (VIZ-08) — does not retroactively invalidate MIGR-01, since Phase 21's own scope was correctly delivered and Phase 23's replacement independently satisfies its own requirement. |
| MIGR-02 | 21-01, 21-02, 21-05, 21-06, 21-07 | 3 embedded sparklines render through the new pipeline | SATISFIED | `CampaignVotersSparklineWidget`/`SurveyResponsesSparklineWidget` (21-05), `CallCenterCallsSparklineWidget` (21-06), all live and browser-tested; correctly closed in `21-05-SUMMARY.md` (partial) and `21-07-SUMMARY.md` (full) |

No orphaned requirements — `.planning/REQUIREMENTS.md` maps only MIGR-01/MIGR-02 to Phase 21, and both are declared in the `requirements:` frontmatter field of at least one plan (cross-checked: 21-01/21-02/21-07 declare both, 21-03/21-04 declare MIGR-01, 21-05/21-06 declare MIGR-02).

### Anti-Patterns Found

None. Scanned all Phase 21-created/modified chart-library files (`chartjs-adapter.js`, `palette.js`, `formatters.js`, `ChartTooltip.jsx`, `LineChart.jsx`, `BarChart.jsx`, `PieChart.jsx`, `SparklineChart.jsx`, `ChartRouter.jsx`) and the 3 new sparkline widget PHP files for `TODO`/`FIXME`/`PLACEHOLDER`/`not yet implemented`/`coming soon` patterns — zero matches.

### Documentation-Only Issue Found

`21-01-SUMMARY.md`'s frontmatter (`requirements-completed: [MIGR-01, MIGR-02]`) is inconsistent with its own plan scope — 21-01 was explicitly a "contract-only" plan (per its own `<success_criteria>`: "No PHP files, Blade files, or `main.jsx`/`ChartCard.jsx` were touched") that built the shared chart-kind library but migrated zero widgets. Marking both requirements complete at that point was premature by the requirements' own literal wording. This did not propagate into an actual gap: `21-04-SUMMARY.md` and `21-05-SUMMARY.md` correctly re-mark the requirements as still-incomplete/partially-complete at their respective junctures, and `21-07-SUMMARY.md` correctly closes both at true phase completion, matching `REQUIREMENTS.md`'s final accurate state. No action required beyond this note — flagged for hygiene per audit request.

### Human Verification Required

None outstanding. Phase 21's own closing plan (21-07) already included and recorded a human browser checkpoint ("si, se ve muchisimo mejor, approved") covering all 6 widgets in both light/dark mode, including a real production bug (hardcoded light theme) found and fixed during that checkpoint. No new human verification is needed for this retroactive audit since all claims were independently re-confirmed via live-running Browser tests above.

### Gaps Summary

No gaps found. Phase 21 goal was achieved: all 3 original `ChartWidget`s and all 3 embedded sparklines were correctly migrated to the React/Recharts pipeline with unchanged, byte-identical `getData()`/chart-data-method bodies and unchanged role/campaign scoping, each backed by a real, currently-passing Pest 4 Browser test. The one artifact whose current code no longer matches Phase 21's output (`TerritorialDistributionChart`, now `treemap` instead of `bar`) is a verified, deliberate, requirement-satisfying (VIZ-08) supersession by Phase 23, not a Phase 21 defect — Phase 21's own delivery is independently confirmed correct via git history at the time it shipped. One minor documentation-only frontmatter inconsistency was found in `21-01-SUMMARY.md` and is noted above; it had no downstream functional impact since later plans and `REQUIREMENTS.md` itself reflect the correct, accurate completion state.

---

*Verified: 2026-08-21 (retroactive)*
*Verifier: Claude (gsd-verifier)*
