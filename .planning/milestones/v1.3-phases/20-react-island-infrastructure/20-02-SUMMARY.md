---
phase: 20-react-island-infrastructure
plan: 02
subsystem: infra
tags: [react, recharts, motion, alpine, filament, livewire, vite, pest-browser]

# Dependency graph
requires:
  - phase: 20-01
    provides: Vite build pipeline (resources/js/charts/main.jsx entry), ChartCard.jsx component, Alpine reactChartBridge bridge, vite.config.js multi-entry setup
provides:
  - ReactIslandPocWidget (ChartWidget subclass) proving the wire:poll -> dispatch('updateChartData') -> Alpine bridge -> React root.render() cycle works end-to-end
  - resources/views/filament/widgets/react-chart.blade.php shared view (the ONE Blade view every future React-backed ChartWidget in Phase 21+ will repoint $view to)
  - Vite chart entry + PoC widget registered on all 5 PanelProviders (Admin, Reports, Coordinator, AreaCoordinator, Leader)
  - Real Pest 4 Browser test convention (tests/Browser/ReactIslandPocWidgetTest.php) for asserting rendered content across a genuine poll cycle
  - Fix for a real Alpine-reactivity/React-root crash in the shared bridge (resources/js/charts/main.jsx)
affects: [21-chart-migration, any future phase adding a React-backed ChartWidget]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ChartWidget subclasses repoint `$view` to the shared `filament.widgets.react-chart` Blade partial instead of each writing their own"
    - "PanelProvider render hook `PanelsRenderHook::HEAD_END` registers the React/Recharts/Motion Vite entry per-panel (not global layout)"
    - "Never store a React root instance (or other external-library object graph) as a property on an Alpine.data() returned object — keep it in a closure variable instead, since Alpine's reactivity Proxy deep-wraps every returned property and crashes on non-configurable internals"

key-files:
  created:
    - app/Filament/Widgets/ReactIslandPocWidget.php
    - resources/views/filament/widgets/react-chart.blade.php
    - tests/Browser/ReactIslandPocWidgetTest.php
  modified:
    - app/Providers/Filament/AdminPanelProvider.php
    - app/Providers/Filament/ReportsPanelProvider.php
    - app/Providers/Filament/CoordinatorPanelProvider.php
    - app/Providers/Filament/AreaCoordinatorPanelProvider.php
    - app/Providers/Filament/LeaderPanelProvider.php
    - resources/js/charts/main.jsx

key-decisions:
  - "Fixed the React-root-in-Alpine-reactive-object crash by keeping `root` as a closure variable in the Alpine.data() factory rather than `this._root` — the correct, minimal fix for this class of bug rather than disabling Alpine reactivity globally or switching mount strategy"

patterns-established:
  - "Pattern: shared react-chart.blade.php view + per-subclass getData()/getChartKind() is the only thing future chart widgets need to change"
  - "Pattern: Pest 4 Browser tests for chart widgets must assert on data-testid content before AND after a real wait() past the polling interval, plus assertNoJavaScriptErrors() — not just 'page loaded'"

requirements-completed: [INFRA-02, INFRA-03, INFRA-04]

# Metrics
duration: ~35min
completed: 2026-08-20
---

# Phase 20 Plan 02: React Island Widget Wiring + Cross-Panel Registration Summary

**Wired the 20-01 Alpine/React bridge into a real Filament ChartWidget, registered the Vite chart entry across all 5 panels, and proved the poll→dispatch→render cycle with a real Pest 4 Browser test — fixing a genuine Alpine-reactivity crash discovered along the way.**

## Performance

- **Duration:** ~35 min (environment setup — stale worktree merge, composer/npm install, Playwright chromium reinstall — plus 3 tasks)
- **Completed:** 2026-08-20
- **Tasks:** 3/3
- **Files modified:** 6 modified/created (widget + view + test) + 5 PanelProviders + 1 shared bridge fix

## Accomplishments

- `ReactIslandPocWidget` (a `ChartWidget` subclass) renders real Recharts+Motion content on the Admin dashboard, polling every 10s with data that changes almost every tick
- `resources/views/filament/widgets/react-chart.blade.php` established as the one shared view future chart widgets will reuse, with the wire:poll(outer)/wire:ignore(inner) skeleton and D-03's Blade-level "bundle never loaded" fallback (5s timeout + `data-react-fallback`)
- All 5 PanelProviders (Admin, Reports, Coordinator, AreaCoordinator, Leader) register `resources/js/charts/main.jsx` via a `HEAD_END` render hook and host the PoC widget
- A real Pest 4 Browser test (`ReactIslandPocWidgetTest.php`) proves rendered content on load, that it changes after a genuine 10s `wire:poll` tick, and asserts zero JavaScript console errors
- Found and fixed a real crash in the shared bridge (`resources/js/charts/main.jsx`, built in 20-01): the React root instance was stored as `this._root` on the Alpine.data() returned object, which Alpine's reactivity system tries to deep-proxy — React's internal root object has non-configurable properties that violate JS Proxy invariants, crashing before any content rendered. Fixed by keeping the root in a closure variable instead.

## Task Commits

1. **Task 1: Create the throwaway ChartWidget subclass + shared Blade view** - `1861522` (feat)
2. **Task 2: Register the Vite chart entry + PoC widget on all 5 PanelProviders** - `6e43c86` (feat)
3. **Task 3: Pest 4 Browser test — real rendered content across a poll cycle (INFRA-04)** - `b62d72d` (test, includes the Rule 1 bug fix to `resources/js/charts/main.jsx`)

**Plan metadata:** (this commit)

## Files Created/Modified

