---
phase: 19-articulador-panel-human-uat-closure
plan: 03
subsystem: testing
tags: [pest, browser-testing, playwright, filament, livewire, widgets, articulador]

# Dependency graph
requires:
  - phase: 19-articulador-panel-human-uat-closure
    provides: "AREA_COORDINATOR-scoped CampaignStatsOverview/TopLeadersTable/TerritorialDistributionChart (19-01) and the shared loginRealBrowserUser() Pest helper (19-02)"
provides:
  - "tests/Browser/ArticuladorDashboardWidgetScopingTest.php: real-Chromium regression proving the /articulador dashboard is genuinely scoped to the logged-in articulador's own transitive team, closing Human-UAT item 1 from 15-HUMAN-UAT.md"
  - "A panel-aware voterResourceUrl() guard on CampaignStatsOverview and TopLeadersTable that prevents a RouteNotFoundException crash when either widget renders inside a panel (Coordinator/Leader/Articulador) that doesn't register VoterResource"
affects: [19-articulador-panel-human-uat-closure remaining plans, any future work touching CampaignStatsOverview or TopLeadersTable, any future Coordinator/Leader/Articulador panel dashboard visit]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Panel-aware resource URL guard: Filament::getCurrentOrDefaultPanel()->getId() + Route::has('filament.{panel}.resources.{resource}.{name}') before calling ResourceClass::getUrl(), so a shared widget can render safely across panels that do/don't register the linked resource"

key-files:
  created:
    - tests/Browser/ArticuladorDashboardWidgetScopingTest.php
  modified:
    - app/Filament/Widgets/CampaignStatsOverview.php
    - app/Filament/Widgets/TopLeadersTable.php

key-decisions:
  - "Fixed a real, previously-undetected production bug found while writing this plan's browser test: CampaignStatsOverview and TopLeadersTable both eagerly called VoterResource::getUrl(), but VoterResource is only registered in the Admin/Reports panels — every Coordinador, Líder, and Articulador dashboard visit crashed with a RouteNotFoundException whenever CampaignContext had an active campaign. Existing Livewire::test()-level coverage never caught this because those tests never set an HTTP-routed active panel, so Filament::getCurrentOrDefaultPanel() silently fell back to the 'admin' panel (where the route exists)."
  - "Guard uses Filament::getCurrentOrDefaultPanel(), not getCurrentPanel() — matches Filament's own internal Resource::getUrl()/getRouteBaseName() fallback-to-default-panel behavior exactly, discovered only after the first guard attempt (getCurrentPanel(), which is null outside a real panel-routed HTTP request) broke 2 pre-existing WidgetDrillThroughTest assertions that rely on that same default-panel fallback."
  - "Pinned deterministic, digit-4-free phone/email on the fixture leader users (leaderA1/leaderB1) instead of leaving them to Faker's random generator — Playwright's assertDontSee(4) does a visible-text substring match, and a random phone number rendered in the TopLeadersTable row would intermittently produce a false-positive digit collision unrelated to the actual voter-count scoping under test (same class of flakiness precedent already documented for wire:snapshot checksum digit collisions)."

patterns-established:
  - "voterResourceUrl()-style panel-aware URL guard: any future shared widget/table that links to a resource not registered in every consuming panel should check Route::has() against the current-or-default panel before calling Resource::getUrl(), rather than assuming the resource is universally routable."

requirements-completed: []

# Metrics
duration: 45min
completed: 2026-08-12
---

# Phase 19 Plan 03: Articulador Dashboard Human-UAT Closure (Browser Test) Summary

**Real-Chromium Pest v4 Browser test proves an articulador's `/articulador` dashboard is genuinely scoped to their own transitive team — and along the way caught and fixed a live production bug where `CampaignStatsOverview`/`TopLeadersTable` crashed with a `RouteNotFoundException` on every Coordinador/Líder/Articulador dashboard visit with an active campaign.**

## Performance

