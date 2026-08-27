---
phase: 260827-d0c
plan: 01
subsystem: voter-operations
tags: [livewire, volt, flux, filament-catalog-consumers, authorization]

# Dependency graph
requires:
  - phase: (pre-existing) gremio/subcategoria/lugar_expedicion_cedula/placa columns on voters + Gremio/Subcategoria models/catalog (Filament, admin-only)
    provides: gremios/subcategorias tables, Voter fillable/relations already in place
provides:
  - Leader and coordinator create-apoyo forms now capture gremio, subcategoria, lugar_expedicion_cedula, placa
  - First-ever edit surfaces for líder/coordinador apoyos (leader.edit-voter, coordinator.leader-edit-voter), scoped to only these 4 fields
  - "Editar" links wired into leader/my-voters and coordinator/leader-voters list views
affects: [voter-operations, coordinator-panel, leader-panel]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dependent gremio->subcategoria Livewire select pair (wire:model.live + updatedGremioId() reset), mirroring the existing department->municipality/municipality->neighborhood cascade pattern already used in these same forms"
    - "Minimal single-purpose edit Volt component (only 4 fields, not a full Voter editor) layered on top of an existing read-heavy list view, matching the project's existing 'add apoyo for leader' component style"
    - "Explicit abort_unless ownership guard in mount() backed by VoterPolicy::update() as belt-and-suspenders — required because VoterPolicy is role-only, not ownership-scoped"

key-files:
  created:
    - resources/views/livewire/leader/edit-voter.blade.php
    - resources/views/livewire/coordinator/leader-edit-voter.blade.php
    - tests/Feature/Leader/RegisterVoterAdditionalFieldsTest.php
    - tests/Feature/Coordinator/LeaderAddVoterAdditionalFieldsTest.php
    - tests/Feature/Leader/EditVoterAdditionalFieldsTest.php
    - tests/Feature/Coordinator/LeaderEditVoterAdditionalFieldsTest.php
  modified:
    - resources/views/livewire/leader/register-voter.blade.php
    - resources/views/livewire/coordinator/leader-add-voter.blade.php
    - resources/views/livewire/leader/my-voters.blade.php
    - resources/views/livewire/coordinator/leader-voters.blade.php
    - routes/web.php

key-decisions:
  - "No changes to VoterPolicy — it already authorizes create/update for coordinator/leader roles; ownership is enforced entirely via explicit abort_unless checks in each edit component's mount(), mirroring the exact pattern already used by coordinator/leader-add-voter.blade.php's mount() for leader-belongs-to-coordinator."
  - "Gremio/Subcategoria catalog (Filament, admin-only CRUD) was not touched — both new forms and both new edit components only ever SELECT from Gremio::orderBy('name')->get() / Subcategoria::where('gremio_id', ...)->get(), never create/edit gremios or subcategorias."
  - "coordinator.leader-edit-voter enforces TWO independent ownership checks in mount() (leader belongs to coordinator, AND voter->registered_by === leader->id) — closing the exact class of cross-leader/cross-coordinator data-leak bug already fixed elsewhere in this project (STATE.md quick tasks 260804-i5f/260804-jbc)."

patterns-established:
  - "When a Filament-admin-only reference catalog (Gremio/Subcategoria) needs to be consumed read-only by non-admin Livewire/Volt forms, use plain Eloquent queries in computed properties (getGremiosProperty()/getSubcategoriasProperty()) rather than any Filament form-builder API — the catalog's own admin CRUD panel stays the single source of truth for writes."

requirements-completed: []

# Metrics
duration: 35min
completed: 2026-08-27
---

# Quick Task 260827-d0c: Coordinadores y líderes pueden llenar y editar gremio/subcategoría/lugar de expedición/placa Summary

**Closed the gap where líder/coordinador create-apoyo forms silently dropped 4 already-existing Voter columns (gremio_id, subcategoria_id, lugar_expedicion_cedula, placa), and added the first-ever edit surface for these two roles — previously they could only create apoyos, never edit them.**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-08-27T (session start)
- **Completed:** 2026-08-27
- **Tasks:** 4/4 completed
- **Files modified:** 5 modified, 6 created (2 Volt components + 4 Pest test files)

