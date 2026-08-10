---
phase: 15-articulador-self-service-panel
plan: 05
subsystem: ui
tags: [filament, livewire-volt, flux, navigation, spatie-permission]

# Dependency graph
requires:
  - phase: 15-articulador-self-service-panel (plans 15-01 through 15-04)
    provides: AreaCoordinatorPanelProvider, /articulador routes, and the three articulador coordinador-management Volt pages (list/create/edit) with authorization and campaign scoping already verified
provides:
  - A "Coordinadores" Filament NavigationItem on the /articulador panel dashboard, linking to route('articulador.coordinadores')
  - An area_coordinator branch in the shared Volt sidebar (Dashboard/Coordinadores/Día D), mirroring the existing coordinador branch
  - An "Articulador" role label in the sidebar campaign header
  - A structural link (via the sidebar header anchor + navlist Dashboard item) back from the Volt CRUD pages to the Filament panel dashboard
affects: [16-metadata-catalog-ui-assignment, 17-filter-sort-export-surfaces]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament Panel::navigationItems() with a closure-based ->url() for cross-surface links registered before all routes are guaranteed booted"
    - "Blade @elseif role-branch mirroring in a shared sidebar partial, ordered to preserve existing role branches unchanged"

key-files:
  created:
    - tests/Feature/Articulador/ArticuladorNavigationTest.php
  modified:
    - app/Providers/Filament/AreaCoordinatorPanelProvider.php
    - resources/views/components/layouts/app/sidebar.blade.php
    - .planning/REQUIREMENTS.md

key-decisions:
  - "Deliberate divergence from 15-CONTEXT.md D-06: added a structural link between the Filament panel and the Volt CRUD pages (NavigationItem + sidebar Dashboard link back to route('filament.area_coordinator.pages.dashboard')), because the articulador lands on the Filament panel post-login (D-06 forbade a Volt dashboard route), so without this link the landing surface was a dead end for reaching the coordinador-management pages."
  - "Fixed a locale bug in the plan's own RED-state assertion: __('Platform') translates to 'Plataforma' under APP_LOCALE=es, so a hardcoded English 'Platform' needle in assertDontSee() was a no-op in both RED and GREEN states. Switched the needle to __('Platform') so the assertion is locale-correct and meaningful."

patterns-established:
  - "Panel NavigationItem url() values must be closures (fn (): string => route(...)), not bare route() calls, since panel providers boot before all routes are guaranteed registered."

requirements-completed: [ARTIC-02]

# Metrics
duration: ~20min
completed: 2026-08-10
---

# Phase 15 Plan 05: Articulador Navigation Reachability Summary

**Closed the sole reachability gap from 15-VERIFICATION.md by wiring a Filament NavigationItem and a shared-sidebar `area_coordinator` branch so an articulador can reach their coordinador-management pages by clicking, not by typing a URL.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-08-10T23:29:00Z (approx.)
- **Completed:** 2026-08-10T23:49:04Z
- **Tasks:** 4/4 completed
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments
- Articulador panel dashboard (`/articulador`) now renders a "Coordinadores" navigation item linking to `route('articulador.coordinadores')`.
- The shared Volt sidebar now has a dedicated "Articulación" group (Dashboard/Coordinadores/Día D) for `area_coordinator` users, replacing the generic "Platform" fallback they previously fell into.
- The sidebar campaign header now shows "Articulador" as the role label, mirroring "Administrador de Campaña" / "Coordinador".
- A structural link back from the Volt CRUD pages to the Filament panel dashboard closes the navigation loop the coordinador already had.
- 6 new Pest tests prove the nav item and sidebar branch render for an articulador and are strictly absent for a coordinador (no cross-role leakage).
- Full phase regression suite (66 tests across Articulador/, AreaCoordinatorPanelAccessTest, RoleMiddlewareTest, RoleBasedRedirectTest) passes green, so ARTIC-02 is now closed.

## Task Commits

Each task was committed atomically:

1. **Task 1: Write the failing navigation test suite (RED)** - `c4ed3cb` (test)
2. **Task 2: Add the Coordinadores navigation item to AreaCoordinatorPanelProvider** - `96838d2` (feat)
3. **Task 3: Add the area_coordinator branch and role label to the shared Volt sidebar** - `e33f143` (feat)
4. **Task 4: Run the phase regression suite and close ARTIC-02** - `88b732a` (docs)

_No separate plan-metadata commit — Task 4's commit already carries the REQUIREMENTS.md update; this SUMMARY.md and STATE.md/ROADMAP.md updates will be captured in the final metadata commit._

