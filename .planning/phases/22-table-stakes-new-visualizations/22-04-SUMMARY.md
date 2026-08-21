---
phase: 22-table-stakes-new-visualizations
plan: 04
subsystem: ui
tags: [filament, chartwidget, recharts, funnel, call-center, messaging]

# Dependency graph
requires:
  - phase: 22-table-stakes-new-visualizations (plan 01)
    provides: "React/Recharts chart-kind library — toOrderedRows() adapter, FunnelChart.jsx (kind 'funnel'), ChartRouter.jsx registration, ChartCard.jsx EMPTY_STATE_COPY keys (no_campaign/no_calls/no_messages)"
provides:
  - "CallContactabilityFunnelChart widget (VIZ-03) — funnel of distinct-voter counts by call-attempt stage (Intento 1/2/3+/Contactado), registered on ListVerificationCalls"
  - "MessageDeliveryFunnelChart widget (VIZ-04) — funnel of Message sent/delivered/read/clicked timestamp counts (Enviado/Entregado/Leído/Clic), registered on ListMessageBatches"
  - "Both widgets registered in AppServiceProvider::PAGE_SCOPED_WIDGETS, surviving wire:poll without ComponentNotFoundException"
affects: [23-differentiator-visualizations, 24-dia-d-live-voting-visualization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ChartWidget getData() empty-state pattern: no_campaign -> no_calls/no_messages -> real payload, each returning the shared emptyReason shape"
    - "Fresh-Builder closure ($baseQuery = fn (): Builder => ...) per stage count to avoid Eloquent builder mutation bleed across sequential counts on the same base query"

key-files:
  created:
    - app/Filament/Widgets/CallContactabilityFunnelChart.php
    - app/Filament/Widgets/MessageDeliveryFunnelChart.php
    - tests/Browser/CallContactabilityFunnelChartTest.php
    - tests/Browser/MessageDeliveryFunnelChartTest.php
  modified:
    - app/Filament/Resources/VerificationCalls/Pages/ListVerificationCalls.php
    - app/Filament/Resources/Messages/MessageBatchResource/Pages/ListMessageBatches.php
    - app/Providers/AppServiceProvider.php
    - tests/Feature/PageScopedWidgetRegistrationTest.php

key-decisions:
  - "Extended PageScopedWidgetRegistrationTest's dataset with both new widgets (not in plan's files_modified) — matches established phase precedent (Phase 21 Plans 04/06) of keeping the exact regression guard for the ComponentNotFoundException bug class current with every new page-scoped widget"
  - "Worktree was 68 commits behind main (stale at pre-Phase-20 commit 9ba4267) — resolved via the established fast-forward merge + .env copy + composer/npm install + npm run build workaround"

patterns-established: []

requirements-completed: [VIZ-03, VIZ-04]

# Metrics
duration: ~25min
completed: 2026-08-21
---

# Phase 22 Plan 04: New Funnel Widgets (Call Contactability + Message Delivery) Summary

**Two new Filament ChartWidgets (funnel kind) exposing call-attempt contactability and message read/click data, both previously invisible, registered page-scoped with full wire:poll safety.**

## Performance

- **Duration:** ~25 min (including stale-worktree environment recovery: git merge, composer/npm install, asset build)
- **Started:** 2026-08-21T08:49:00-05:00 (approx)
- **Completed:** 2026-08-21T08:57:03-05:00
- **Tasks:** 2
- **Files modified:** 8 (2 created widgets, 2 created Browser tests, 4 modified)

## Accomplishments
- `CallContactabilityFunnelChart` (VIZ-03): funnel of DISTINCT-voter counts per call-attempt stage (Intento 1 -> Intento 2 -> Intento 3+ -> Contactado), joined through `voter_id` since `VerificationCall` has no direct `campaign_id`; registered as the 3rd header widget on the Call Center's `ListVerificationCalls`.
- `MessageDeliveryFunnelChart` (VIZ-04): funnel of `Message` row counts per `sent_at`/`delivered_at`/`read_at`/`clicked_at` timestamp presence (Enviado -> Entregado -> Leído -> Clic) — the first ever visualization of message read/click data, since `MessageBatch` only pre-aggregates sent/delivered/failed counts. Registered as the first (and only) header widget on `ListMessageBatches`, which previously had no `getHeaderWidgets()` at all.
- Both widgets registered in `AppServiceProvider::PAGE_SCOPED_WIDGETS`, preventing the well-documented `ComponentNotFoundException` on the first `wire:poll` tick (the same bug class hit repeatedly across Phases 20-21 for page-scoped-but-unregistered widgets).
- Two real Pest 4 Browser tests (`CallContactabilityFunnelChartTest`, `MessageDeliveryFunnelChartTest`) confirm both funnels render `[data-chart-kind="funnel"]` with real stage-label text and zero JS console errors against a genuine Chromium session.

## Task Commits

Each task was committed atomically:

1. **Task 1: Build CallContactabilityFunnelChart (VIZ-03) and register on Call Center list page** - `b123204` (feat)
2. **Task 2: Build MessageDeliveryFunnelChart (VIZ-04), register on Messages list page, register both widgets in PAGE_SCOPED_WIDGETS + write both Browser tests** - `a37b104` (feat)

**Plan metadata:** (this commit, added after SUMMARY creation)

## Files Created/Modified
- `app/Filament/Widgets/CallContactabilityFunnelChart.php` - New funnel ChartWidget, DISTINCT-voter counts per attempt stage
- `app/Filament/Widgets/MessageDeliveryFunnelChart.php` - New funnel ChartWidget, Message timestamp-column counts
- `app/Filament/Resources/VerificationCalls/Pages/ListVerificationCalls.php` - Added `CallContactabilityFunnelChart::class` as 3rd header widget
- `app/Filament/Resources/Messages/MessageBatchResource/Pages/ListMessageBatches.php` - Added `getHeaderWidgets()` (previously absent) returning `MessageDeliveryFunnelChart::class`
- `app/Providers/AppServiceProvider.php` - Added both new widget classes to `PAGE_SCOPED_WIDGETS`
- `tests/Feature/PageScopedWidgetRegistrationTest.php` - Extended regression dataset with both new widgets
- `tests/Browser/CallContactabilityFunnelChartTest.php` - New Browser test, real Chromium session
- `tests/Browser/MessageDeliveryFunnelChartTest.php` - New Browser test, real Chromium session

## Decisions Made
- Extended `PageScopedWidgetRegistrationTest`'s dataset to cover both new widgets even though the plan's `files_modified` list didn't include this file — matches the established Phase 21 precedent of keeping this exact regression guard current with every new page-scoped widget, since the bug class it guards against (`ComponentNotFoundException` on `wire:poll`) is precisely what this plan's `PAGE_SCOPED_WIDGETS` registration exists to prevent.
- No production-code deviation from the plan's literal code blocks — both widgets, both page registrations, and the `PAGE_SCOPED_WIDGETS` entries were built exactly as specified in the plan's `<action>` blocks.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Extended PageScopedWidgetRegistrationTest's dataset with both new widgets**
- **Found during:** Task 2
- **Issue:** The plan registers both new widgets in `PAGE_SCOPED_WIDGETS` to prevent `ComponentNotFoundException` on `wire:poll`, but the pre-existing regression test guarding exactly this bug class (`PageScopedWidgetRegistrationTest`) wasn't in the plan's `files_modified` and would have silently not covered the two new widgets.
- **Fix:** Added `CallContactabilityFunnelChart::class` and `MessageDeliveryFunnelChart::class` to the test's dataset.
- **Files modified:** tests/Feature/PageScopedWidgetRegistrationTest.php
- **Verification:** `php artisan test tests/Feature/PageScopedWidgetRegistrationTest.php` — 11/11 passed (9 original + 2 new)
- **Committed in:** a37b104 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 missing critical / regression coverage)
**Impact on plan:** Strengthens existing regression protection for the exact bug class this plan's widgets are newly exposed to. No scope creep — same file, same test mechanism, matching Phase 21 precedent.

