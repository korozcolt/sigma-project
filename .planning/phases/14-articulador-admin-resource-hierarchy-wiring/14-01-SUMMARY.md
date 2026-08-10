---
phase: 14-articulador-admin-resource-hierarchy-wiring
plan: 01
subsystem: admin-panel
tags: [filament, spatie-permission, livewire, pest, area_coordinator]

# Dependency graph
requires:
  - phase: 12-hierarchy-metadata-schema-foundation
    provides: "area_coordinator_user_id self-referencing FK, User::coordinators()/areaCoordinator() relations, UserRole::AREA_COORDINATOR enum"
  - phase: 13-hierarchy-authorization-call-site-audit
    provides: "CoordinatorPolicy (view/update, ownership-scoped, non-interfering with admin actions)"
provides:
  - "AreaCoordinatorResource Filament admin resource (create/edit/list articuladores)"
  - "coordinators_count column on the Articuladores list table"
affects: [15-articulador-self-service-panel]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Mechanical mirror of CoordinatorResource for a second role-scoped User resource (Resource/Pages/Schemas/Tables) — same shape reusable for future role-scoped Filament resources"

key-files:
  created:
    - app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php
    - app/Filament/Resources/AreaCoordinators/Pages/CreateAreaCoordinator.php
    - app/Filament/Resources/AreaCoordinators/Pages/EditAreaCoordinator.php
    - app/Filament/Resources/AreaCoordinators/Pages/ListAreaCoordinators.php
    - app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php
    - app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php
    - tests/Feature/Filament/AreaCoordinatorResourceCampaignTest.php
  modified: []

key-decisions:
  - "coordinators() HasMany carries User's CampaignMembershipScope global scope — factory-created coordinators in tests must also be attached to the active campaign or counts('coordinators') silently reports null/0"
  - "assertTableColumnStateSet must be passed the record's key, not an in-memory model instance, so Filament re-resolves it through the table's own query where counts('coordinators') is applied"
  - "UserFactory nulls phone/document_number by default (~20%/10%) — any test that calls EditRecord's save() on a factory-created User must set both explicitly (matches existing CoordinatorResourceCampaignTest precedent)"

patterns-established:
  - "Role-scoped Filament User resource: Resource::getEloquentQuery() scoped via ->role(...), Create/Edit pages assign the Spatie role + attach/self-heal active-campaign pivot in afterCreate()/afterSave()"

requirements-completed: [ARTIC-01]

duration: 45min
completed: 2026-08-10
---

# Phase 14 Plan 01: AreaCoordinatorResource Summary

**New Filament admin resource (`AreaCoordinatorResource`) lets super_admin/admin_campaign create and manage `area_coordinator` (Articulador) users, mirroring `CoordinatorResource` minus the "también será líder" toggle, plus a coordinadores-count column.**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-08-10T17:35:00Z
- **Completed:** 2026-08-10T18:05:00Z
- **Tasks:** 3
- **Files modified:** 7 (6 created source files + 1 created test file)

## Accomplishments
- `AreaCoordinatorResource` + 3 Pages (Create/Edit/List) auto-discovered by Filament under `admin/area-coordinators`, scoped to `role('area_coordinator')`
- `AreaCoordinatorForm` (4 sections, no también-será-líder toggle per D-01) and `AreaCoordinatorsTable` (with `coordinators_count` column via `counts('coordinators')`, D-05)
- 5 new Pest regression tests covering creation+campaign attachment, list visibility, the coordinators_count aggregate, the no-active-campaign guard, and `CoordinatorPolicy` non-interference on admin edits

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AreaCoordinatorResource + its 3 Pages** - `30f3b8a` (feat)
2. **Task 2: Create AreaCoordinatorForm schema + AreaCoordinatorsTable table** - `072239e` (feat)
3. **Task 3: Pest regression tests for ARTIC-01** - `4eb8775` (test), `7979adc` (fix — flaky-test stabilization)

## Files Created/Modified
- `app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php` - Role-scoped Filament Resource, `getEloquentQuery()->role('area_coordinator')`
- `app/Filament/Resources/AreaCoordinators/Pages/CreateAreaCoordinator.php` - Assigns `AREA_COORDINATOR` role + attaches active campaign on create, blocks create with no active campaign
- `app/Filament/Resources/AreaCoordinators/Pages/EditAreaCoordinator.php` - Self-heals campaign attachment on save, `DeleteAction` header action
- `app/Filament/Resources/AreaCoordinators/Pages/ListAreaCoordinators.php` - List page with `CreateAction` header action
- `app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php` - Form: Información personal, Contacto, Ubicación, Acceso sections (no `also_leader` Toggle)
- `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php` - List table with `coordinators_count` column
- `tests/Feature/Filament/AreaCoordinatorResourceCampaignTest.php` - 5 regression tests for ARTIC-01