## Files Created/Modified
- `tests/Feature/Articulador/ArticuladorNavigationTest.php` - 6 Pest tests covering the panel nav item, the coordinador panel's negative guard, the articulador sidebar group, the absent Platform group, the Articulador role label, and the coordinador's negative sidebar guard
- `app/Providers/Filament/AreaCoordinatorPanelProvider.php` - Added `->navigationItems([NavigationItem::make('Coordinadores')...])` pointing at `route('articulador.coordinadores')`
- `resources/views/components/layouts/app/sidebar.blade.php` - Added the `area_coordinator` header-href arm, role-label arm, and navlist branch (Dashboard/Coordinadores/Día D)
- `.planning/REQUIREMENTS.md` - Marked ARTIC-02 `[x]` and `Done` in the traceability table

## Decisions Made
- Deliberately diverged from `15-CONTEXT.md` D-06 by adding a structural link between the Filament panel and the Volt CRUD pages — necessary because the articulador's post-login landing surface (the Filament panel, per D-06) would otherwise be a dead end with no path to the coordinador-management pages. Recorded per the plan's explicit instruction.
- Kept `resources/views/components/layouts/app/header.blade.php` untouched — confirmed dead code (zero renders in `resources/`) per the plan's explicit scope boundary.
- Did not touch `app/Providers/Filament/CoordinatorPanelProvider.php` — the coordinador panel deliberately has no navigation item pointing at articulador surfaces, verified by a dedicated regression test.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a locale-blind assertion in the RED-state test**
- **Found during:** Task 1 (writing the failing navigation test suite)
- **Issue:** The plan's behavior spec called for `assertDontSee('Platform')` (hardcoded English) to prove the generic sidebar group doesn't render for an articulador. `config/app.php`'s `APP_LOCALE` is `es`, and `lang/es.json` translates `"Platform"` → `"Plataforma"`. The hardcoded English needle never matched the rendered Spanish text in either RED or GREEN state, making the assertion a permanent no-op (it "passed" for the wrong reason even before Task 2/3 existed — 3 failed/3 passed in the initial run, not the plan's expected 4 failed/2 passed).
- **Fix:** Changed the needle to `__('Platform')` so it resolves to the actual rendered string (`'Plataforma'`) under the app's configured locale.
- **Files modified:** `tests/Feature/Articulador/ArticuladorNavigationTest.php`
- **Verification:** Re-ran the suite before any production code changes — now correctly shows 4 failed / 2 passed (matching the plan's specified RED state), and the test genuinely fails until Task 3's sidebar branch removes the articulador from the `@else` fallback.
- **Committed in:** `c4ed3cb` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 bug fix)
**Impact on plan:** Test correctness fix only — no change to scope, architecture, or the plan's intended behavior. Without this fix, the "no Platform group" assertion would have silently proven nothing in either RED or GREEN state.

## Issues Encountered
- **Worktree staleness (recurring, documented pattern):** This worktree (`agent-a3a7516501be8223a`) was checked out behind `main`, missing Phases 12-15 entirely plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, and ran `npm install && npm run build`. `npm install` produced a 1-line `package-lock.json` diff (dependency resolution drift, not an intentional change) — left uncommitted as it's outside this plan's scope.
- **`gsd-tools init execute-phase` root-resolution bug (recurring, documented pattern):** `project_root` resolved to the main checkout, not this worktree, confirming the same `findProjectRoot()` issue logged repeatedly in STATE.md's Blockers/Concerns. STATE.md/ROADMAP.md updates for this plan were hand-edited directly in this worktree rather than via the CLI.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- ARTIC-02 is closed — all 4 ARTIC requirements (ARTIC-01 through ARTIC-04) are now Done, completing Phase 15's sole requirement.
- Phase 15 (articulador-self-service-panel) is now fully complete: 5/5 plans done.
- **Outstanding human verification (not this plan's scope, re-flagged per project convention that Pest tests alone are insufficient for UI-facing changes):**
  1. From `15-VERIFICATION.md`: articulador panel widget data scoping (visual confirmation the panel's stat widgets reflect only the articulador's own team).
  2. From `15-VERIFICATION.md`: the cédula autofill lock/unlock interaction on the create-coordinador form, in a real browser.
  3. **New in this plan:** visually confirm the new "Articulación" sidebar group and the "Coordinadores" Filament navigation item render correctly and are clickable for a real articulador login (not just asserted via `assertSee` in Pest).
- No blockers for Phase 16 (Metadata Catalog UI & Assignment), which depends on Phase 12/13 schema and authorization work already complete, not on this plan.

---
*Phase: 15-articulador-self-service-panel*
*Completed: 2026-08-10*

## Self-Check: PASSED

- FOUND: tests/Feature/Articulador/ArticuladorNavigationTest.php
- FOUND: app/Providers/Filament/AreaCoordinatorPanelProvider.php
- FOUND: resources/views/components/layouts/app/sidebar.blade.php
- FOUND: .planning/phases/15-articulador-self-service-panel/15-05-SUMMARY.md
- FOUND: commit c4ed3cb (test)
- FOUND: commit 96838d2 (feat)
- FOUND: commit e33f143 (feat)
- FOUND: commit 88b732a (docs)
