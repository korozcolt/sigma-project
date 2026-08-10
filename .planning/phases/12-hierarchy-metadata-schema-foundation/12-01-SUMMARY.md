---
phase: 12-hierarchy-metadata-schema-foundation
plan: 01
subsystem: database
tags: [eloquent, spatie-permission, migrations, pest]

# Dependency graph
requires: []
provides:
  - "area_coordinator Spatie role (UserRole::AREA_COORDINATOR enum case), auto-seeded by RoleSeeder"
  - "area_coordinator_user_id nullable, indexed, self-referencing FK on users, independent of coordinator_user_id"
  - "User::areaCoordinator() BelongsTo and User::coordinators() HasMany relations"
affects: [13-hierarchy-authorization-call-site-audit, 14-articulador-admin-resource-hierarchy-wiring, 15-articulador-self-service-panel]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "New hierarchy levels are added as a dedicated self-referencing FK + relation pair, never by reusing/overloading an existing FK column (mirrors coordinator_user_id -> area_coordinator_user_id)"

key-files:
  created:
    - database/migrations/2026_08_10_120000_add_area_coordinator_to_users_table.php
    - tests/Feature/AreaCoordinatorHierarchyTest.php
  modified:
    - app/Enums/UserRole.php
    - app/Models/User.php
    - tests/Feature/RolePermissionTest.php
    - tests/Feature/Policies/VoterPolicyTest.php

key-decisions:
  - "AREA_COORDINATOR excluded from VoterPolicyTest's every-other-role regression dataset — the new role is not yet wired into any authorization policy; that wiring is explicitly Phase 13's scope (hierarchy authorization & call-site audit), not this schema-only plan's"
  - "RolePermissionTest's hardcoded role count updated from 6 to 7 to reflect the new UserRole::AREA_COORDINATOR case"

patterns-established:
  - "Pattern: a new hierarchy level = new nullable/indexed self-referencing FK + BelongsTo/HasMany pair, structurally isolated from every existing hierarchy column"

requirements-completed: [ARTIC-04, ARTIC-05]

# Metrics
duration: 30min
completed: 2026-08-10
---

# Phase 12 Plan 01: Hierarchy Schema Foundation Summary

**New `area_coordinator` Spatie role and a dedicated `area_coordinator_user_id` self-referencing FK on `users`, structurally independent of `coordinator_user_id`, with no backend-enforced cap on coordinadores per area coordinator.**

## Performance

- **Duration:** 30 min
- **Started:** 2026-08-10T15:02:00Z
- **Completed:** 2026-08-10T15:32:48Z
- **Tasks:** 2/2 completed
- **Files modified:** 6 (1 migration + 5 code/test files)

## Accomplishments
- `area_coordinator_user_id` nullable, indexed, self-referencing FK on `users`, migrated and applied
- `UserRole::AREA_COORDINATOR` enum case with all 4 `HasColor`/`HasIcon`/`HasLabel`/`HasDescription` match arms, auto-seeded via `RoleSeeder`'s existing `foreach(UserRole::cases())` loop (zero seeder changes needed)
- `User::areaCoordinator()` (BelongsTo) and `User::coordinators()` (HasMany) relations, exact structural mirror of `coordinator()`/`leaders()`
- `AreaCoordinatorHierarchyTest` with 5 passing tests proving role seeding, FK independence from `coordinator_user_id` (ARTIC-04), no regression on the existing coordinator/leader relation, and no backend-enforced cap via a 25-coordinador assignment (ARTIC-05)
- Full existing Pest suite (1417 tests) passes with zero new regressions after accounting for the pre-existing `CampaignContext` test-pollution flake class

## Task Commits

Each task was committed atomically:

1. **Task 1: Add area_coordinator_user_id migration, UserRole::AREA_COORDINATOR enum case, and User model relations** - `00256e3` (feat)
2. **Task 2: Hierarchy invariant tests + full regression run** - `ce2f5ed` (test)

**Plan metadata:** (this commit) - `docs(12-01): complete hierarchy-schema-foundation plan`

## Files Created/Modified
- `database/migrations/2026_08_10_120000_add_area_coordinator_to_users_table.php` - New nullable, indexed, self-referencing `area_coordinator_user_id` FK on `users`, `nullOnDelete`
- `app/Enums/UserRole.php` - New `AREA_COORDINATOR = 'area_coordinator'` case with all 4 interface method arms (label "Articulador", color `primary`, icon `heroicon-m-user-group`, Spanish description)
- `app/Models/User.php` - `area_coordinator_user_id` added to `$fillable`; new `areaCoordinator(): BelongsTo` and `coordinators(): HasMany` relations added after `coordinator()`/`leaders()`, which remain untouched
- `tests/Feature/AreaCoordinatorHierarchyTest.php` - 5 new tests: role seeding, FK independence (ARTIC-04), coordinator/leader no-regression, 25-coordinador no-cap assignment (ARTIC-05)
- `tests/Feature/RolePermissionTest.php` - Updated hardcoded role count assertion from 6 to 7
- `tests/Feature/Policies/VoterPolicyTest.php` - Excluded `AREA_COORDINATOR` from the "every other role can mutate Voter" dataset, with an inline comment explaining the Phase 13 deferral

