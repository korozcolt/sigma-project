---
phase: 23-differentiator-visualizations
plan: 05
subsystem: ui
tags: [filament, react, recharts, heatmap, call-center, admin-dashboard]

# Dependency graph
requires:
  - phase: 23-01
    provides: HeatmapChart.jsx chart-kind component + ChartRouter.jsx 'heatmap' registration
  - phase: 23-03
    provides: AdminPanelProvider.php's ->widgets([...]) array state after Sankey/StackedArea registration (avoids conflict)
provides:
  - CallerHourHeatmapChart.php — caller x business-hour (7-21) contact-rate % aggregation widget (kind heatmap)
  - Admin dashboard heatmap surfacing WHO is effective WHEN, not just who is busy
  - Real-browser proof of grid render + real positioned tooltip on hover (D-17)
affects: [24-day-d-live-voting-visualization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Driver-aware raw SQL hour extraction (sqlite strftime vs MySQL HOUR()) is safe for hour-of-day
       (unlike week-bucketing, which needed PHP/Carbon in 23-03) since hour extraction is semantically
       identical across drivers - matches the existing BirthdayWidget precedent."
    - "addBinding([...], 'select') pattern for parameterized raw SQL SUM(CASE WHEN col IN (?,?,?) ...)
       inside a select() clause, verified correct binding order via tinker before shipping."

key-files:
  created:
    - app/Filament/Widgets/CallerHourHeatmapChart.php
    - tests/Browser/CallerHourHeatmapChartTest.php
  modified:
    - app/Providers/Filament/AdminPanelProvider.php

key-decisions:
  - "Contact-rate numerator hardcodes CallResult::ANSWERED/CONFIRMED/CALLBACK_REQUESTED (D-13) since raw SQL
     cannot call CallResult::isSuccessfulContact() directly - must stay in sync with that enum method."
  - "Business-hours-only axis (7am-9pm, hours 7-21) per D-15 - Claude's discretion default, no real
     call_date distribution data was available locally to refine this range."
  - "Every caller renders as its own row with zero top-N truncation (D-14) - HeatmapChart.jsx's scroll
     container (built in 23-01) handles arbitrarily many rows."
  - "null rate for zero-attempt cells, distinct from a real 0%-effectiveness cell (D-16) - HeatmapChart.jsx
     renders these with visually distinct shades."

patterns-established: []

requirements-completed: [VIZ-09]

# Metrics
duration: ~20min
completed: 2026-08-21
---

# Phase 23 Plan 05: Caller x Hour Effectiveness Heatmap Summary

**Admin-only heatmap of call-center caller x business-hour contact-rate %, built on the shared HeatmapChart.jsx component from 23-01, registered on the Admin dashboard after 23-03's Sankey/StackedArea entries.**

## Performance

- **Duration:** ~20 min (including stale-worktree environment recovery)
- **Started:** 2026-08-21T11:20:44-05:00
- **Completed:** 2026-08-21T11:24:12-05:00
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Shipped `CallerHourHeatmapChart` (VIZ-09) — a caller x business-hour (7am-9pm) contact-rate % heatmap, joined through `voter_id` (VerificationCall has no direct `campaign_id`, same pattern as `CallContactabilityFunnelChart`), numerator using the same 3 `CallResult` values as `CallResult::isSuccessfulContact()`.
- Registered on the Admin dashboard's panel-global `->widgets([...])` array, appended after 23-03's `RejectionReasonsStackedAreaChart` entry and before `BirthdayWidget`, preserving every prior Phase 23 widget registration.
- Real Pest v4 Browser test proves the CSS-grid heatmap renders, the always-visible caller-name row label is present, and hovering a specific `[data-caller-id][data-hour]` cell shows a real positioned React tooltip (not a native `title=` attribute), with zero JS errors.

## Task Commits

Each task was committed atomically:

1. **Task 1: CallerHourHeatmapChart.php** - `6b4f347` (feat)
2. **Task 2: Register on Admin dashboard + Browser test** - `a2af352` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Filament/Widgets/CallerHourHeatmapChart.php` - Caller x hour contact-rate % aggregation, kind `heatmap`, D-13/D-14/D-15/D-16 all implemented per plan spec
- `app/Providers/Filament/AdminPanelProvider.php` - Added `CallerHourHeatmapChart` import + widget registration
- `tests/Browser/CallerHourHeatmapChartTest.php` - Real-browser proof of grid render + real positioned tooltip on hover

## Decisions Made

None beyond the plan's own literal D-13/D-14/D-15/D-16 decisions (all implemented exactly as specified) - see `key-decisions` in frontmatter above.

## Deviations from Plan

None - plan executed exactly as written. Both tasks' literal code blocks (widget class, `AdminPanelProvider` edit, Browser test) were applied verbatim and passed verification on the first attempt with zero fixes needed.

## Issues Encountered

- **Stale worktree (same recurring class documented throughout this milestone):** Worktree (`agent-a3d9a945e89533f81`) was 109 commits behind `main` at session start — missing all of Phase 23's planning corpus and completed plans (23-01 through 23-04), plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction` (real install, not symlinked, per the Phase 22 Plan 02 precedent that a symlinked `vendor/` breaks Pest's directory-scoped test binding), `node_modules/` symlinked from the main checkout (both `composer.lock` and `package-lock.json` were byte-identical, confirmed via `diff` before symlinking), `public/build/` initially copied from the main checkout then found stale (zero `heatmap` occurrences in the bundled JS) and rebuilt fresh via `npm run build` — same stale-bundle pattern documented in 23-03's decisions. `php artisan migrate:status` showed zero pending migrations (shared `sigma_betha_backup`-backed DB already current through 23-03's `previous_status`-nullable migration).
- **`gsd-tools` `findProjectRoot()` worktree-redirection bug (documented extensively throughout v1.3):** Not directly hit in this plan's execution since STATE.md/ROADMAP.md/REQUIREMENTS.md are hand-edited directly in this worktree below, per the same established workaround as every prior Phase 20-23 plan.
- **Pre-existing, unrelated test failure confirmed still present:** `tests/Browser/VoterHappyPathFunnelChartTest.php` (owned by plan 23-02) still fails deterministically on `assertSee('Pendiente de Revisión')` when run as part of this plan's required 5-file cross-test-pollution regression sweep. Already documented as a pre-existing, unrelated issue in `deferred-items.md` since 23-03; confirmed again here (not caused by this plan's `CallerHourHeatmapChart` addition — the other 4 files in the sweep, including this plan's own new test, all pass cleanly together). Left unfixed per scope-boundary rules; logged an additional confirmation entry to `.planning/phases/23-differentiator-visualizations/deferred-items.md`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

VIZ-09 is Done. Phase 23 (Differentiator Visualizations) now has all 5 of its plans complete (23-01 through 23-05), closing VIZ-06 through VIZ-10. Ready for phase-completion verification and the transition to Phase 24 (Día D Live Voting Visualization, DAYD-05). The one known open item is the pre-existing, unrelated `VoterHappyPathFunnelChartTest.php` flake/failure tracked in `deferred-items.md` — recommended as a fast-follow fix before or during Phase 24, not a blocker for this plan's own scope.

---
*Phase: 23-differentiator-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/CallerHourHeatmapChart.php
- FOUND: tests/Browser/CallerHourHeatmapChartTest.php
- FOUND: .planning/phases/23-differentiator-visualizations/23-05-SUMMARY.md
- FOUND: commit 6b4f347
- FOUND: commit a2af352