- **Duration:** 45 min (including stale-worktree environment setup)
- **Started:** 2026-08-12T04:18:00Z (approx)
- **Completed:** 2026-08-12T05:03:51Z
- **Tasks:** 2 (browser test + widget bugfix, style/regression sweep)
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments
- Created `tests/Browser/ArticuladorDashboardWidgetScopingTest.php` with two real-Chromium `it(...)` tests: one logs in as `areaCoordinatorA` and asserts `CampaignStatsOverview` shows only team A's total (4, not team B's 11 or the campaign's 15), `TopLeadersTable` shows only "Leader Team A", and `TerritorialDistributionChart` renders its heading without a server error; the second is the mirror image logged in as `areaCoordinatorB`, proving cross-articulador isolation in a real browser session (not just at the Livewire-component level already covered by 19-01's `OwnershipScopedWidgetsTest.php`).
- Found and fixed a real, previously-undetected production bug: `CampaignStatsOverview::getTotalVotersStat()`/`getConfirmedVotersStat()` and `TopLeadersTable`'s `recordUrl()` all eagerly called `VoterResource::getUrl(...)`, which resolves against the currently active Filament panel — but `VoterResource` is registered only in the Admin and Reports panels. Every real visit to the Coordinador, Líder, or Articulador dashboard with an active campaign selected crashed with an uncaught `Illuminate\View\ViewException` wrapping a `RouteNotFoundException`. Added a shared `voterResourceUrl()` private helper to both widgets that checks `Route::has("filament.{panel}.resources.voters.{name}")` against `Filament::getCurrentOrDefaultPanel()` before calling `VoterResource::getUrl()`, omitting the link (instead of crashing) when the resource isn't registered in the active panel.
- Confirmed the full `tests/Browser` suite (4/4), the widget/team-resolution regression surface (`OwnershipScopedWidgetsTest` 8/8, `ArticuladorTeamResolutionTest` 5/5, `WidgetDrillThroughTest` 11/11, `DashboardWidgetsTest` 20/20, `ArticuladorNavigationTest` 6/6, `AreaCoordinatorHierarchyTest` 5/5, `AreaCoordinatorPanelAccessTest` 6/6), and a full-suite run (1520 passed, 17 pre-existing unrelated `CampaignContext` test-pollution failures, all confirmed passing in isolation) all stay green after the fix.

## Task Commits

1. **Task 1: Write and run the multi-tenant articulador dashboard scoping browser test** - `ead3920` (test — includes the widget bugfix and deterministic-fixture fix discovered while making the test pass)
2. **Task 2: Style check and full Browser suite regression** - `63eb79a` (fix — corrected the Task 1 guard from `getCurrentPanel()` to `getCurrentOrDefaultPanel()` after the full regression sweep surfaced 2 broken pre-existing tests)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified
- `tests/Browser/ArticuladorDashboardWidgetScopingTest.php` - Two real-Chromium tests proving per-articulador dashboard scoping and cross-articulador isolation.
- `app/Filament/Widgets/CampaignStatsOverview.php` - Added `voterResourceUrl()` panel-aware guard; `getTotalVotersStat()`/`getConfirmedVotersStat()` now omit the stat's `->url()` instead of crashing when `VoterResource` isn't registered in the active panel.
- `app/Filament/Widgets/TopLeadersTable.php` - Same `voterResourceUrl()` guard applied to `->recordUrl()`.

