---
phase: 14-articulador-admin-resource-hierarchy-wiring
plan: 02
subsystem: filament-admin
tags: [filament-select, coordinator-form, articulador, campaign-scoping]

# Dependency graph
requires:
  - phase: 12-hierarchy-metadata-schema-foundation
    provides: "area_coordinator role, users.area_coordinator_user_id FK, User::areaCoordinator() BelongsTo relation"
  - phase: 13-hierarchy-authorization-call-site-audit
    provides: "CoordinatorPolicy backend gate protecting coordinador records once exposed through a real UI surface"
provides:
  - "CoordinatorForm's Ubicación section now exposes an optional, campaign-scoped Select to assign/reassign a coordinador's articulador"
affects: [15-articulador-self-service-panel, 16-metadata-catalog-ui-assignment]

# Tech tracking
tech-stack:
  added: []
  patterns: ["relationship() Select role-filtered via modifyQueryUsing, relying on the target model's global scope for campaign isolation instead of a manual CampaignContext closure — same shape as LeaderForm's coordinator_user_id Select"]

key-files:
  created: []
  modified:
    - app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php
    - tests/Feature/Filament/CoordinatorResourceCampaignTest.php

key-decisions:
  - "Followed the plan's exact field shape (relationship() + role-filter closure, no manual CampaignContext closure) since User's global CampaignMembershipScope already restricts the relationship query to the active campaign — verified by test, not assumed"
  - "Fixed the plan's literal campaign-scoping test fixture setup to account for the pre-existing, already-documented CampaignContext::enforceCampaignId() behavior: attaching a user to a campaign_user pivot row without first setting that campaign as the active CampaignContext silently overwrites the explicit campaign_id to 'the sole active campaign system-wide' on the pivot's creating() hook (CampaignUser uses HasCampaignContext, same trait/behavior documented in STATE.md for Voter). Set CampaignContext::setCampaignId() to each campaign before attaching that campaign's articulador fixture, then switched back to the real active campaign before asserting — matches the exact precedent already recorded in STATE.md's Blockers/Concerns for this class of test."

requirements-completed: [ARTIC-01, ARTIC-03]

# Metrics
duration: 25min
completed: 2026-08-10
---

# Phase 14 Plan 02: CoordinatorForm Articulador Selector Summary

**Optional, campaign-scoped `Select` field on `CoordinatorForm` lets an admin assign/reassign a coordinador's articulador (`area_coordinator_user_id`), with zero impact on coordinadores left unassigned.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-08-10T17:40:00Z (after worktree fast-forward + provisioning)
- **Completed:** 2026-08-10T18:02:36Z
- **Tasks:** 2/2
- **Files modified:** 2

## Accomplishments

- `CoordinatorForm`'s "Ubicación" section gained a third field: `Select::make('area_coordinator_user_id')`, labeled "Articulador," role-filtered to `UserRole::AREA_COORDINATOR`, searchable, preloaded, with a helper text explaining the automatic campaign scoping. No `->required()` — stays fully optional per ARTIC-03.
- Confirmed via test (not assumed) that `User`'s global `CampaignMembershipScope` transparently restricts the `relationship()` Select's option query to the active campaign — no manual `CampaignContext`-based closure needed, unlike `municipality_id`'s special-cased closure (which exists only because `Municipality` has no global scope).
- 3 new Pest tests appended to `CoordinatorResourceCampaignTest.php`: selector assignment persists `area_coordinator_user_id`, a coordinador with no articulador still saves cleanly (ARTIC-03 regression proof), and the dropdown excludes articuladores from a different campaign.
- All 7 tests in the file pass (4 pre-existing + 3 new); `vendor/bin/pint --dirty` exits 0.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add area_coordinator_user_id Select to CoordinatorForm** - `b924664` (feat)
2. **Task 2: Pest tests for selector assignment, ARTIC-03 regression, and campaign scoping** - `01ba4cd` (test)

## Files Created/Modified

- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` - Added `use App\Enums\UserRole;` and a new optional `Select::make('area_coordinator_user_id')` in the Ubicación section, role-filtered to `area_coordinator` via `modifyQueryUsing`
- `tests/Feature/Filament/CoordinatorResourceCampaignTest.php` - Added `use Filament\Forms\Components\Select;` and 3 new tests covering assignment, no-articulador regression, and campaign-scoped option filtering

## Decisions Made

- No manual `CampaignContext` closure added to the new Select — `User::query()`'s global `CampaignMembershipScope` already scopes the relationship query, verified with a debug run showing the generated SQL includes a `whereHas('campaigns', ...)` clause bound to the active campaign id.
- Rewrote the plan's literal campaign-scoping test fixture setup: the plan's verbatim code attached both `area_coordinator` fixtures to their respective campaigns *before* calling `CampaignContext::setCampaignId()`, which silently collapsed both attach()es onto campaign A due to the pre-existing, already-documented `CampaignContext::enforceCampaignId()` pivot-overwrite behavior (see Rule 1 below). Fixed by setting the campaign context to each campaign immediately before that campaign's `attach()` call.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Campaign-scoping test's fixture setup silently collapsed both articuladores onto the same campaign**
- **Found during:** Task 2, running `php artisan test --filter=CoordinatorResourceCampaignTest`
- **Issue:** The plan's literal test code created `$areaCoordinatorB` and attached it to `$campaignB->id` *before* any `CampaignContext::setCampaignId()` call. `CampaignUser` (the `campaigns()` pivot model) uses the `HasCampaignContext` trait, whose `creating` hook calls `CampaignContext::enforceCampaignId($model)` — with no active campaign context set, this resolves to "the sole ACTIVE campaign system-wide" for a super_admin actor and silently overwrites the pivot's explicit `campaign_id` attribute. Result: both `$areaCoordinatorA` and `$areaCoordinatorB` ended up attached to `$this->campaign` (id 1) instead of their intended separate campaigns, so the assertion that `$areaCoordinatorB` is excluded from the dropdown failed (it wasn't excluded, because it was never actually attached to `$campaignB`). This exact class of pre-existing behavior is already documented in `.planning/STATE.md`'s Blockers/Concerns (Quick task 260806-elm decisions) for `Voter`; it applies identically to the `CampaignUser` pivot since both use `HasCampaignContext`.
- **Fix:** Added `CampaignContext::setCampaignId($this->campaign->id)` immediately before attaching `$areaCoordinatorA` to `$this->campaign`, and `CampaignContext::setCampaignId($campaignB->id)` immediately before attaching `$areaCoordinatorB` to `$campaignB`, then switched back to `CampaignContext::setCampaignId($this->campaign->id)` before invoking the Livewire form assertion — matches the exact workaround pattern already recorded in STATE.md for this class of test.
- **Files modified:** `tests/Feature/Filament/CoordinatorResourceCampaignTest.php`
- **Commit:** `01ba4cd`

## Issues Encountered

**Worktree staleness (recurring, documented pattern per STATE.md Blockers):** This worktree (`agent-a2d5b90b25811bdb2`) was 5 commits behind `main` at session start, missing Phase 14's own `14-01-PLAN.md`/`14-02-PLAN.md`/`14-CONTEXT.md`/`14-RESEARCH.md` plus `.env`, `vendor/`, `node_modules/`, and `public/build`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

The articulador-to-coordinador hierarchy is now assignable through the admin UI, on top of Phase 13's `CoordinatorPolicy` ownership gate. Phase 15 (Articulador Self-Service Panel) can rely on `area_coordinator_user_id` being reliably settable/nullable through this exact form; no blockers identified.

---
*Phase: 14-articulador-admin-resource-hierarchy-wiring*
*Completed: 2026-08-10*

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php
- FOUND: tests/Feature/Filament/CoordinatorResourceCampaignTest.php
- FOUND: commit b924664
- FOUND: commit 01ba4cd
