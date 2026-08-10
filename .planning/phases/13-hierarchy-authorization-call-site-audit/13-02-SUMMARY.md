---
phase: 13-hierarchy-authorization-call-site-audit
plan: 02
subsystem: auth
tags: [spatie-permission, filament-gate, policy, articulador, coordinador]

# Dependency graph
requires:
  - phase: 12-hierarchy-metadata-schema-foundation
    provides: "area_coordinator role, users.area_coordinator_user_id FK, User::areaCoordinator()/coordinators() relations"
provides:
  - "app/Policies/CoordinatorPolicy.php — first ownership-aware Policy in the codebase, view()/update() gate on User::class"
  - "CoordinatorPolicy registered globally as the User::class policy in AuthServiceProvider"
  - "Regression proof that Gate::before's cross-campaign check already protects the new articulador role at the direct-record level (AUTHZ-03)"
affects: [14-articulador-admin-resource-hierarchy-wiring, 15-articulador-self-service-panel]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Ownership-aware Policy returning Illuminate\\Auth\\Access\\Response with a named denial reason (mirrors Phase 05.1 PERM-02 precedent)"]

key-files:
  created:
    - app/Policies/CoordinatorPolicy.php
    - tests/Feature/Policies/CoordinatorPolicyTest.php
  modified:
    - app/Providers/AuthServiceProvider.php

key-decisions:
  - "CoordinatorPolicy implements ONLY view()/update() — every other Filament ability (viewAny, create, delete, etc.) on User falls through untouched to Gate::before + default-allow, per 13-CONTEXT.md D-03/D-04 (purely additive, no regression to UserResource/LeaderResource/CoordinatorResource)"
  - "AUTHZ-03 required no new code — Gate::before's existing User-branch cross-campaign check already covers the new area_coordinator role; this plan only adds a regression test proving it"

requirements-completed: [AUTHZ-02, AUTHZ-03]

# Metrics
duration: 15min
completed: 2026-08-10
---

# Phase 13 Plan 02: CoordinatorPolicy (Articulador Ownership Gate) Summary

**New `CoordinatorPolicy` denies an articulador direct view/edit access to a coordinador outside their team with a named 403 reason, registered globally for `User::class` with zero regression to any other role/target combination.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-08-10T11:36:00-05:00 (after worktree fast-forward + provisioning)
- **Completed:** 2026-08-10T11:51:26-05:00
- **Tasks:** 2/2
- **Files modified:** 3 (1 created policy, 1 created test, 1 modified provider)

## Accomplishments
- `CoordinatorPolicy` created: `view()`/`update()` deny an `area_coordinator` actor from a coordinador whose `area_coordinator_user_id` doesn't match their own id, with the exact message `'Este coordinador no pertenece a tu equipo de articulador.'`
- Registered as `User::class => CoordinatorPolicy::class` in `AuthServiceProvider` — the first ownership-aware Policy in this codebase (VoterPolicy/InvitationPolicy are role-only)
- 10 Pest tests (34+24 assertions) proving: denial with reason (AUTHZ-02), allow-own-coordinador, no regression across every other `UserRole` case, no restriction on non-coordinador targets (D-04), and cross-campaign denial still applies via the pre-existing `Gate::before` check (AUTHZ-03)
- Zero regression confirmed against `CoordinatorResourceCampaignTest`, `LeaderResourceCampaignTest`, and `OperationalDenialMessagesTest`

## Task Commits

Each task was committed atomically:

1. **Task 1: Create CoordinatorPolicy (view/update ownership check) and register it** - `2444404` (feat, TDD: RED confirmed with 9 failing tests before implementation, then GREEN)
2. **Task 2: AUTHZ-03 cross-campaign regression test + full-suite sanity check** - `d8d50d6` (test)

## Files Created/Modified
- `app/Policies/CoordinatorPolicy.php` - New Policy, `view()`/`update()` ownership check via `area_coordinator_user_id === $user->id`
- `app/Providers/AuthServiceProvider.php` - Added `use App\Policies\CoordinatorPolicy;` and `User::class => CoordinatorPolicy::class` to `$policies`
- `tests/Feature/Policies/CoordinatorPolicyTest.php` - 6 test cases (10 test runs including the `UserRole::cases()` dataset) covering AUTHZ-02, AUTHZ-03, allow-own, and no-regression scenarios

## Decisions Made
- Followed the plan's exact code shape (no deviation) — `CoordinatorPolicy` mirrors `VoterPolicy`'s structure but is ownership-aware, matching 13-CONTEXT.md D-03/D-04.
- AUTHZ-03's test needed no new production code: `CampaignContext::currentCampaignId($user)` resolves from the user's own attached campaigns when no session override is set, so `Gate::before`'s existing `User`-branch check already denies a cross-campaign coordinador even when ownership is technically correct — confirmed by test, not implemented fresh.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**Worktree staleness (recurring, documented pattern per STATE.md Blockers):** This worktree (`agent-a32d4cb12325f22a2`) was 15 commits behind `main` at session start, missing Phase 12's completed schema work and Phase 13's own PLAN.md files entirely, plus `.env`, `vendor/`, `node_modules/`, and `public/build`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, and ran `php artisan migrate` to apply the two pending Phase 12 migrations (`metadata_keys`, `user_metadata_values`) against the shared `sigma_betha_backup` local DB.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

`CoordinatorPolicy` is in place and registered ahead of Phase 14 (Articulador Admin Resource & Hierarchy Wiring), which will be the first phase to actually expose coordinador records to an `area_coordinator` actor through a real UI surface — this plan's backend-only gate is ready to protect that surface the moment it's wired. No blockers identified for Phase 14/15.

---
*Phase: 13-hierarchy-authorization-call-site-audit*
*Completed: 2026-08-10*

## Self-Check: PASSED

- FOUND: app/Policies/CoordinatorPolicy.php
- FOUND: tests/Feature/Policies/CoordinatorPolicyTest.php
- FOUND: `User::class => CoordinatorPolicy::class` in app/Providers/AuthServiceProvider.php
- FOUND: commit 2444404
- FOUND: commit d8d50d6