## Accomplishments
- Both create-apoyo forms (`leader.register-voter`, `coordinator.leader-add-voter`) now expose an "Información Adicional" section with a dependent Gremio→Subcategoría select pair plus lugar de expedición de cédula / placa text inputs, all optional, all persisted on `Voter::create`.
- Built the project's first edit surfaces for líder/coordinador apoyos: `leader.edit-voter` (own apoyos only) and `coordinator.leader-edit-voter` (apoyos of the coordinator's own leaders only) — deliberately scoped to only these 4 fields, not a full Voter editor (the existing Filament `VoterResource` remains the full-field admin editor).
- Both new edit routes are ownership-gated by an explicit `abort_unless` check in `mount()`, independent of and in addition to the pre-existing, role-only `VoterPolicy::update()` — the coordinator variant checks both "leader belongs to coordinator" and "voter was registered by that leader."
- "Editar" links now appear on every voter row in `leader/my-voters.blade.php` and `coordinator/leader-voters.blade.php`, routing to the new edit pages.
- 4 new Pest test files (15 tests, 58 assertions) cover persistence of all 4 fields, nullable/empty-state saves, gremio→subcategoria mismatch validation, gremio-change resetting subcategoria, 403s on both ownership axes, and that editing leaves every other Voter column untouched.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add gremio/subcategoria/lugar_expedicion_cedula/placa to leader's create-apoyo form** - `d487dce` (feat)
2. **Task 2: Add the same 4 fields to coordinator's "agregar apoyo para líder" form** - `536ad48` (feat)
3. **Task 3: Create the leader's edit-apoyo view + route + "Editar" link** - `66d2878` (feat)
4. **Task 4: Create the coordinator's edit-apoyo view + route + "Editar" link** - `9bed753` (feat)

**Plan metadata:** (this commit, docs: complete quick task)

## Files Created/Modified
- `resources/views/livewire/leader/register-voter.blade.php` - Added gremio_id/subcategoria_id/lugar_expedicion_cedula/placa state, validation, dependent-select reset, Voter::create persistence, and "Información Adicional" markup card
- `resources/views/livewire/coordinator/leader-add-voter.blade.php` - Same 4-field addition as leader form, ownership/mount logic unchanged
- `resources/views/livewire/leader/edit-voter.blade.php` (new) - Volt component editing only the 4 fields for an apoyo the leader themselves registered, ownership-gated
- `resources/views/livewire/coordinator/leader-edit-voter.blade.php` (new) - Volt component editing only the 4 fields for an apoyo of one of the coordinator's leaders, double ownership-gated
- `resources/views/livewire/leader/my-voters.blade.php` - Added "Editar" button per voter row, linking to `leader.edit-voter`
- `resources/views/livewire/coordinator/leader-voters.blade.php` - Added "Editar" button per voter row, linking to `coordinator.leaders.voters.edit`
- `routes/web.php` - Added `leader.edit-voter` (`voters/{voter}/edit`) and `coordinator.leaders.voters.edit` (`leaders/{leader}/voters/{voter}/edit`) routes
- `tests/Feature/Leader/RegisterVoterAdditionalFieldsTest.php` (new) - 3 tests
- `tests/Feature/Coordinator/LeaderAddVoterAdditionalFieldsTest.php` (new) - 3 tests
- `tests/Feature/Leader/EditVoterAdditionalFieldsTest.php` (new) - 4 tests
- `tests/Feature/Coordinator/LeaderEditVoterAdditionalFieldsTest.php` (new) - 5 tests

## Deviations from Plan

None - plan executed exactly as written. All interface details (route names, mount() ownership patterns, validation rules) verified in the plan's `<interfaces>` section matched the codebase exactly on first read, and every task's Pest tests passed on the first run with no debugging required.

## Verification Results

```
php artisan test --filter=RegisterVoter                        → 19 passed (73 assertions)
php artisan test --filter=LeaderAddVoter                       → 3 passed (16 assertions)
php artisan test --filter=EditVoterAdditionalFieldsTest        → 9 passed (21 assertions, both roles' files)
php artisan test --filter=LeaderEditVoterAdditionalFieldsTest  → 5 passed (11 assertions)
php artisan test --filter=AddVoterFromLeaderDetailTest         → 5 passed (9 assertions)
vendor/bin/pint --dirty                                        → PASS, 0 files needing changes
php artisan test --testsuite=Feature --filter="Voter|Leader|Coordinator" → 625 passed (1785 assertions), no regressions
```

## Known Stubs

None. All 4 fields are real, persisted, editable data — no placeholders, no hardcoded empty values feeding the UI.

## Pending Manual Verification

Per the user's standing preference (browser-verify before considering UI changes deployed), the following has NOT yet been done in this session and is recommended before this is considered fully verified in production:

- Real-browser click-through of both create forms' new "Información Adicional" section (gremio select → subcategoria select enabling/filtering correctly, lugar de expedición + placa inputs saving).
- Real-browser click-through of both new edit pages (as a leader editing their own apoyo, and as a coordinator editing one of their leaders' apoyos), confirming pre-filled values, the dependent select reset behavior, and the redirect after save.
- Confirming the "Editar" links render correctly in both `my-voters` and `leader-voters` list views for a real logged-in leader/coordinador.

This joins the other pending `checkpoint:human-verify` items already tracked in STATE.md's Blockers/Concerns section (this quick task did not have an explicit checkpoint task in its PLAN.md — it was fully autonomous — but the project's standing feedback preference still applies).

## Self-Check: PASSED

All claimed files verified to exist on disk; all claimed commit hashes verified present in git log.