## Decisions Made
- `AREA_COORDINATOR` is deliberately not wired into `VoterPolicy` (or any other policy) by this plan — Phase 13 ("Hierarchy Authorization & Call-Site Audit") owns that work per the roadmap and per this plan's own objective ("No UI, no authorization/policy layer... that is Phase 13's scope"). `VoterPolicyTest`'s regression dataset was narrowed accordingly rather than granting the new role premature/unreviewed authorization.
- `RolePermissionTest`'s role-count assertion is a literal count of `Role::all()`, so it necessarily needed updating (6 -> 7) the moment a new `UserRole` case was added — a mechanical, in-scope fix, not a design decision.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed RolePermissionTest's hardcoded role count**
- **Found during:** Task 2 full-suite regression run
- **Issue:** `RolePermissionTest > it creates all roles from UserRole enum` asserted `expect($roles)->toHaveCount(6)`, a literal count that broke the instant `UserRole::AREA_COORDINATOR` was added (now 7 roles)
- **Fix:** Updated the expected count to 7
- **Files modified:** `tests/Feature/RolePermissionTest.php`
- **Verification:** `php artisan test --filter=RolePermissionTest` passes (14/14)
- **Committed in:** `ce2f5ed` (Task 2 commit)

**2. [Rule 3 - Blocking] Excluded AREA_COORDINATOR from VoterPolicyTest's every-other-role dataset**
- **Found during:** Task 2 full-suite regression run
- **Issue:** `VoterPolicyTest`'s `->with(collect(UserRole::cases())->reject(...))` dataset iterates every role except `REPORTS_VIEWER` and asserts full Voter-mutation access. `VoterPolicy` uses explicit `hasAnyRole([...])` allow-lists that don't (and per this plan's explicit scope, should not yet) include `AREA_COORDINATOR`, so the dataset run for the new role failed with "Failed asserting that false is true."
- **Fix:** Excluded `UserRole::AREA_COORDINATOR` from the dataset alongside `REPORTS_VIEWER`, with an inline comment pointing to Phase 13 as the owner of wiring the new role into authorization policies
- **Files modified:** `tests/Feature/Policies/VoterPolicyTest.php`
- **Verification:** `php artisan test --filter=VoterPolicyTest` passes (8/8)
- **Committed in:** `ce2f5ed` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 bug fix, 1 blocking/scope-boundary fix)
**Impact on plan:** Both fixes were mechanical consequences of adding a new enum case to an existing, well-tested role system. Neither touches authorization logic itself (that remains correctly deferred to Phase 13) — no scope creep.

## Issues Encountered

- **Worktree was stale at session start** (same recurring class of issue documented repeatedly in STATE.md's Blockers/Concerns): this worktree's HEAD (`760a354`) was missing `31a5c9d`/`1c35956` (this plan's own PLAN.md commits), plus `vendor/`, `.env`, `node_modules/`, and `public/build/` entirely. Resolved with the established workaround: confirmed `main` (`1c35956`) is a fast-forward descendant via the shared object store, ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, then `npm install && npm run build` (the Vite-manifest-missing failure mode caused 59 spurious test failures on the first full-suite run, resolved entirely by the build step).
- **19 full-suite failures remain after both real fixes**, all confirmed (by running the affected files in isolation, twice) to be the pre-existing, already-documented `CampaignContext` static-override test-pollution class (`DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`, `RejectionsCountersOverviewTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `UserResourceTest`, `VoterResourceTest`, `IsElectionDayMiddlewareTest`) — every one of these passed cleanly when run standalone. Not caused by this plan; not fixed, per the plan's scope boundary and the project's long-standing recommendation to fix this at the source in a shared `afterEach`/`TestCase::tearDown()`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 13 (Hierarchy Authorization & Call-Site Audit) can now build on `UserRole::AREA_COORDINATOR`, `area_coordinator_user_id`, and `User::areaCoordinator()`/`coordinators()` to wire real authorization (policies, panel access, call-site scoping) for the new role — none of that exists yet by design.
- Phase 14 (Articulador Admin Resource & Hierarchy Wiring) has a stable, tested schema/model foundation to build the actual CRUD UI against.
- No blockers introduced by this plan. The pre-existing `CampaignContext` test-pollution flake class remains open (see Issues Encountered) and continues to be a standing, cross-phase concern already tracked in `.planning/STATE.md`.

---
*Phase: 12-hierarchy-metadata-schema-foundation*
*Completed: 2026-08-10*

## Self-Check: PASSED

- FOUND: database/migrations/2026_08_10_120000_add_area_coordinator_to_users_table.php
- FOUND: tests/Feature/AreaCoordinatorHierarchyTest.php
- FOUND: .planning/phases/12-hierarchy-metadata-schema-foundation/12-01-SUMMARY.md
- FOUND commit: 00256e3
- FOUND commit: ce2f5ed
