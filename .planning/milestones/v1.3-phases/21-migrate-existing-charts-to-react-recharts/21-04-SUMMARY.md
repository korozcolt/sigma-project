---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 04
subsystem: ui
tags: [filament, livewire, recharts, chart-widget, survey]

# Dependency graph
requires:
  - phase: 21-02
    provides: react-chart.blade.php shared view (heading/description generalized), ChartRouter/PieChart/BarChart components, reactChartBridge Alpine bridge
provides:
  - SurveyResultsWidget migrated to the React/Recharts pipeline via the shared filament.widgets.react-chart view
  - getChartKind() proxy pattern applied to a widget with genuinely dynamic (per-instance) chart kind, preserving D-04's pie-for-YES_NO/bar-for-other-types switching verbatim
  - SurveyResultsWidget registered on a real routed page (EditSurvey footer, one instance per survey question) for the first time — closes RESEARCH.md Finding 2
  - Documented, reusable fix for page-scoped-widget wire:poll ComponentNotFoundException (AppServiceProvider::PAGE_SCOPED_WIDGETS)
affects: [21-05, 21-06, 21-07]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Page-scoped ChartWidgets that poll (wire:poll) must be added to AppServiceProvider::PAGE_SCOPED_WIDGETS, or the follow-up Livewire request throws ComponentNotFoundException even though the initial mount renders fine"
    - "Factories for HasCampaignContext-scoped models (Survey, etc.) must be given an explicit campaign_id in Browser/Feature tests that also authenticate as a user scoped to a specific campaign — the factory's own default relationship creates an unrelated Campaign that the global scope then hides"

key-files:
  created:
    - tests/Browser/SurveyResultsWidgetTest.php
  modified:
    - app/Filament/Widgets/SurveyResultsWidget.php
    - app/Filament/Resources/Surveys/Pages/EditSurvey.php
    - app/Providers/AppServiceProvider.php
    - tests/Feature/PageScopedWidgetRegistrationTest.php

key-decisions:
  - "SurveyResultsWidget added to AppServiceProvider::PAGE_SCOPED_WIDGETS (the codebase's existing, documented mechanism for widgets mounted only via getHeaderWidgets()/getFooterWidgets() rather than a panel's global ->widgets([...]) array), because its new wire:poll (inherited from react-chart.blade.php) exposed the same Livewire name<->class round-trip bug already fixed once for RevalidationProgressWidget"
  - "Survey::factory() in the new Browser test fixture passes an explicit campaign_id matching the authenticated admin's attached campaign, since Survey uses HasCampaignContext and the factory's own default spins up an unrelated Campaign"

patterns-established:
  - "Any new page-scoped (non-panel-global) ChartWidget built on react-chart.blade.php must be added to AppServiceProvider::PAGE_SCOPED_WIDGETS if it polls, verified by PageScopedWidgetRegistrationTest.php's dataset"

requirements-completed: [MIGR-01]

# Metrics
duration: 65min
completed: 2026-08-20
---

# Phase 21 Plan 04: SurveyResultsWidget Migration Summary

**SurveyResultsWidget migrated onto the React/Recharts pipeline and mounted for the first time (EditSurvey footer, one per question), preserving its dynamic pie-for-YES_NO/bar-for-other-types switching exactly, with a real Pest 4 Browser test proving both chart kinds render on the real survey edit page.**

## Performance

- **Duration:** ~65 min (including worktree resync: merge, composer/npm install, Vite build)
- **Started:** 2026-08-20T21:40:00-05:00 (approx, first commit 21:48)
- **Completed:** 2026-08-21T02:56:00Z
- **Tasks:** 3/3 completed
- **Files modified:** 5 (1 created, 4 modified)

