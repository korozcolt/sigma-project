---
phase: 13-hierarchy-authorization-call-site-audit
plan: 01
subsystem: auth
tags: [spatie-permission, eloquent, filament-widget, maatwebsite-excel, pest]

# Dependency graph
requires:
  - phase: 12-hierarchy-metadata-schema-foundation
    provides: "area_coordinator_user_id FK, User::areaCoordinator()/coordinators() relations, AREA_COORDINATOR enum case"
provides:
  - "User::teamCoordinatorUserIds() — single centralized 'my team' resolution helper for coordinador/articulador actors"
  - "TopLeadersTable, TopLeadersExport, LeadersExportController all resolve an articulador's full transitive team"
  - "ArticuladorTeamResolutionTest — regression coverage for AUTHZ-01 transitive-team resolution and AUTHZ-03 cross-campaign isolation"
affects: [14-articulador-admin-resource-hierarchy-wiring, 15-articulador-self-service-panel]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Centralized team-resolution helper on the model (User::teamCoordinatorUserIds()) instead of duplicating the same inline ->when()/hasRole() query-scoping closure across every call site"

key-files:
  created:
    - tests/Feature/ArticuladorTeamResolutionTest.php
  modified:
    - app/Models/User.php
    - app/Filament/Widgets/TopLeadersTable.php
    - app/Exports/TopLeadersExport.php
    - app/Http/Controllers/Coordinator/LeadersExportController.php

key-decisions:
  - "teamCoordinatorUserIds() returns [$this->id] for a coordinador (so whereIn(...) is byte-for-byte equivalent to the old where(...) for that role) and $this->coordinators()->pluck('id')->all() for an articulador"
  - "LeadersExportController's articulador coverage is tested by invoking the controller directly (not via the coordinator route group), since that route's role middleware intentionally excludes area_coordinator until Phase 14/15 wires the panel — this plan proves the query itself is correct ahead of that routing work"

patterns-established:
  - "Pattern 1: 'my team' scoping for coordinador/articulador actors goes through User::teamCoordinatorUserIds() + whereIn('coordinator_user_id', ...) — future call sites should extend this helper's match arms rather than re-deriving the resolution inline"

requirements-completed: [AUTHZ-01, AUTHZ-03]

# Metrics
duration: 17min
completed: 2026-08-10
---

# Phase 13 Plan 01: Hierarchy Authorization Call-Site Fix Summary

**Centralized `User::teamCoordinatorUserIds()` helper wired into TopLeadersTable, TopLeadersExport, and LeadersExportController so an articulador's full transitive team (every leader under every coordinador assigned to them) resolves correctly instead of returning empty results.**

## Performance

- **Duration:** 17 min (excluding worktree provisioning)
- **Started:** 2026-08-10T16:38:00Z (approx, after worktree fast-forward)
- **Completed:** 2026-08-10T16:55:00Z
- **Tasks:** 2
- **Files modified:** 4 (+1 created)

## Accomplishments
- Added `User::teamCoordinatorUserIds()` as the single centralized resolution point for "my team" scoping across coordinador and articulador actors
- Wired the helper into all 3 audited call sites (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`), replacing `->where('coordinator_user_id', $user->id)` gated on `hasRole(COORDINATOR)` only with `->whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())` gated on `hasAnyRole([COORDINATOR, AREA_COORDINATOR])`
- Added 5 new regression tests (`ArticuladorTeamResolutionTest`) proving AUTHZ-01 (transitive team resolution across all 3 surfaces, including the zero-coordinadores edge case) and AUTHZ-03 (an articulador's active-campaign context never leaks a managed coordinador's leaders from a different campaign)
- Confirmed zero regression to existing coordinador-only behavior via the full targeted suite (21 tests, 62 assertions, all passing)

## Task Commits

1. **Task 1: Add User::teamCoordinatorUserIds() helper and wire it into the 3 audited call sites** - `1ceb67d` (feat)
2. **Task 2: Regression tests proving AUTHZ-01 transitive-team resolution and AUTHZ-03 campaign isolation across all 3 surfaces** - `065e7f5` (test)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified
- `app/Models/User.php` - Added `use App\Enums\UserRole;` import and `teamCoordinatorUserIds(): array` method immediately after `coordinators()`
- `app/Filament/Widgets/TopLeadersTable.php` - Replaced coordinador-only `->when()` scoping with the articulador-aware `hasAnyRole()`/`whereIn()` pattern
- `app/Exports/TopLeadersExport.php` - Same replacement in the Excel export query
- `app/Http/Controllers/Coordinator/LeadersExportController.php` - Same replacement in the controller query
- `tests/Feature/ArticuladorTeamResolutionTest.php` - New Pest file, 5 tests covering all 3 surfaces plus the no-coordinadores and no-regression cases

## Decisions Made
- No new decisions beyond what 13-CONTEXT.md and the plan already specified — implementation followed the plan's exact code blocks and test structure since the codebase's actual `User` relations, `UserRole` enum values, and factory conventions matched the plan's assumptions exactly (verified via `read_first` before editing).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**Worktree staleness (pre-existing, documented recurring issue, not part of this plan's scope):** This worktree (`agent-a6beaebb4e89d11f9`) was 15 commits behind `main` at session start — missing Phase 13's own PLAN.md files, Phase 12's completed schema/hierarchy work, `vendor/`, and `.env`. Confirmed fast-forward ancestry (`git merge-base --is-ancestor` returned true), ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`. This is the same recurring class of issue already logged multiple times in STATE.md's Blockers/Concerns section; no new blocker entry added since it's already tracked there.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `User::teamCoordinatorUserIds()` is now the established pattern for any future call site needing "my team" scoping across the coordinador/articulador hierarchy — Phase 14/15 (Articulador Admin Resource, Self-Service Panel) can reuse it directly.
- Plan 13-02 (the phase's other plan — `CoordinatorPolicy` for direct-record ownership denial, AUTHZ-02) is independent of this plan's changes and can proceed without blockers.
- No blockers for Phase 14/15.

---
*Phase: 13-hierarchy-authorization-call-site-audit*
*Completed: 2026-08-10*

## Self-Check: PASSED

All created/modified files found on disk. Both task commits (`1ceb67d`, `065e7f5`) confirmed present in `git log`.
