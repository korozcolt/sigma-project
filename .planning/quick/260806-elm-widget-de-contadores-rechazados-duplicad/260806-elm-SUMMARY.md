---
phase: quick-260806-elm
plan: 01
subsystem: reports-panel
tags: [filament, widget, reports, rejections, duplicates, territorial-scope]
requires: []
provides:
  - RejectionsCountersOverview widget (3 campaign-scoped counters: Rechazados / Duplicados / Fuera de Jurisdicción)
affects:
  - app/Providers/Filament/ReportsPanelProvider.php
tech-stack:
  added: []
  patterns:
    - "StatsOverviewWidget with campaign-scoped getStats(), zero-safe no-campaign branch (CampaignStatsOverview/JurisdictionSummaryOverview precedent)"
key-files:
  created:
    - app/Filament/Widgets/RejectionsCountersOverview.php
    - tests/Feature/Filament/RejectionsCountersOverviewTest.php
  modified:
    - app/Providers/Filament/ReportsPanelProvider.php
decisions:
  - "Rechazados counter deliberately excludes DUPLICATE and REJECTED_OUT_OF_SCOPE — the three counters are non-overlapping by design, per plan's confirmed user decision."
metrics:
  duration: 20min
  completed: 2026-08-06
---

# Quick Task 260806-elm: Widget de Contadores Rechazados/Duplicados/Fuera de Jurisdicción Summary

Added a new `RejectionsCountersOverview` Filament StatsOverviewWidget to the reports panel showing 3 independent, non-overlapping campaign-scoped counters (Rechazados, Duplicados, Fuera de Jurisdicción), backed by full Pest coverage.

## What Was Built

`app/Filament/Widgets/RejectionsCountersOverview.php` — a `StatsOverviewWidget` (sort=6, same tier as `RejectionsReportTable`) with 3 `Stat` tiles:

- **Rechazados**: `Voter` count where `status IN (REJECTED_CENSUS, CENSUS_NOT_FOUND, CORRECTION_REQUIRED)` OR has a `verificationCalls` relation with `call_result IN (REJECTED, INVALID_NUMBER, NOT_INTERESTED)` — deliberately excludes `DUPLICATE` and `REJECTED_OUT_OF_SCOPE` so the 3 counters never overlap.
- **Duplicados**: `Voter` count where `status = DUPLICATE`.
- **Fuera de Jurisdicción**: `Voter` count where `status = REJECTED_OUT_OF_SCOPE`.

All 3 counts are scoped to `CampaignContext::currentCampaign()`; with no active campaign, all 3 tiles render `0` (int, matching `CampaignStatsOverview`/`JurisdictionSummaryOverview`'s no-campaign convention) with no exception. Each tile links via `->url(VoterResource::getUrl('index', ['tableFilters' => ...]))` to the filtered voter list, mirroring `CampaignStatsOverview`'s drill-through pattern.

Registered in `app/Providers/Filament/ReportsPanelProvider.php` immediately before `RejectionsReportTable::class` (same ordering precedent as `JurisdictionSummaryOverview` before `JurisdictionReportTable`).

## Tests

`tests/Feature/Filament/RejectionsCountersOverviewTest.php` (group `dashboard-widgets`), 3 tests:

1. Correct non-overlapping counts for a mix of 7 voters covering all 3 counters plus 1 `CONFIRMED` noise voter.
2. Zero-safe rendering with no active campaign session.
3. Cross-campaign isolation — a second campaign's qualifying voters never leak into the active campaign's counts.

All 3 tests pass (10 assertions). `RejectionsReportTable.php` and `JurisdictionSummaryOverview.php` were read-only references, left unmodified.

## Deviations from Plan

### Auto-fixed Issues

None — plan executed as written for the widget/provider code itself.

**Test-writing note (not a deviation from the plan, but worth recording):** the cross-campaign isolation test initially failed twice before landing on the correct setup, due to a pre-existing project mechanism, not a bug in the new widget:

- `CampaignContext::enforceCampaignId()` (fired on every `Voter::creating()` via `HasCampaignContext`) force-overwrites `campaign_id` from the active session/context for authenticated users — including `super_admin`. When no session campaign is set, a `super_admin`'s `currentCampaignId()` auto-resolves to "the sole active campaign system-wide" rather than staying `null`. This meant creating the "other campaign"'s voters required setting the session to that other campaign's id first (not leaving it unset), then switching the session to the real active campaign before asserting — otherwise the explicit `campaign_id` on the factory calls was silently overwritten to the wrong campaign. This is pre-existing, well-known, intentional behavior (not a bug), documented here only because it cost 2 failed test iterations to discover.

## Deviations from Environment (worktree provisioning)

This worktree (`worktree-agent-a956c439b34fb5651`) was stale at session start and one commit behind `main` — missing this quick task's own `260806-elm-PLAN.md` (created directly on `main` by the planning step), plus `vendor/`, `.env`, and `node_modules`/`public/build` were already present from a prior session. Resolved with the established workaround documented repeatedly in STATE.md: confirmed `main` was a fast-forward descendant of this worktree's `HEAD` (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` from the main checkout, and ran `composer install`. No `gsd-tools` state-mutation commands were used for this quick task's STATE.md update (per the same documented `findProjectRoot()` worktree-redirection bug) — STATE.md was hand-edited directly in this worktree.

## Verification

- `php artisan test tests/Feature/Filament/RejectionsCountersOverviewTest.php` — 3 passed, 10 assertions.
- `php artisan test --group=dashboard-widgets` — full group run, 75 passed, 204 assertions, no regressions in sibling widgets/drill-through/page-scoped-registration tests.
- `vendor/bin/pint --dirty --test` — clean, no style issues.
- Manual browser verification of the new "Contadores de Rechazos" tile row on `/reports` is still pending, per standing project preference (Pest/Livewire tests alone are not treated as sufficient sign-off for UI-facing changes in this project).

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/RejectionsCountersOverview.php
- FOUND: tests/Feature/Filament/RejectionsCountersOverviewTest.php
- FOUND: app/Providers/Filament/ReportsPanelProvider.php (modified, RejectionsCountersOverview::class present)
- Commits verified present: aad01af, bf5970e
