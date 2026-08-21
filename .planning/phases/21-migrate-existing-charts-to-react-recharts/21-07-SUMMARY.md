---
phase: 21-migrate-existing-charts-to-react-recharts
plan: 07
subsystem: ui
tags: [react, recharts, filament, livewire, alpine, dark-mode, vite]

# Dependency graph
requires:
  - phase: 21-03, 21-04, 21-05, 21-06
    provides: All 6 real migrated/new chart widgets (3 ChartWidgets + 3 sparklines) proven end-to-end on the React/Recharts pipeline
provides:
  - Phase 20's throwaway ReactIslandPocWidget, its Browser test, and the transitional 'poc' ChartCard/ChartRouter shim fully removed from the codebase
  - Real Filament dark-mode detection in the React/Alpine bridge (was previously hardcoded to 'light')
  - Human-verified sign-off that all 6 migrated/new chart widgets render correctly together across real panels
  - Phase 21 fully complete — MIGR-01 and MIGR-02 closed
affects: [22-table-stakes-new-visualizations, 23-differentiator-visualizations, 24-dia-d-live-voting-visualization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Live dark-mode detection via document.documentElement.classList.contains('dark') + MutationObserver, matching Filament's own dark-mode.js state instead of a server-computed Blade value"

key-files:
  created:
    - .planning/phases/21-migrate-existing-charts-to-react-recharts/21-07-SUMMARY.md
  modified:
    - app/Filament/Widgets/ReactIslandPocWidget.php (deleted)
    - tests/Browser/ReactIslandPocWidgetTest.php (deleted)
    - resources/js/charts/components/ChartCard.jsx
    - resources/js/charts/ChartRouter.jsx
    - app/Providers/Filament/AdminPanelProvider.php
    - app/Providers/Filament/ReportsPanelProvider.php
    - app/Providers/Filament/CoordinatorPanelProvider.php
    - app/Providers/Filament/AreaCoordinatorPanelProvider.php
    - app/Providers/Filament/LeaderPanelProvider.php
    - resources/js/charts/main.jsx
    - resources/views/filament/widgets/react-chart.blade.php
    - .planning/phases/21-migrate-existing-charts-to-react-recharts/deferred-items.md

key-decisions:
  - "Dark-mode fix (commit c7d1762) treated as an in-scope Rule 1 bug fix surfaced by the human checkpoint itself, not a new feature — theme was silently hardcoded to 'light' regardless of the panel's real color scheme"
  - "Pre-existing CampaignContext full-suite test-pollution class re-confirmed at plan closure with a different single failing test than any prior run, consistent with the already-documented non-deterministic pattern — not fixed, out of this plan's scope"
  - "Human browser checkpoint approved by the user directly ('si, se ve muchisimo mejor, approved') after the theme fix, standing in as the checkpoint's verification record since Chrome-extension conflicts blocked the orchestrator's own confirming screenshot"

patterns-established:
  - "Theme-reactive Alpine/React bridges should read live DOM state via a MutationObserver rather than trusting a value computed once server-side in Blade, whenever the underlying source of truth (Filament's dark-mode Alpine store) can change without a page reload"

requirements-completed: [MIGR-01, MIGR-02]

# Metrics
duration: ~40min (across the original Task 1/2 session plus this checkpoint-closure continuation)
completed: 2026-08-20
---

# Phase 21 Plan 07: Decommission PoC Widget + Human Checkpoint Summary

**Removed Phase 20's throwaway ReactIslandPocWidget end-to-end, fixed a real hardcoded-light-theme bug the human checkpoint surfaced, and closed Phase 21 with the user's explicit browser sign-off on all 6 migrated/new chart widgets.**

## Performance

- **Duration:** ~40 min (Tasks 1-2 + theme fix + checkpoint closure)
- **Started:** 2026-08-20T22:09:49-05:00 (Task 1 commit)
- **Completed:** 2026-08-20T22:41:59-05:00 (this closure commit)
- **Tasks:** 3/3 (2 auto + 1 human checkpoint)
- **Files modified:** 12 (9 production/test files across Tasks 1-2 + theme fix, 1 deferred-items.md doc, 1 summary)

## Accomplishments

- Deleted the throwaway `ReactIslandPocWidget.php`, its Browser test, and the transitional `'poc'` shim in `ChartCard.jsx`/`ChartRouter.jsx` — zero remaining references anywhere in the codebase
- Removed the widget's registration from all 5 `PanelProvider`s (Admin, Reports, Coordinator, AreaCoordinator, Leader)
- Found and fixed a real production bug during the human checkpoint: every chart rendered with a hardcoded light-theme background regardless of the panel's actual dark-mode state — fixed by reading `document.documentElement`'s `dark` class live plus a `MutationObserver` for live theme toggles
- User explicitly approved the full checkpoint in their own real Chrome browser after the fix ("si, se ve muchisimo mejor, approved")
- Phase 21 is now fully complete — all 7 plans done, MIGR-01 and MIGR-02 both closed

## Task Commits

Each task was committed atomically:

1. **Task 1: Delete ReactIslandPocWidget, its Browser test, and the poc shim** - `15a7cb7` (chore)
2. **Task 2: Remove ReactIslandPocWidget's registration from all 5 PanelProviders** - `6a914a1` (chore)
3. **Task 3: Human browser checkpoint** - verification-only task, no direct file changes; the real bug it surfaced was fixed and committed separately:
   - `c7d1762` (fix): detect real Filament dark mode instead of hardcoded 'light' theme
   - `0b7faa4` (docs): log pre-existing CampaignContext test-pollution as deferred
   - `ab42842` (docs): reconfirm pre-existing test-pollution class at plan closure

**Plan metadata:** (this commit) `docs(21-07): complete decommission-poc-widget-and-human-checkpoint plan`

## Files Created/Modified

- `app/Filament/Widgets/ReactIslandPocWidget.php` - deleted (Phase 20 throwaway PoC, no longer needed)
- `tests/Browser/ReactIslandPocWidgetTest.php` - deleted (its only test)
- `resources/js/charts/components/ChartCard.jsx` - removed the `kind === 'poc'` transitional branch
- `resources/js/charts/ChartRouter.jsx` - removed the `'poc'` alias from `KIND_COMPONENTS`
- `app/Providers/Filament/{Admin,Reports,Coordinator,AreaCoordinator,Leader}PanelProvider.php` - removed `ReactIslandPocWidget` import + registration line from each
- `resources/js/charts/main.jsx` - `reactChartBridge` now computes `theme` live via `isDarkMode()` (reads `document.documentElement.classList.contains('dark')`) on every render, plus a `MutationObserver` that re-renders in place when the user toggles theme without reload
- `resources/views/filament/widgets/react-chart.blade.php` - removed the now-unused hardcoded `theme: @js('light')` param from `x-data`
- `.planning/phases/21-migrate-existing-charts-to-react-recharts/deferred-items.md` - documented the pre-existing `CampaignContext` test-pollution class (found during Task 1/2's regression run) and re-confirmed it at plan closure

## Decisions Made

- The dark-mode fix found during the checkpoint is a legitimate in-scope Rule 1 (bug) fix, not a new feature — the checkpoint's own purpose is exactly this kind of "does it actually look right in a real browser" verification, and a chart rendering the wrong palette background is broken behavior, not a missing capability.
- The human checkpoint's sign-off is recorded as given directly by the user in their own real browser session ("si, se ve muchisimo mejor, approved") rather than re-performed by this closing agent, since Pest Browser/Livewire tests alone don't satisfy this project's standing "browser-verify before prod" convention (established Phase 20 D-04) and the verification had already genuinely happened.
- MIGR-01 and MIGR-02 are marked Done in this plan (not deferred further) — this is the last plan in Phase 21, and all 3 `ChartWidget`s (`ValidationProgressChart`, `TerritorialDistributionChart`, `SurveyResultsWidget`, closed in Plans 21-03/21-04) plus all 3 sparklines (`CampaignVotersSparklineWidget`, `SurveyResponsesSparklineWidget` from 21-05, `CallCenterCallsSparklineWidget` from 21-06) are now confirmed rendering through the pipeline with the PoC fully decommissioned.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed hardcoded 'light' theme ignoring Filament's real dark-mode state**
- **Found during:** Task 3 (human browser checkpoint) — the user spotted every chart's inner chart-area rendering with a stark white/light background even while the surrounding dashboard was in dark mode
- **Issue:** `react-chart.blade.php` passed `theme: @js('light')` unconditionally into every chart's Alpine bridge (`reactChartBridge`), regardless of the panel's actual current color scheme
- **Fix:** `main.jsx`'s `reactChartBridge` now reads `document.documentElement.classList.contains('dark')` directly on every render — the same DOM state Filament's own `dark-mode.js` toggles — computing `theme` live instead of trusting a value baked in once server-side. Added a `MutationObserver` watching `<html>`'s `class` attribute so charts re-render in place (no reload) if the user toggles theme live. Removed the now-unused `theme` param from the blade's `x-data` call.
- **Files modified:** `resources/js/charts/main.jsx`, `resources/views/filament/widgets/react-chart.blade.php`
- **Verification:** User re-verified in a real Chrome browser session across both light and dark mode and replied "si, se ve muchisimo mejor, approved"
- **Committed in:** `c7d1762`

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug)
**Impact on plan:** Necessary correctness fix directly surfaced by the checkpoint's own purpose (real-browser visual verification). No scope creep — no new features, no architectural change.

## Issues Encountered

- The orchestrator itself was blocked from taking a confirming screenshot after the theme fix by a known, previously-documented Chrome extension conflict (the "Jam" extension, same issue class noted in Phase 20's STATE.md decision log for D-04). The user's own direct confirmation in their real browser session stands as the checkpoint's verification record instead.
- Final full-suite `php artisan test` run reproduced the already-documented, pre-existing `CampaignContext` static-override test-pollution class (`Tests\Feature\Filament\UserResourceTest > can update user campaigns` failed on this run; passed cleanly in isolation immediately after). Confirmed unrelated to this plan's changes and consistent with `deferred-items.md`'s prior findings — not fixed, out of scope.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 21 (Migrate Existing Charts to React/Recharts) is fully complete: all 7 plans done, MIGR-01 and MIGR-02 both closed, zero PoC code remaining anywhere in the codebase.
- All 6 real chart widgets (3 `ChartWidget`s + 3 sparklines) are proven end-to-end with real Pest 4 Browser test coverage (14/14 Browser tests passing) and explicit human sign-off in a real browser, including correct dark/light theming.
- Phase 22 (Table-Stakes New Visualizations) can proceed on top of a clean, PoC-free React/Recharts pipeline with a corrected theme-detection mechanism already in place for any new chart widget to reuse as-is.
- No blockers. The pre-existing `CampaignContext` full-suite test-pollution tech debt remains open and tracked (per `.planning/PROJECT.md`'s "Post-v1.2 state" note) — unrelated to and unaffected by this phase's work.

## Self-Check: PASSED

- CONFIRMED DELETED: `app/Filament/Widgets/ReactIslandPocWidget.php`
- CONFIRMED DELETED: `tests/Browser/ReactIslandPocWidgetTest.php`
- FOUND: `resources/js/charts/components/ChartCard.jsx`
- FOUND: `resources/js/charts/ChartRouter.jsx`
- FOUND: `resources/js/charts/main.jsx`
- FOUND: `resources/views/filament/widgets/react-chart.blade.php`
- FOUND: `.planning/phases/21-migrate-existing-charts-to-react-recharts/21-07-SUMMARY.md`
- FOUND: `.planning/phases/21-migrate-existing-charts-to-react-recharts/deferred-items.md`
- FOUND COMMIT: `15a7cb7`
- FOUND COMMIT: `6a914a1`
- FOUND COMMIT: `c7d1762`
- FOUND COMMIT: `0b7faa4`
- FOUND COMMIT: `ab42842`

---
*Phase: 21-migrate-existing-charts-to-react-recharts*
*Completed: 2026-08-20*
