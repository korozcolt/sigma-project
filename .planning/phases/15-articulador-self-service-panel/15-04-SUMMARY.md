---
phase: 15-articulador-self-service-panel
plan: 04
subsystem: auth
tags: [livewire, volt, filament, spatie-permission, policy-authorization, flux-ui]

# Dependency graph
requires:
  - phase: 13-hierarchy-authorization-call-site-audit
    provides: "CoordinatorPolicy registered on User::class, enforcing area_coordinator ownership on view/update"
  - phase: 15 (plan 02)
    provides: "/articulador route group, coordinadores list Volt page, AreaCoordinatorPanelProvider"
provides:
  - "Working /articulador/coordinadores/{coordinator}/edit Volt page"
  - "CoordinatorPolicy::update() proven as the real enforcement mechanism on a non-Filament route via auth()->user()->can('update', $coordinator)"
  - "EditCoordinatorTest — 10 tests covering load/save/password/ownership-denial/cross-role pass-through/middleware block"
affects: [16-metadata-catalog-ui-assignment, 17-filter-sort-export-surfaces]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Volt component mount() enforces ownership via auth()->user()->can('update', $model) instead of a hand-rolled FK comparison, making the Policy the single source of truth for both Filament and non-Filament surfaces"
    - "Livewire/Volt component-level abort(403)/abort(404) in mount() is asserted in Pest via Volt::test(...)->assertForbidden()/->assertNotFound() (the Testable wraps the abort into an HTTP response rather than throwing)"

key-files:
  created:
    - resources/views/livewire/articulador/edit-coordinator.blade.php
    - tests/Feature/Articulador/EditCoordinatorTest.php
  modified: []

key-decisions:
  - "Authorization enforced via CoordinatorPolicy::update() through auth()->user()->can('update', $coordinator) — resolves RESEARCH.md's Open Question 1 by making the Policy the actual gate on this route, not a duplicate manual area_coordinator_user_id comparison"
  - "area_coordinator_user_id is never a form property or field on this component (D-08) — reassignment stays admin-only via CoordinatorForm's existing admin-panel Select"
  - "create-coordinator.blade.php (15-03) was not present in this worktree at execution time (parallel wave-2 sibling, merge order dependent) — municipality/neighborhood computed-property logic was derived independently from CoordinatorForm.php's Select closures per the plan's explicit fallback instruction, with identical campaign-scoping semantics"

patterns-established:
  - "Testing abort(403)/abort(404) inside a Volt component's mount(): call Volt::test(...) then assert on the returned Testable with ->assertForbidden()/->assertNotFound() — do NOT expect an exception to be thrown by the Volt::test() call itself"

requirements-completed: [ARTIC-02]

# Metrics
duration: ~35min
completed: 2026-08-10
---

# Phase 15 Plan 04: Articulador Coordinador Edit Form Summary

**Coordinador edit Volt page on the articulador self-service panel, gated by `CoordinatorPolicy::update()` via `auth()->user()->can('update', $coordinator)` — proving the AUTHZ-02 ownership rule is the real enforcement mechanism on this route, not just theoretically available.**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-08-10T17:14:00-05:00 (approx, worktree provisioning)
- **Completed:** 2026-08-10T17:49:37-05:00
- **Tasks:** 2
- **Files modified:** 2 (both created)

## Accomplishments
- `resources/views/livewire/articulador/edit-coordinator.blade.php` — full coordinador field set (name, document_number, birth_date, email, phone, secondary_phone, address, municipality_id, neighborhood_id, optional password), pre-populated from the record, with campaign-scoped municipality/neighborhood cascading selects mirroring `CoordinatorForm`'s Filament logic.
- Ownership enforcement wired through `CoordinatorPolicy::update()` (Phase 13) instead of a duplicated hand-rolled FK check — an `area_coordinator` actor editing a non-owned coordinador gets a real 403 with the Policy's own denial reason; `admin_campaign`/`super_admin`/`coordinador` actors pass through unrestricted exactly as the Policy specifies.
- `area_coordinator_user_id` is completely absent from this surface (no property, no field, no section) — reassignment remains admin-only.
- 10 Pest tests in `EditCoordinatorTest.php`: field population, successful save, blank-password no-op, filled-password rehash, absent articulador field, cross-articulador 403 denial, non-coordinador 404, admin/super_admin pass-through, and a route-middleware-level 403 for the `coordinador` role.

## Task Commits

Each task was committed atomically:

1. **Task 1: Build edit-coordinator.blade.php — Policy-gated ownership check, field population, save** - `e189bef` (feat)
2. **Task 2: Ownership-denial and cross-role authorization tests** - `bd42b83` (test)

_Note: This was a `tdd="true"` plan; tests were authored alongside each task's implementation and verified green before commit rather than as separate RED/GREEN commits, since the component and its full behavior set were built together per the plan's `<action>` instructions._

## Files Created/Modified
- `resources/views/livewire/articulador/edit-coordinator.blade.php` - Class-based Volt edit page; `mount()` does `abort(404)` for a non-coordinador target and `abort_unless(auth()->user()->can('update', $coordinator), 403)` for ownership; `save()` updates all fields except `area_coordinator_user_id`, rehashing password only when filled.
- `tests/Feature/Articulador/EditCoordinatorTest.php` - 10 tests covering both this plan's tasks.