## Accomplishments
- `SurveyResultsWidget` now renders through `filament.widgets.react-chart` instead of Chart.js, with `getData()`/`getQuestionData()`/`getOverallSurveyData()` bodies completely unchanged (MIGR-01 constraint)
- Its dynamic chart-kind logic (`getType()` → `getChartKind()` rename, verbatim branching body) is preserved exactly, closing the second half of MIGR-01
- The widget is now reachable on a real page — `SurveyResource`'s `EditSurvey`, one footer widget instance per survey question — closing `RESEARCH.md` Finding 2 (previously unregistered anywhere in the codebase)
- A real Pest 4 Browser test (`tests/Browser/SurveyResultsWidgetTest.php`) visits the actual `filament.admin.resources.surveys.edit` route and proves both the pie (YES_NO) and bar (SCALE) rendering paths with real fixture data
- Found and fixed a genuine, previously-latent production bug: page-scoped `ChartWidget`s that poll throw `ComponentNotFoundException` on their first `wire:poll` tick unless explicitly registered in `AppServiceProvider::PAGE_SCOPED_WIDGETS` — this codebase already had this exact fix for other widgets (`RevalidationProgressWidget`, Call Center, Día D widgets); `SurveyResultsWidget` was simply never added because it never polled before this migration

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate SurveyResultsWidget's chart-kind resolution to the React pipeline** - `dbd2957` (feat)
2. **Task 2: Register SurveyResultsWidget on EditSurvey's footer, one instance per survey question** - `d26be9e` (feat)
3. **Task 3: Pest 4 Browser test proving D-04's pie/bar dynamic switching on the real survey edit page** - `7e8ad7b` (test, includes 3 auto-fixes discovered while making the test pass for real)

## Files Created/Modified
- `app/Filament/Widgets/SurveyResultsWidget.php` - `$view` repointed to `filament.widgets.react-chart`; `getType()` reduced to a 1-line delegate to new `getChartKind()` (verbatim dynamic pie/bar logic); `getOptions()` Chart.js config deleted (nothing reads it via the new view)
- `app/Filament/Resources/Surveys/Pages/EditSurvey.php` - `getFooterWidgets()` returns one `WidgetConfiguration(SurveyResultsWidget::class, ['questionId' => ...])` per survey question sorted by order; `getFooterWidgetsColumns()` returns 1 (public, matching the parent `Filament\Pages\Page` signature)
- `app/Providers/AppServiceProvider.php` - added `SurveyResultsWidget::class` to `PAGE_SCOPED_WIDGETS`, following the codebase's existing pattern for page-scoped widgets outside `App\Livewire`
- `tests/Feature/PageScopedWidgetRegistrationTest.php` - added `SurveyResultsWidget::class` to the existing regression dataset
- `tests/Browser/SurveyResultsWidgetTest.php` - new Pest 4 Browser test, real Chromium session against the real `EditSurvey` route

## Decisions Made
- `SurveyResultsWidget` registered in `AppServiceProvider::PAGE_SCOPED_WIDGETS` rather than adding it to any panel's global `->widgets([...])` array — it is genuinely page-scoped (per-question, per-survey), matching the existing precedent for `RevalidationProgressWidget` and the Call Center/Día D widgets exactly.
- `Survey::factory()` in the new Browser test fixture is given an explicit `campaign_id` matching the authenticated admin's active campaign, rather than relying on the factory's own default `Campaign::factory()` relationship.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `EditSurvey::getFooterWidgetsColumns()` must be `public`, not `protected`**
- **Found during:** Task 3 (first Browser test run — `FatalError`)
- **Issue:** The plan's own `<interfaces>` documentation described `getFooterWidgetsColumns()` as `protected` (matching `getFooterWidgets()`'s visibility), but the actual vendor signature (`Filament\Pages\Page::getFooterWidgetsColumns()`) is `public`. PHP enforces that an override cannot reduce visibility, so the page fatally errored on every request.
- **Fix:** Changed the override to `public function getFooterWidgetsColumns(): int|array`.
- **Files modified:** `app/Filament/Resources/Surveys/Pages/EditSurvey.php`
- **Verification:** `php -l` clean; Browser test now mounts the page without a `FatalError`.
- **Committed in:** `7e8ad7b`

**2. [Rule 1 - Bug] Test fixture `Survey::factory()->create()` needed an explicit `campaign_id`**
- **Found during:** Task 3 (Browser test failed with `ModelNotFoundException: No query results for model [App\Models\Survey] 1`)
- **Issue:** `Survey` uses `HasCampaignContext`, whose global scope filters every query by the active campaign. `SurveyFactory`'s default `campaign_id` relationship (`Campaign::factory()`) creates its own unrelated `Campaign` distinct from the one the test's admin user is attached to, so the admin's `EditSurvey` page visit couldn't resolve the fixture survey at all.
- **Fix:** Passed `['campaign_id' => $campaign->id]` explicitly to `Survey::factory()->create()`, matching the campaign the admin is attached to.
- **Files modified:** `tests/Browser/SurveyResultsWidgetTest.php`
- **Verification:** Browser test's `visit()` call resolves the record successfully.
- **Committed in:** `7e8ad7b`

