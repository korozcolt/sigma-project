---
phase: 14-articulador-admin-resource-hierarchy-wiring
verified: 2026-08-10T18:12:59Z
status: passed
score: 6/6 must-haves verified
---

# Phase 14: Articulador Admin Resource & Hierarchy Wiring Verification Report

**Phase Goal:** A superadmin/admin_campaign can create and manage articulador users from the admin panel and wire coordinadores to them, while existing coordinador behavior is fully preserved whether or not an articulador is assigned.
**Verified:** 2026-08-10T18:12:59Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Superadmin/admin_campaign can create a user with the Articulador role from the admin panel | ✓ VERIFIED | `AreaCoordinatorResource` + `CreateAreaCoordinator` exist, `assignRole(UserRole::AREA_COORDINATOR->value)` in `afterCreate()`, campaign attachment via `syncWithoutDetaching`, guarded by an active-campaign check. Route `admin/area-coordinators/create` registered. Test `creating an area coordinator attaches it to the active campaign` passes. |
| 2 | A created articulador is attached to the active campaign and appears in the Articuladores list when that campaign is active | ✓ VERIFIED | `attachActiveCampaign()` writes to `campaigns()` pivot with `role_id`/`assigned_at`/`assigned_by`. `AreaCoordinatorResource::getEloquentQuery()` scopes via `->role('area_coordinator')`; global `CampaignMembershipScope` on `User` filters the list to the active campaign. Test `area coordinator is visible in the list when filtering by the active campaign` passes. |
| 3 | The Articuladores list table shows a count of coordinadores assigned to each articulador | ✓ VERIFIED | `AreaCoordinatorsTable` has `TextColumn::make('coordinators_count')->counts('coordinators')`, backed by `User::coordinators(): HasMany`. Test `list table shows the count of coordinadores assigned to each area coordinator` asserts `assertTableColumnStateSet('coordinators_count', 3, ...)` and passes. |
| 4 | Admin (super_admin/admin_campaign) actions on articulador records are not blocked by CoordinatorPolicy | ✓ VERIFIED | `CoordinatorPolicy::authorizeOwnership()` only restricts actors with `hasRole(AREA_COORDINATOR)` acting on targets with `hasRole(COORDINATOR)` — an `area_coordinator`-role target fails that second check and gets `Response::allow()` immediately, and a `super_admin` actor fails the first check regardless of target. Test `super_admin is not blocked by CoordinatorPolicy when editing an area coordinator record` passes. |
| 5 | Admin can assign or reassign an articulador to a coordinador via a Select field on CoordinatorForm, and the selector is optional (ARTIC-03) | ✓ VERIFIED | `CoordinatorForm.php` line 159-168: `Select::make('area_coordinator_user_id')` with `relationship(name: 'areaCoordinator', ...)`, no `->required()` call. Tests `admin can assign an articulador to a coordinador via CoordinatorForm select` and `coordinador without an articulador assigned saves successfully (ARTIC-03, no regression)` both pass. |
| 6 | The articulador dropdown only shows articuladores belonging to the active campaign | ✓ VERIFIED | `modifyQueryUsing` role-filters to `area_coordinator`; `User`'s global `CampaignMembershipScope` restricts the relationship query to the active campaign (no manual closure needed, confirmed by test, not assumed). Test `articulador dropdown on CoordinatorForm only shows articuladores from the active campaign` passes. |