## Decisions Made
- `$navigationSort = 6` on `AreaCoordinatorResource` appends after existing "Gestión" group resources (Campaigns=1, Coordinators=2, Leaders=3, Voters=4, Invitations=5) rather than renumbering them — minimal diff, no functional requirement.
- No `Schema::hasColumn` visibility guard needed on `area_coordinator_user_id` in the new table/resource (unlike some historical `leaders_count` guards) — the column's migration (Phase 12) is already fully applied.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed `coordinators_count` test asserting `null` instead of `3`**
- **Found during:** Task 3 (Pest regression tests)
- **Issue:** The plan's literal test code created 3 coordinator `User` factories attached to the `area_coordinator` via `area_coordinator_user_id` but never attached them to the active campaign. `User::coordinators()` (`HasMany`) carries `User`'s `CampaignMembershipScope` global scope, which requires campaign membership to be included — so the aggregate the `counts('coordinators')` column relies on silently returned 0/null instead of 3, and separately the test passed the in-memory `$areaCoordinator` model instance (never fetched with the count aggregate applied) instead of its key to `assertTableColumnStateSet`, which also independently caused a `null` actual value.
- **Fix:** Attached each factory-created coordinator to `$this->campaign` via `->campaigns()->attach(...)`, and switched `assertTableColumnStateSet(...)`'s third argument from the `$areaCoordinator` instance to `$areaCoordinator->id` so Filament re-resolves the record through the table's own query (which applies `counts('coordinators')`).
- **Files modified:** `tests/Feature/Filament/AreaCoordinatorResourceCampaignTest.php`
- **Verification:** `php artisan test tests/Feature/Filament/AreaCoordinatorResourceCampaignTest.php` — 5/5 pass.
- **Committed in:** `4eb8775` (part of Task 3 commit)

**2. [Rule 1 - Bug] Fixed intermittent (~35% of runs) validation failure in the CoordinatorPolicy non-interference test**
- **Found during:** Task 3 verification (running the new test file repeatedly, matching the project's documented `CampaignContext` test-pollution flake-hunting pattern, revealed a second, unrelated flake)
- **Issue:** `UserFactory` defaults `phone` to `null` ~20% of the time and `document_number` to `null` ~10% of the time when not explicitly overridden. The "super_admin is not blocked" test created its `$areaCoordinator` fixture via bare `User::factory()->create(['municipality_id' => ...])`, then called `EditAreaCoordinator`'s `save()`, which re-validates the form's required `phone`/`document_number` fields — causing an intermittent `assertHasNoFormErrors()` failure with zero code changes between runs. Confirmed by running the isolated test 10+ times and dumping `$component->errors()` on failure.
- **Fix:** Set `phone`/`document_number` explicitly on the fixture, matching the exact existing precedent in `CoordinatorResourceCampaignTest`'s "self-heals" test (which sets both for the same reason).
- **Files modified:** `tests/Feature/Filament/AreaCoordinatorResourceCampaignTest.php`
- **Verification:** Ran the full file 10 consecutive times post-fix — 5/5 pass every time (previously ~3/8 failed).
- **Committed in:** `7979adc` (separate commit, discovered after Task 3's initial commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 - test bugs found and fixed during this plan's own verification, not pre-existing)
**Impact on plan:** Both fixes were necessary to make the plan's stated acceptance criterion ("`php artisan test --filter=AreaCoordinatorResourceCampaignTest` exits 0") actually true and non-flaky. No scope creep — fixes confined to the new test file.

## Issues Encountered
- This worktree (`agent-a0a54832e1200e119`) was behind `main` at session start, missing this phase's own PLAN.md files (Phase 12-14) plus `vendor/` and `.env` — the same recurring staleness class documented repeatedly in STATE.md's Blockers section. Resolved with the established workaround: confirmed fast-forward ancestry via `git merge-base --is-ancestor`, ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`.
- Running `--filter=AreaCoordinatorResourceCampaignTest` alongside `CoordinatorResourceCampaignTest` and `CoordinatorPolicyTest` together in one `php artisan test` invocation reproduced the already-documented pre-existing `CampaignContext` static-override test-pollution issue (unrelated files' tests fail only when run together, always pass in isolation). Confirmed not a regression: `CoordinatorPolicyTest` alone passes 10/10, and `CoordinatorResourceCampaignTest` + `AreaCoordinatorResourceCampaignTest` together pass cleanly (9/9). Not fixed — matches the standing project-level deferred item.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `AreaCoordinatorResource` is live and test-covered; Phase 15 (Articulador Self-Service Panel, ARTIC-02) can build on top of the same `area_coordinator` role/hierarchy without further admin-resource work.
- ARTIC-03 (coordinador behavior preservation) still needs its own explicit verification pass — this plan only proves ARTIC-01 (the admin-creation half); Phase 14's second plan or a phase-level checkpoint should confirm `CoordinatorResource` itself is untouched (no shared files were modified by this plan, so risk is low).

---
*Phase: 14-articulador-admin-resource-hierarchy-wiring*
*Completed: 2026-08-10*

## Self-Check: PASSED

All 7 created files confirmed present on disk; all 4 task commits (`30f3b8a`, `072239e`, `4eb8775`, `7979adc`) confirmed in `git log`.