- `app/Filament/Widgets/ReactIslandPocWidget.php` - `ChartWidget` subclass, repointed `$view`, 10s polling, `getData()` returns a value that changes almost every tick via `now()->second`
- `resources/views/filament/widgets/react-chart.blade.php` - shared wire:poll/wire:ignore skeleton, `reactChartBridge` Alpine invocation, D-03 fallback timeout script
- `app/Providers/Filament/AdminPanelProvider.php` - registers Vite chart entry (`HEAD_END`) + `ReactIslandPocWidget`
- `app/Providers/Filament/ReportsPanelProvider.php` - same
- `app/Providers/Filament/CoordinatorPanelProvider.php` - same
- `app/Providers/Filament/AreaCoordinatorPanelProvider.php` - same
- `app/Providers/Filament/LeaderPanelProvider.php` - same
- `resources/js/charts/main.jsx` - fixed the Alpine-reactivity/React-root Proxy crash (root moved to closure variable)
- `tests/Browser/ReactIslandPocWidgetTest.php` - real Chromium Pest 4 Browser test proving rendered content across a poll cycle

## Decisions Made

- Kept the fix for the React-root/Alpine-reactivity crash minimal and localized (closure variable instead of `this._root`) rather than reworking the bridge's overall architecture — the mount/unmount/dispatch contract established in 20-01 remains unchanged, only where the root reference lives changed.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed a pre-existing `binary_operator_spaces` style issue in CoordinatorPanelProvider.php/LeaderPanelProvider.php**
- **Found during:** Task 2
- **Issue:** Both files had a pre-existing double-space alignment (`50  => '#fff7ed',`) in their `colors()` array, unrelated to this plan's edits, which made `vendor/bin/pint --test app/Providers/Filament/` fail (Task 2's own acceptance criterion)
- **Fix:** Ran `vendor/bin/pint --dirty` (already-modified files only), which reformatted the pre-existing whitespace alongside this plan's additive changes
- **Files modified:** app/Providers/Filament/CoordinatorPanelProvider.php, app/Providers/Filament/LeaderPanelProvider.php
- **Verification:** `vendor/bin/pint --test app/Providers/Filament/` exits 0 afterward
- **Committed in:** `6e43c86` (Task 2 commit)

**2. [Rule 1 - Bug] Fixed a crash in the 20-01-built Alpine/React bridge (`resources/js/charts/main.jsx`)**
- **Found during:** Task 3 (writing/running the Browser test — first real end-to-end exercise of the bridge)
- **Issue:** `reactChartBridge`'s Alpine.data() factory stored the React root instance as `this._root` (a property on the object returned to Alpine). Alpine's reactivity system deep-proxies every property of that returned object; React's internal root object contains non-configurable properties, so Alpine's Proxy `get` trap violated JS Proxy invariants and threw `TypeError: 'get' on proxy: property '0' is a read-only and non-configurable data property...` on the very first render attempt — the widget rendered a permanently blank box, confirmed via a Browser test screenshot before the fix
- **Fix:** Moved the root reference to a plain closure variable (`let root = null`) inside the Alpine.data() factory function, outside the object Alpine wraps in reactivity, so it's never proxied
- **Files modified:** resources/js/charts/main.jsx
- **Verification:** `php artisan test tests/Browser/ReactIslandPocWidgetTest.php` passes (asserts real rendered content before/after a poll tick, zero JS errors)
- **Committed in:** `b62d72d` (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking/pre-existing style, 1 real bug)
**Impact on plan:** Both fixes were necessary to make this plan's own acceptance criteria pass. The bug fix (deviation 2) is the more significant one — without it, the entire React island infrastructure this milestone depends on would have silently rendered blank in production, only masked by the fact that no automated test had previously exercised the real mount->poll->re-render cycle end-to-end in a browser.

## Issues Encountered

- This worktree was stale at session start — missing Phase 20 entirely (including 20-01's completed work and this plan's own PLAN.md), plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install`. `npm install` regenerated a spurious `package-lock.json` `name` field change (worktree directory name) — reverted via `git checkout -- package-lock.json` before committing, matching prior phases' documented precedent.
- Playwright's freshly-installed npm package (1.58.2) didn't match its cached Chromium browser binary, causing an initial `PlaywrightOutdatedException` on the Browser test — fixed via `npx playwright install chromium`, per the same pitfall documented in Phase 19's decisions.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The React island infrastructure is now proven end-to-end in a real browser: mount, poll-triggered data update without remount, and no JS errors. Phase 21 can begin migrating real chart widgets by repointing their `$view` to `filament.widgets.react-chart` (or a per-widget variant of it) and reusing `reactChartBridge`.
- The `resources/js/charts/main.jsx` bridge is now safe to use as a template for future chart types — the closure-variable pattern for the React root must be preserved in any copy/variant to avoid reintroducing the Alpine-reactivity crash.
- Per D-04 in `20-CONTEXT.md`, this phase is not done until a human checkpoint confirms the live PoC widget in a real browser (poll cycle updating, no leaked React roots across panel navigation) — that checkpoint is plan 20-03, not part of this plan.
- `ReactIslandPocWidget` is explicitly throwaway (per its own docblock) — safe to delete once Phase 21's first real widget migrates and the infra is verified via the 20-03 human checkpoint.

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/ReactIslandPocWidget.php
- FOUND: resources/views/filament/widgets/react-chart.blade.php
- FOUND: tests/Browser/ReactIslandPocWidgetTest.php
- FOUND: app/Providers/Filament/AdminPanelProvider.php
- FOUND: resources/js/charts/main.jsx
- FOUND commit: 1861522
- FOUND commit: 6e43c86
- FOUND commit: b62d72d

---
*Phase: 20-react-island-infrastructure*
*Completed: 2026-08-20*