## Issues Encountered
- Worktree (`agent-a60ac77987e7da33b`) was 68 commits behind `main` at session start — checked out at pre-Phase-20 commit `9ba4267`, missing all of Phase 20/21/22 planning corpus and code (including this plan's own `22-04-PLAN.md`), plus `.env`, `vendor/`, `node_modules/`, `public/build/` — the same recurring class documented extensively across prior phase SUMMARY.md files in this milestone. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install` (reverted the recurring spurious `package-lock.json` `name`-field regression via `git checkout -- package-lock.json`), `npm run build`. `php artisan migrate:status` confirmed the shared local DB was already fully migrated (no `artisan migrate` needed).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- VIZ-03 and VIZ-04 both observably true: an admin visiting `/admin/verification-calls` sees the call-contactability funnel, and `/admin/messages/message-batches` sees the message-delivery funnel, both rendering via the shared React/Recharts island pipeline with confirmed poll-safety.
- No blockers for the remaining Phase 22 plan (22-05) or Phase 23/24 — the `PAGE_SCOPED_WIDGETS` registration pattern and the empty-state (`no_campaign`/`no_calls`/`no_messages`) convention are both directly reusable for future funnel/chart widgets.

---
*Phase: 22-table-stakes-new-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

All 8 claimed files confirmed present on disk; both task commits (`b123204`, `a37b104`) confirmed present in git history.
