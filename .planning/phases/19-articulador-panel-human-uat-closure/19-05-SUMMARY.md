---
phase: 19-articulador-panel-human-uat-closure
plan: 05
subsystem: testing
tags: [pest, browser-testing, playwright, filament, volt, articulador, navigation]
dependency_graph:
  requires:
    - phase: 19-02
      provides: "Shared loginRealBrowserUser(User $user, string $password = 'password') global function in tests/Pest.php"
  provides:
    - "Real-browser regression coverage proving the articulador's navigation click-through (Filament dashboard -> Coordinadores -> Día D -> back to Dashboard) lands correctly on both of the role's two distinct nav surfaces"
  affects:
    - "15-HUMAN-UAT.md item 3 (Navigation click-through), closing its human_needed status from 15-VERIFICATION.md"
tech_stack:
  added: []
  patterns:
    - "Filament sidebar active-state assertion pattern: assertAttributeContains('li.fi-sidebar-item:has(a[href=\"...\"])', 'class', 'fi-active') — the bare li:has(...) selector without the .fi-sidebar-item class also matches the wrapping .fi-sidebar-group li, causing a Playwright strict-mode multi-element violation"
    - "Volt flux:navlist active-state assertion pattern: assertDataAttribute for the active item (data-current='data-current'), assertAttributeMissing for inactive items (no data-current attribute at all, never '0')"
    - "When a route's href is shared between a panel's brand-logo/nav-item duplicate anchors, scope the click/assertion selector to a structural marker (a.fi-sidebar-item-btn for Filament's own sidebar, a[data-flux-navlist-item] for the shared Volt sidebar) instead of a bare a[href=...] selector"
key_files:
  created:
    - tests/Browser/ArticuladorNavigationClickThroughTest.php
  modified:
    - app/Filament/Pages/DiaD.php
    - app/Filament/Widgets/CampaignStatsOverview.php
decisions:
  - "[Rule 1 - Bug] DiaD::canAccess() was missing the 'area_coordinator' role entirely, even though AreaCoordinatorPanelProvider registers DiaD as one of its own panel pages and the shared Volt sidebar links every articulador to /articulador/dia-d. Every articulador clicking their own Día D nav link got a hard 403. Added 'area_coordinator' to the canAccess() role list."
  - "[Rule 1 - Bug] CampaignStatsOverview's 'Total de Apoyos'/'Apoyos Confirmados' stat cards called VoterResource::getUrl(), which resolves its route name from Filament::getCurrentOrDefaultPanel() — the CURRENT panel context, not VoterResource's own registered panel. VoterResource is only registered on the admin panel (auto-discovered), never on coordinator or area_coordinator. Rendering this shared widget on either of those panels threw a RouteNotFoundException and broke the entire Dashboard page render, discovered because it blocked this plan's own browser test from ever reaching a working Dashboard page. Added a getVoterResourceUrl() helper that returns null (no link, not a crash) when the current panel doesn't have VoterResource registered — verified via the full pre-existing DashboardWidgetsTest/OwnershipScopedWidgetsTest/WidgetDrillThroughTest suites (57 tests) still passing unchanged, including the admin-panel drill-through test that DOES still get a real link."
  - "Followed the Plan 19-02-established precedent for the pre-existing, out-of-scope phpunit.xml gap: php artisan test --testsuite=Browser silently reports \"No tests found\" (no Browser testsuite entry exists in phpunit.xml). Verified Task 2's actual intent instead by running all tests/Browser/*.php files directly in one process — 4 passed, no redeclare errors, no cross-test pollution."
metrics:
  duration_minutes: 45
  tasks_completed: 2
  files_changed: 3
  completed_date: "2026-08-12"
---

# Phase 19 Plan 05: Articulador Navigation Click-Through Browser Test Summary