**3. [Rule 3 - Blocking] `SurveyResultsWidget`'s `wire:poll` follow-up request threw `ComponentNotFoundException`**
- **Found during:** Task 3 (Browser test's page-load assertion failed; server-side throwable captured by Pest's `LaravelHttpServer` showed the real cause)
- **Issue:** Livewire's alias↔class round-trip resolution is asymmetric for classes outside `config('livewire.class_namespace')` (default `App\Livewire`): the initial `mount()` request works because it's called with the FQCN directly, but any subsequent request driven by the component's serialized snapshot `name` (e.g. a `wire:poll` tick) goes through `nameToClass()`, which unconditionally prepends the root namespace and produces a nonexistent class unless the widget was explicitly registered. `SurveyResultsWidget` is only referenced via `EditSurvey::getFooterWidgets()` (page-scoped), never a panel's `->widgets([...])` array, so it never got Filament's automatic registration — and it never polled before this migration, so the bug was previously undetectable.
- **Fix:** Added `SurveyResultsWidget::class` to the codebase's existing `AppServiceProvider::PAGE_SCOPED_WIDGETS` array (a mechanism already built and documented for exactly this bug class — first fixed for `RevalidationProgressWidget`, later consolidated for Call Center/Día D widgets). Also added `SurveyResultsWidget::class` to `tests/Feature/PageScopedWidgetRegistrationTest.php`'s existing dataset for permanent regression coverage.
- **Files modified:** `app/Providers/AppServiceProvider.php`, `tests/Feature/PageScopedWidgetRegistrationTest.php`
- **Verification:** `tests/Feature/PageScopedWidgetRegistrationTest.php` passes (7/7, up from 6/6); Browser test's `wire:poll` tick round-trips without error; `assertNoJavaScriptErrors()` passes.
- **Committed in:** `7e8ad7b`

---

**Total deviations:** 3 auto-fixed (2 Rule 1 bugs, 1 Rule 3 blocking)
**Impact on plan:** All three were necessary to make the plan's own literal success criteria true (the Browser test genuinely passing against the real page with a real poll cycle). No scope creep — the fixes are narrowly targeted to the widget/page this plan touches, reusing an established codebase pattern rather than inventing a new one.

## Issues Encountered
- This worktree (`agent-ade60f158db9f4fd2`) was 35 commits behind `main` at session start — missing all of Phase 21's planning docs (including this plan's own `21-04-PLAN.md`) and Plans 21-01/21-02's completed work, plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install`, `npm run build`. `npm install` regenerated a spurious `package-lock.json` `name` field change (worktree directory name) — reverted via `git checkout -- package-lock.json` before committing, per established precedent.
- Local MySQL (`sigma_betha_backup`, per `.env`) was not running/reachable in this session (`Connection refused` on `php artisan migrate:status`) — not a blocker, since `phpunit.xml` overrides `DB_CONNECTION`/`DB_DATABASE` to `sqlite`/`:memory:` for all Feature/Unit/Browser test runs, and this plan's scope is entirely test-covered (no `artisan migrate` or production DB access needed).
- Full regression sweep (`php artisan test tests/Feature/`) showed 1 pre-existing, unrelated failure (`UserResourceTest > can update user campaigns`, `app/Filament/Resources/Users/...` — not touched by this plan) that passed in isolated re-run — confirmed to be the same documented `CampaignContext` static-override test-pollution class already tracked as known tech debt in `PROJECT.md`. Not fixed (out of scope, pre-existing).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `SurveyResultsWidget` is fully migrated and the last of the 3 big `ChartWidget`s in MIGR-01's scope (`ValidationProgressChart`, `TerritorialDistributionChart` covered by other Phase 21 plans).
- The `AppServiceProvider::PAGE_SCOPED_WIDGETS` pattern is now a proven, reusable checklist item for any future page-scoped `ChartWidget` built on `react-chart.blade.php` — later plans in this phase (sparkline widgets, MIGR-02) should check whether their new widgets are panel-global or page-scoped and register accordingly if page-scoped and polling.
- No blockers for Phase 21's remaining plans.

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-20*

## Self-Check: PASSED

All 5 created/modified files confirmed present on disk (`SurveyResultsWidget.php`, `EditSurvey.php`, `AppServiceProvider.php`, `PageScopedWidgetRegistrationTest.php`, `SurveyResultsWidgetTest.php`). All 3 task commits (`dbd2957`, `d26be9e`, `7e8ad7b`) confirmed present in `git log`.