**Score:** 6/6 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php` | Role-scoped Filament Resource for area_coordinator | ✓ VERIFIED | 58 lines. Contains `->role('area_coordinator')`, `$model = User::class`, `getPages()` registering index/create/edit. |
| `app/Filament/Resources/AreaCoordinators/Pages/CreateAreaCoordinator.php` | assignRole + active-campaign attachment on create | ✓ VERIFIED | 57 lines. `assignRole(UserRole::AREA_COORDINATOR->value)`, active-campaign guard via `Halt`, `attachActiveCampaign()`. |
| `app/Filament/Resources/AreaCoordinators/Pages/EditAreaCoordinator.php` | Self-healing campaign attachment + delete action | ✓ VERIFIED | 55 lines. `DeleteAction::make()`, `attachActiveCampaignIfMissing()` in `afterSave()`. |
| `app/Filament/Resources/AreaCoordinators/Pages/ListAreaCoordinators.php` | List page with CreateAction header action | ✓ VERIFIED | 19 lines. `CreateAction::make()` present. |
| `app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php` | Form mirroring CoordinatorForm minus also_leader Toggle (D-01) | ✓ VERIFIED | 170 lines, 4 `Section::make(` occurrences, no `also_leader`/`Toggle` string present. |
| `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php` | List table with coordinators_count column (D-05) | ✓ VERIFIED | 62 lines. `counts('coordinators')` present, no `leaders_count` string. |
| `tests/Feature/Filament/AreaCoordinatorResourceCampaignTest.php` | Regression coverage for ARTIC-01 | ✓ VERIFIED | 139 lines, 5 tests, all pass. Includes `afterEach(fn () => CampaignContext::setCampaignId(null));` (post-merge pollution fix). |
| `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` | New area_coordinator_user_id Select, campaign-scoped | ✓ VERIFIED | 192 lines. `Select::make('area_coordinator_user_id')` with `name: 'areaCoordinator'`, `UserRole::AREA_COORDINATOR->value`, no `->required()` chained. |
| `tests/Feature/Filament/CoordinatorResourceCampaignTest.php` | Extended coverage for selector assignment/optionality/scoping | ✓ VERIFIED | 177 lines, 7 tests (4 pre-existing + 3 new), all pass. Includes the same `afterEach` pollution fix. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `AreaCoordinatorResource::getEloquentQuery()` | `role('area_coordinator')` query scope | `parent::getEloquentQuery()->role('area_coordinator')` | ✓ WIRED | Confirmed on line 37 of `AreaCoordinatorResource.php`. |
| `CreateAreaCoordinator::afterCreate()` | Spatie role assignment | `assignRole(UserRole::AREA_COORDINATOR->value)` | ✓ WIRED | Confirmed on line 36 of `CreateAreaCoordinator.php`. |
| `AreaCoordinatorsTable coordinators_count column` | `User::coordinators()` HasMany relation | `TextColumn::make('coordinators_count')->counts('coordinators')` | ✓ WIRED | Confirmed on lines 39-40 of `AreaCoordinatorsTable.php`; `User::coordinators(): HasMany` exists at `app/Models/User.php:153`. |
| `CoordinatorForm Select area_coordinator_user_id` | `User::areaCoordinator()` BelongsTo relation | `->relationship(name: 'areaCoordinator', ...)` | ✓ WIRED | Confirmed on lines 159-168 of `CoordinatorForm.php`; `User::areaCoordinator(): BelongsTo` exists at `app/Models/User.php:148`. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| --- | --- | --- | --- | --- |
| `AreaCoordinatorsTable` | `coordinators_count` | `User::coordinators()` HasMany, aggregated via Filament's `->counts()` (adds a `withCount` subquery) | Yes — test seeds 3 real `User` rows with matching `area_coordinator_user_id` + campaign attachment and asserts the column reports `3`, not a static/hardcoded value | ✓ FLOWING |
| `CoordinatorForm` `area_coordinator_user_id` Select | options list | `User::query()->role('area_coordinator')` filtered by `User`'s global `CampaignMembershipScope` | Yes — test seeds two real articuladores in two different campaigns and asserts only the active campaign's articulador appears in `$field->getOptions()` | ✓ FLOWING |
| `AreaCoordinatorResource` list | table records | `User::query()->role('area_coordinator')` filtered by `CampaignMembershipScope` | Yes — test creates a real record via the Create form and asserts it's visible via `assertCanSeeTableRecords` | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Filament auto-discovers `AreaCoordinatorResource` routes without manual registration | `php artisan route:list --no-interaction \| grep -i area-coordinator` | 3 routes returned: `admin/area-coordinators` (index), `admin/area-coordinators/create`, `admin/area-coordinators/{record}/edit` | ✓ PASS |
| Pint style compliance on all phase-touched files | `vendor/bin/pint --test <9 files>` | `PASS ........................................................... 9 files` | ✓ PASS |
| Full regression set (Phase 14's new tests + Phase 12/13 dependents) runs clean together, confirming the post-merge `afterEach` pollution fix holds | `php artisan test` on the 11-file regression set specified for this verification, in the documented order | `Tests: 55 passed (183 assertions)` — includes `Policies/CoordinatorPolicyTest` (10/10) run immediately after the two files that previously leaked `CampaignContext` static state into it | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| ARTIC-01 | 14-01, 14-02 | Superadmin/admin_campaign puede crear un usuario con rol Articulador (`area_coordinator`) | ✓ SATISFIED | `AreaCoordinatorResource` (14-01) provides creation; `CoordinatorForm` Select (14-02) provides the assignment/reassignment half. Both halves test-covered and passing. REQUIREMENTS.md marks `[x]` Done, mapped to Phase 14. |
| ARTIC-03 | 14-02 | Coordinador sigue funcionando exactamente igual que hoy, tenga o no un articulador asignado | ✓ SATISFIED | New Select is optional (no `->required()`), dedicated regression test proves a coordinador saves identically with `area_coordinator_user_id` null; all 4 pre-existing `CoordinatorResourceCampaignTest` tests (unrelated to the new field) still pass unmodified in behavior. REQUIREMENTS.md marks `[x]` Done, mapped to Phase 14. |

No orphaned requirements found — REQUIREMENTS.md maps only ARTIC-01 and ARTIC-03 to Phase 14, and both appear in the combined `requirements` frontmatter of plans 14-01/14-02.

### Anti-Patterns Found

None. Scanned all 7 phase-created/modified source files (Resource, 3 Pages, Schema, Table, CoordinatorForm) for TODO/FIXME/placeholder/stub markers, empty handlers, and hardcoded empty data. The one `->placeholder('—')` match in `AreaCoordinatorsTable.php` is a legitimate Filament column empty-state string, not a stub marker.

### Human Verification Required

None required for automated goal verification — all observable truths, artifacts, key links, data flow, and the full regression suite are verified programmatically. The plan's own `<verification>` block recommends a manual browser check (per project convention) of the Articulador Select appearing correctly in the CoordinatorForm UI; this is a nice-to-have polish check, not a blocker, since the underlying Livewire form-field assertions already prove the field's options and optionality behaviorally.

### Gaps Summary

No gaps. Both plans' artifacts exist, are substantive (not stubs), are wired to their data sources, and produce real data end-to-end. The post-merge regression (`CampaignContext` static-state leak from the two new test files into `CoordinatorPolicyTest`) was already identified and fixed by the orchestrator (commit `5e3f478`); this verification independently re-ran the full specified 11-file regression set in the documented order and confirmed 55/55 pass, with no other cross-phase pollution detected in this set. Pint is clean on all changed files, and route auto-discovery is confirmed working.

---

*Verified: 2026-08-10T18:12:59Z*
*Verifier: Claude (gsd-verifier)*