Real Chromium Pest v4 Browser test proving an articulador's full navigation click-through (Filament panel dashboard → Coordinadores → Día D → back to Dashboard) works end-to-end across both of the role's distinct nav surfaces, and along the way found + fixed two real production bugs (a hard 403 on the articulador's own Día D page, and a Dashboard-page-crashing RouteNotFoundException) that were silently blocking the exact page this test needed to render.

## What Was Built

### Task 1: Write and run the navigation click-through browser test

- Created `tests/Browser/ArticuladorNavigationClickThroughTest.php` with two tests:
  1. Full click-through: Filament dashboard (asserts `fi-active` on the panel's own sidebar) → click the panel's own "Coordinadores" `NavigationItem` → land on the Volt `articulador.coordinadores` page (asserts `data-current="data-current"` on the shared Volt sidebar's Coordinadores item) → click "Día D" → land on `/articulador/dia-d` → click back to Dashboard → asserts `fi-active` again on the Filament sidebar.
  2. A second, smaller test confirming a fresh direct visit to the Volt coordinadores list marks the shared sidebar's Coordinadores item current (not Dashboard).
- Confirmed via `vendor/filament/filament/resources/views/components/sidebar/item.blade.php` (per the plan's `read_first`) that Filament's own sidebar marks the active item with a `fi-active` class on the wrapping `<li>`, not a `data-current` attribute — used `assertAttributeContains('li.fi-sidebar-item:has(...)', 'class', 'fi-active')` for those assertions, and `assertDataAttribute`/`assertAttributeMissing` only for the Volt-sidebar-rendered assertions, exactly as the plan's interfaces section specified.

Three real bugs/ambiguities surfaced and were fixed while getting this test to pass:

1. **Selector ambiguity — Filament sidebar group wrapper.** `li:has(a[href="..."])` matched both the wrapping `.fi-sidebar-group` `<li>` AND the actual `.fi-sidebar-item` `<li>` (Playwright's `:has()` matches any descendant, not just direct children). Fixed by scoping to `li.fi-sidebar-item:has(...)`.
2. **Selector ambiguity — duplicate anchors sharing the dashboard href.** On the shared Volt sidebar, the brand-logo `<a>` (top of sidebar) points at the same dashboard route as the "Dashboard" `flux:navlist.item`. On Filament's own sidebar there are actually two brand-logo anchors (light/dark variants) plus the real `fi-sidebar-item-btn` nav anchor, all pointing at the same href. Fixed by scoping click/assertion selectors to structural markers instead of bare `a[href=...]`: `a[data-flux-navlist-item]` for the Volt sidebar, `a.fi-sidebar-item-btn` for Filament's own sidebar.
3. **Real bug — `DiaD::canAccess()` missing `area_coordinator`.** Clicking the Día D link as an articulador threw a hard 403 (`abort_unless(false, 403)` in Filament's `CanAuthorizeAccess`), even though `AreaCoordinatorPanelProvider` registers `DiaD::class` as one of its own pages and the shared sidebar explicitly links articuladores there. Fixed by adding `'area_coordinator'` to the `hasRole([...])` array.
4. **Real bug — `CampaignStatsOverview`'s stat links crashed the Dashboard for non-admin panels.** `VoterResource::getUrl('index')` resolves its route name via `Filament::getCurrentOrDefaultPanel()`, but `VoterResource` is only registered on the `admin` panel (via `discoverResources`) — never on `coordinator` or `area_coordinator`. Rendering the widget there threw `RouteNotFoundException: Route [filament.area_coordinator.resources.voters.index] not defined`, which broke the entire Dashboard page (not just the stat card). Fixed with a `getVoterResourceUrl()` helper that checks `Filament::getCurrentOrDefaultPanel()->getResources()` and returns `null` (no link) instead of crashing when `VoterResource` isn't registered on the current panel.

### Task 2: Style check and full Browser suite regression

- `vendor/bin/pint --dirty` — 3 files, no style issues.
- Ran the full `Browser` suite directly (per the Plan 19-02-established workaround for the pre-existing `phpunit.xml` gap — see Deviations): `php artisan test tests/Browser/ArticuladorNavigationClickThroughTest.php tests/Browser/RegistraduriaPollingResilienceTest.php` → **4 passed (12 assertions)**, no redeclare errors, no cross-test pollution.
- Confirmed no regression from the two bug fixes: `tests/Feature/DashboardWidgetsTest.php`, `tests/Feature/OwnershipScopedWidgetsTest.php`, `tests/Feature/WidgetDrillThroughTest.php` (39 tests), the full `--filter="DiaD"` suite (30 tests), and the full `--filter="Widget"` suite (57 tests) all pass unchanged, including the admin-panel drill-through test which still gets a real `VoterResource` link.

## Task Commits

1. **Task 1 + Task 2 (combined, Task 2 produced no file changes):** `f845687` — `test(19-05): add articulador navigation click-through browser test`

## Files Created/Modified

- `tests/Browser/ArticuladorNavigationClickThroughTest.php` - New real-browser click-through test (2 tests, 10 assertions)
- `app/Filament/Pages/DiaD.php` - `canAccess()` now includes `area_coordinator`
- `app/Filament/Widgets/CampaignStatsOverview.php` - New `getVoterResourceUrl()` helper prevents a `RouteNotFoundException` crash on panels without `VoterResource` registered

## Decisions Made

See frontmatter `decisions` for full detail. In short: two pre-existing production bugs (403 on articulador's Día D, Dashboard-crashing widget link) were auto-fixed under Rule 1/Rule 3 because they directly blocked this plan's own browser test from reaching a working page — both are minimal, non-architectural fixes verified against the full pre-existing regression suite with zero behavior change for the admin panel.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `DiaD::canAccess()` missing `area_coordinator` role**
- **Found during:** Task 1 (writing the click-through test)
- **Issue:** Clicking "Día D" as an articulador threw a hard 403; `canAccess()` only allowed `coordinator`/`leader`/`admin_campaign`/`super_admin`.
- **Fix:** Added `'area_coordinator'` to the `hasRole([...])` array in `app/Filament/Pages/DiaD.php`.
- **Files modified:** `app/Filament/Pages/DiaD.php`
- **Verification:** Full `--filter="DiaD"` suite (30 tests) passes unchanged; the new browser test's click-through to `/articulador/dia-d` now succeeds instead of hitting a 403.
- **Committed in:** `f845687`

**2. [Rule 1 - Bug] `CampaignStatsOverview` crashed the Dashboard on panels without `VoterResource`**
- **Found during:** Task 1 (the very first dashboard visit in the browser test threw `RouteNotFoundException` mid-render)
- **Issue:** `VoterResource::getUrl('index')` resolves the route name from the CURRENT panel context (`Filament::getCurrentOrDefaultPanel()`), not `VoterResource`'s own registered panel. `VoterResource` is only auto-discovered on the `admin` panel — `coordinator` and `area_coordinator` panels never register it — so this shared widget threw `Route [filament.area_coordinator.resources.voters.index] not defined` and broke the whole Dashboard page render for both roles.
- **Fix:** Added `getVoterResourceUrl(string $name, array $parameters = []): ?string` which checks `in_array(VoterResource::class, Filament::getCurrentOrDefaultPanel()->getResources(), true)` and returns `null` (no link, `Stat::url()` accepts `null`) instead of crashing when the resource isn't registered on the current panel.
- **Files modified:** `app/Filament/Widgets/CampaignStatsOverview.php`
- **Verification:** `tests/Feature/DashboardWidgetsTest.php`, `tests/Feature/OwnershipScopedWidgetsTest.php`, `tests/Feature/WidgetDrillThroughTest.php` (39 tests total, including the admin-panel drill-through assertion that still gets a real link) all pass unchanged.
- **Committed in:** `f845687`

---

**Total deviations:** 2 auto-fixed (both Rule 1 - bug, both also qualifying as Rule 3 - blocking issue since they prevented the Dashboard page from rendering for this plan's own test)
**Impact on plan:** Both fixes are pre-existing production bugs unrelated to this plan's stated scope, but both directly blocked the plan's own test from reaching a working page. Both are minimal (no schema/architecture change), verified against the full existing regression suite with zero behavior change outside the two broken paths.

## Issues Encountered

- Three Playwright strict-mode "resolved to N elements" selector-ambiguity errors during test authoring (documented above and in `tech_stack.patterns`), all resolved by scoping selectors to structural markers instead of bare `a[href=...]`.
- `php artisan test --testsuite=Browser` (Task 2's literal verify command) silently reports "No tests found" — same pre-existing `phpunit.xml` gap already documented in `deferred-items.md` by Plan 19-02. Not re-logged as new; verified via direct file invocation instead, per established precedent.

## Worktree Staleness (environment setup, not a plan deviation)

This worktree (`agent-ae1ce010ceba500b2`) was on a stale Phase-15 commit at session start — missing Phases 16-19 entirely, plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry against `refs/heads/main` (this repo's own main worktree shares the object store), `git merge --ff-only refs/heads/main`, `.env` copy from the main checkout, `composer install`, `npm install`, `npm run build`. The Playwright Chromium browser cache (`~/Library/Caches/ms-playwright`) was already present globally and did not need reinstalling. `package-lock.json`'s `name` field was reverted after `npm install` overwrote it with this worktree's directory name — not part of this plan's work.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Human-UAT item 3 (Navigation click-through, `15-HUMAN-UAT.md`) is now closed by a genuine automated real-browser regression test — no manual verification needed going forward.
- The two bug fixes (articulador Día D 403, Dashboard-crashing stat link) are real production fixes that also benefit the live app immediately, independent of this plan's test-closure purpose.
- No blockers for remaining Phase 19 plans (19-03, 19-04, 19-06).

## Self-Check: PASSED

- FOUND: `tests/Browser/ArticuladorNavigationClickThroughTest.php`
- FOUND: `app/Filament/Pages/DiaD.php`
- FOUND: `app/Filament/Widgets/CampaignStatsOverview.php`
- FOUND: commit `f845687` — `test(19-05): add articulador navigation click-through browser test`

---
*Phase: 19-articulador-panel-human-uat-closure*
*Completed: 2026-08-12*