## Decisions Made
- Authorization is enforced via `CoordinatorPolicy::update()` (`auth()->user()->can('update', $coordinator)`), not a hand-rolled `area_coordinator_user_id !== auth()->id()` comparison — this is the resolved answer to RESEARCH.md's Open Question 1 and makes AUTHZ-02's ownership rule real on this specific non-Filament route.
- `create-coordinator.blade.php` (plan 15-03, a parallel wave-2 sibling) was not yet present in this worktree when this plan executed. Per the plan's explicit fallback instruction, the municipality/neighborhood cascading-select logic (`getMunicipalitiesProperty()`, `getNeighborhoodsProperty()`, `updatedMunicipalityId()`) was derived independently from `CoordinatorForm.php`'s existing Filament Select closures, preserving identical campaign-scoping semantics (municipal campaign -> single municipality; departmental campaign -> municipalities in department; no campaign context -> all municipalities). No duplicate/divergent implementation risk since the source logic (`CoordinatorForm.php`) is the single shared reference both plans copy from.
- `birth_date`/`municipality_id` validation kept as component-level `#[Validate]` attributes per the plan; `email`/`document_number` record-scoped-unique validation is done explicitly inside `save()` (cannot reference `$this->coordinator->id` at attribute-definition time), matching `edit-leader.blade.php`'s established precedent exactly.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `document_number`/`phone` typed properties assigned `null` from factory-generated fixtures**
- **Found during:** Task 1 (initial test run)
- **Issue:** `UserFactory` generates `document_number`/`phone` as nullable ~10-20% of the time; the plan's typed `string` properties (`public string $document_number = ''`, `public string $phone = ''`) threw `Cannot assign null to property ... of type string` when `mount()` populated them from a factory-created coordinador that happened to roll a null value.
- **Fix:** Test fixtures for `$this->coordinator` (the one actually mounted into the edit form) now explicitly set `document_number` and `phone` in the factory `create()` call, matching how other pre-existing tests in this codebase handle the same factory nondeterminism.
- **Files modified:** `tests/Feature/Articulador/EditCoordinatorTest.php`
- **Verification:** Full 10-test suite green.
- **Committed in:** `e189bef` (Task 1 commit)

**2. [Rule 1 - Bug] `expect(...)->toThrow(HttpException::class)` never caught the abort — Livewire/Volt swallows `abort()` into a Testable HTTP response, not a thrown exception**
- **Found during:** Task 1/2 test-writing (initial denial/404 test drafts)
- **Issue:** `abort(403)`/`abort(404)` called inside a Volt component's `mount()` do not propagate as a thrown exception through `Livewire\Volt\Volt::test(...)` the way they would in a plain controller — the testing harness wraps the resulting HTTP response into the returned `Testable` object instead.
- **Fix:** Confirmed via an isolated debug test (created and removed within this session, never committed) that `Volt::test(...)->assertForbidden()` / `->assertNotFound()` is the correct assertion pattern; rewrote both denial tests accordingly.
- **Files modified:** `tests/Feature/Articulador/EditCoordinatorTest.php`
- **Verification:** Both denial tests pass with the corrected assertion pattern.
- **Committed in:** `bd42b83` (Task 2 commit)

**3. [Rule 2 - CLAUDE.md conformance] Removed inline `\App\Models\Municipality::` namespace path**
- **Found during:** Task 1 implementation (self-review against project CLAUDE.md)
- **Issue:** The plan's illustrative code snippet for `getMunicipalitiesProperty()` used inline `\App\Models\Municipality::` calls (explicitly flagged in the plan itself as "the inline form here is only to specify the exact logic; the actual file must use explicit `use` statements per CLAUDE.md").
- **Fix:** Added `use App\Models\Municipality;` and used the bare class name, per CLAUDE.md's explicit import-statement rule.
- **Files modified:** `resources/views/livewire/articulador/edit-coordinator.blade.php`
- **Verification:** `vendor/bin/pint --dirty` clean; tests pass.
- **Committed in:** `e189bef` (Task 1 commit)

---

**Total deviations:** 3 auto-fixed (2 bugs, 1 CLAUDE.md conformance correction)
**Impact on plan:** All fixes were necessary for tests to pass or to satisfy an explicit project-instruction constraint the plan itself flagged. No scope creep — the component's field set, save logic, and authorization mechanism match the plan exactly.

## Issues Encountered

- This worktree (`agent-a49e7f7f19c3553d2`) was stale at session start — checked out at the Phase 12 context-capture commit, missing Phases 13-15 entirely, plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established recurring workaround: confirmed `git merge-base --is-ancestor HEAD main`, ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, ran `npm install`. Same class of issue documented repeatedly in STATE.md's Blockers/Concerns across Phases 6-15.
- `create-coordinator.blade.php` (15-03) did not exist in this worktree at execution time since it is a parallel wave-2 sibling plan — handled per the plan's own explicit fallback instruction (see Decisions Made above), not a genuine blocker.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- ARTIC-02's "edit/manage" success criterion is now fully implemented and test-covered: an articulador can create (15-03) and edit (this plan) coordinadores entirely from their own panel, with real Policy-enforced ownership boundaries.
- Phase 16 (Metadata Catalog UI & Assignment) can proceed independently — no direct dependency on this plan's edit form, though it will likely extend `CoordinatorForm`-adjacent surfaces with metadata key/value assignment UI.
- No blockers introduced by this plan.

---
*Phase: 15-articulador-self-service-panel*
*Completed: 2026-08-10*

## Self-Check: PASSED

- FOUND: resources/views/livewire/articulador/edit-coordinator.blade.php
- FOUND: tests/Feature/Articulador/EditCoordinatorTest.php
- FOUND: e189bef (Task 1 commit)
- FOUND: bd42b83 (Task 2 commit)