## Decisions Made
See `key-decisions` in frontmatter above (production bug found/fixed, `getCurrentOrDefaultPanel()` vs `getCurrentPanel()`, deterministic fixture phone/email).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `CampaignStatsOverview`/`TopLeadersTable` crash inside Coordinador/Líder/Articulador panels**
- **Found during:** Task 1, first real-browser run of the new test
- **Issue:** Both widgets eagerly call `VoterResource::getUrl(...)` to build a drill-through link, but `VoterResource` is only registered in the Admin and Reports panels (`ReportsPanelProvider`, and `AdminPanelProvider`'s `discoverResources()`). Visiting `/articulador` (or `/coordinator`, `/leader`) with an active campaign selected threw an uncaught `RouteNotFoundException` for `filament.area_coordinator.resources.voters.index`, crashing the entire dashboard render — a real, live production bug that predates this plan and was never caught by prior Livewire::test()-level widget coverage (which never sets an HTTP-routed active panel, so `Filament::getCurrentOrDefaultPanel()` silently fell back to `admin`, where the route exists).
- **Fix:** Added a private `voterResourceUrl(string $name, array $parameters = []): ?string` helper to both widgets that checks `Route::has("filament.{$panelId}.resources.voters.{$name}")` against `Filament::getCurrentOrDefaultPanel()?->getId()` before calling `VoterResource::getUrl()`; returns `null` (link omitted) instead of crashing when the route doesn't exist for the current panel.
- **Files modified:** `app/Filament/Widgets/CampaignStatsOverview.php`, `app/Filament/Widgets/TopLeadersTable.php`
- **Verification:** Both new browser tests pass; full `tests/Browser` suite (4/4); full regression sweep of every widget/dashboard test touching these two classes (55 tests across 7 files) green.
- **Committed in:** `ead3920` (Task 1), corrected in `63eb79a` (Task 2)

**2. [Rule 1 - Bug] Guard broke 2 pre-existing `WidgetDrillThroughTest` assertions**
- **Found during:** Task 2's full-suite regression sweep
- **Issue:** The Task 1 guard used `Filament::getCurrentPanel()`, which is `null` in `Livewire::test()`-only contexts (no real HTTP-routed panel request). This broke `WidgetDrillThroughTest`'s "top leaders table rows link to..." and "campaign stats overview...stats link to..." assertions, which rely on Filament's own default-panel fallback (`Resource::getUrl()` internally resolves via `Filament::getCurrentOrDefaultPanel()`) to still generate an admin-panel URL even without an active HTTP-routed panel.
- **Fix:** Switched the guard from `Filament::getCurrentPanel()` to `Filament::getCurrentOrDefaultPanel()`, matching Filament's own internal resolution exactly.
- **Files modified:** `app/Filament/Widgets/CampaignStatsOverview.php`, `app/Filament/Widgets/TopLeadersTable.php`
- **Verification:** `WidgetDrillThroughTest` (11/11) and `DashboardWidgetsTest` (20/20) pass again; the new browser test and full `tests/Browser` suite still pass (confirms the Coordinador/Líder/Articulador crash fix from Task 1 still holds under real HTTP routing).
- **Committed in:** `63eb79a`

**3. [Rule 1 - Bug] False-positive single-digit substring match in the browser test itself**
- **Found during:** Task 1, second test ("cross-articulador isolation")
- **Issue:** `$page->assertDontSee(number_format(4))` failed with "Expected not to see text [4]... but it was found" even though team A's total (4) was never rendered on team B's dashboard — Playwright's `assertDontSee()` does a visible-text substring match, and the fixture leader's Faker-random phone number (rendered in the `TopLeadersTable` row) coincidentally contained the digit "4", unrelated to the actual scoping behavior under test.
- **Fix:** Pinned deterministic `email`/`phone` values (no "4" digit) on `leaderA1`/`leaderB1` in both tests instead of leaving them to Faker's random generator.
- **Files modified:** `tests/Browser/ArticuladorDashboardWidgetScopingTest.php`
- **Verification:** Both tests pass consistently.
- **Committed in:** `ead3920`

---

**Total deviations:** 3 auto-fixed (2 Rule 1 bugs in production widgets, 1 Rule 1 test-fragility fix)
**Impact on plan:** All three were necessary to make the plan's own must-have truths achievable at all — the widget crash directly blocked the dashboard from ever rendering, and the digit-collision fix was necessary for the test to be deterministic. No scope creep beyond what was required to close Human-UAT item 1.

## Issues Encountered
- **Stale worktree (recurring, previously documented class):** This worktree was 78 commits behind `main` at session start — missing Phases 16-19's entire planning corpus (including this plan's own `19-03-PLAN.md`), plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install`, and copied `public/build/` from the main checkout (this plan makes no frontend asset changes, so `npm run build` was not needed). Playwright's Chromium browser cache is a global (`~/Library/Caches/ms-playwright`), non-worktree-scoped resource and was already present, so no `npx playwright install` was needed this time. `npm install` regenerated a spurious `package-lock.json` `name` field change (picked up the worktree directory name) — reverted via `git checkout -- package-lock.json` before committing, per the project's convention of never committing unrelated environment artifacts.

## Next Phase Readiness
- Human-UAT item 1 (`15-HUMAN-UAT.md`) is now closed with automated real-browser coverage, and the underlying scoping bug (19-01) plus its previously-hidden crash companion (this plan) are both fixed and regression-tested.
- `voterResourceUrl()`'s pattern is directly reusable if any future widget/table needs a similar cross-panel-safe resource link.
- No blockers identified for subsequent Phase 19 plans (19-04, 19-05, 19-06).

---
*Phase: 19-articulador-panel-human-uat-closure*
*Completed: 2026-08-12*

## Self-Check: PASSED

- FOUND: tests/Browser/ArticuladorDashboardWidgetScopingTest.php
- FOUND: app/Filament/Widgets/CampaignStatsOverview.php
- FOUND: app/Filament/Widgets/TopLeadersTable.php
- FOUND: commit ead3920
- FOUND: commit 63eb79a
