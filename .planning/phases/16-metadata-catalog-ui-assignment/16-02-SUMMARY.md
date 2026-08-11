---
phase: 16-metadata-catalog-ui-assignment
plan: 02
subsystem: ui
tags: [filament, metadata, catalog, repeater, superadmin]

# Dependency graph
requires:
  - phase: 12-hierarchy-metadata-schema-foundation
    provides: "metadata_keys/user_metadata_values schema, MetadataKey/UserMetadataValue models"
provides:
  - "MetadataKeyResource — the single superadmin-only CRUD surface for the metadata catalog"
  - "Structural guarantee behind META-02 (no other key-creating surface exists in the app)"
affects: [16-03, 16-04, 16-05, 16-06, 17-filter-sort-export]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament Repeater::make(...)->simple(TextInput::make(...)) for a flat JSON-array-backed field, conditional on a sibling Select via Get"
    - "Superadmin-only Filament resource gated via canAccess() => CampaignContext::isSuperAdmin(), mirroring AuditLogResource"

key-files:
  created:
    - app/Filament/Resources/MetadataKeys/MetadataKeyResource.php
    - app/Filament/Resources/MetadataKeys/Schemas/MetadataKeyForm.php
    - app/Filament/Resources/MetadataKeys/Tables/MetadataKeysTable.php
    - app/Filament/Resources/MetadataKeys/Pages/ListMetadataKeys.php
    - app/Filament/Resources/MetadataKeys/Pages/CreateMetadataKey.php
    - app/Filament/Resources/MetadataKeys/Pages/EditMetadataKey.php
    - tests/Feature/Metadata/MetadataKeyResourceTest.php
  modified: []

key-decisions:
  - "No MetadataKeySeeder created, per plan instruction — META-01's success criterion requires the superadmin to create keys through the UI, and a seeder would hardcode client business vocabulary untestably"
  - "Simple repeater tested via Livewire's ->set('data.options', [['option' => 'si'], ['option' => 'no']]) rather than ->fillForm(['options' => [...]]) — fillForm's Arr::dot flattening does not match a simple repeater's internal item-keyed state shape and produced spurious required-field validation errors on the repeater's default items; ->set() replaces the whole array atomically"

requirements-completed: [META-01, META-02]

# Metrics
duration: 35min
completed: 2026-08-10
---

# Phase 16 Plan 02: Metadata Key Catalog Resource Summary

**Superadmin-only Filament resource (`MetadataKeyResource`) for CRUD over the metadata catalog, with a type-conditional options repeater and an assignment-count table column.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 3/3 completed
- **Files modified:** 7 created (6 resource files + 1 test file)

## Accomplishments
- Built `MetadataKeyResource` (mirroring `GremioResource`'s Resource/Schemas/Tables/Pages split) gated to `super_admin` only via `CampaignContext::isSuperAdmin()`, in the existing `Configuración` navigation group
- Built `MetadataKeyForm` with key/label/type/options/is_active fields, where the `options` `Repeater::make(...)->simple(...)` is visible only when `type === 'select'`, matching the `MetadataKey.options` array cast exactly
- Built `MetadataKeysTable` with a `values_count` column (`->counts('values')`) and an `is_active` `TernaryFilter`
- Added 8 Pest tests (33 assertions) covering superadmin gating, create for numeric and select types, duplicate-key rejection, edit/deactivate, assignment-history preservation, and the assignment-count table column

## Task Commits

Each task was committed atomically:

1. **Task 1: Scaffold MetadataKeyResource with the superadmin gate** - `50737fb` (feat)
2. **Task 2: Build MetadataKeyForm and MetadataKeysTable** - `e1140df` (feat)
3. **Task 3: Pest coverage for the catalog resource** - `b7d5050` (test)

_Note: no TDD flag on this plan — tasks were built then verified, not RED/GREEN/REFACTOR._

## Files Created/Modified
- `app/Filament/Resources/MetadataKeys/MetadataKeyResource.php` - Superadmin-gated resource, `Configuración` nav group, sort 5
- `app/Filament/Resources/MetadataKeys/Schemas/MetadataKeyForm.php` - key/label/type/options/is_active form with the type-conditional simple repeater
- `app/Filament/Resources/MetadataKeys/Tables/MetadataKeysTable.php` - Catalog listing with `values_count` and an active ternary filter
- `app/Filament/Resources/MetadataKeys/Pages/ListMetadataKeys.php` - `CreateAction` header action
- `app/Filament/Resources/MetadataKeys/Pages/CreateMetadataKey.php` - standard create page
- `app/Filament/Resources/MetadataKeys/Pages/EditMetadataKey.php` - `DeleteAction` header action (blocked at DB level by `restrictOnDelete` when assigned)
- `tests/Feature/Metadata/MetadataKeyResourceTest.php` - META-01/META-02 coverage, 8 tests / 33 assertions

## Decisions Made
- No `MetadataKeySeeder` created — matches the plan's explicit instruction and META-01's UI-creation success criterion.
- Simple-repeater test fill technique: used `->set('data.options', [['option' => 'si'], ['option' => 'no']])` instead of `->fillForm(['options' => ['si', 'no']])`. Filament's `fillFormDataForTesting` flattens the whole state via `Arr::dot()`, which conflicts with a `->simple()` repeater's internal item-keyed (`['option' => value]`) state shape and produced 4 spurious required-field errors (2 from the repeater's `defaultItems(2)` pre-populated empty items, 2 from the malformed flat values). `->set()` replaces the `options` array atomically at the correct path, sidestepping the conflict. Verified the resulting persisted `MetadataKey.options` cast still equals the plan-specified flat `['si', 'no']` shape.

## Deviations from Plan

None — plan executed exactly as written. The repeater-fill technique above is a test-implementation detail (Rule 1 — a bug in the literal test approach, not the plan's design), not a deviation from the plan's specified form/table code, which was implemented verbatim.

### Auto-fixed Issues

**1. [Rule 1 - Bug] Simple-repeater test fill technique produced spurious validation errors**
- **Found during:** Task 3 (Pest coverage), first run of "creates a select key with options"
- **Issue:** `->fillForm(['type' => 'select', 'options' => ['si', 'no'], ...])` failed with `assertHasNoFormErrors()` — Livewire/Filament reported 4 required-field errors under `data.options.*.option` (2 from the repeater's own `defaultItems(2)` mounted empty items, 2 from the flat scalar values landing at the wrong shape via `Arr::dot()`).
- **Fix:** Replaced the `options` key inside `fillForm()` with a follow-up `->set('data.options', [['option' => 'si'], ['option' => 'no']])` call, which assigns the whole array atomically in the shape the repeater's `simple()` wrapper expects.
- **Files modified:** `tests/Feature/Metadata/MetadataKeyResourceTest.php`
- **Verification:** `php artisan test --filter=MetadataKeyResourceTest` — all 8 tests pass; persisted `MetadataKey.options` confirmed to equal the flat `['si', 'no']` array via the `MetadataKey.options` array cast.
- **Committed in:** `b7d5050` (part of Task 3 commit)

**2. [Rule 3 - Blocking] Worktree environment setup**
- **Found during:** Session start
- **Issue:** Worktree was missing `.env`, `vendor/`, `node_modules/`, and `public/build/` (recurring class of issue documented across Phases 12-15).
- **Fix:** `git merge --ff-only main` (worktree was several commits behind), copied `.env` from the main checkout, ran `composer install --no-interaction`, and copied `public/build/` from the main checkout (this plan makes no frontend asset changes, so no `npm run build` was needed).
- **Files modified:** none tracked (environment setup only, `.env`/`vendor`/`public/build` are gitignored)
- **Verification:** `php artisan test --filter=MetadataKeyResourceTest` initially failed on the Vite-manifest-missing error until `public/build` was copied; passed cleanly afterward.

## Known Stubs

None — no hardcoded empty values, placeholder text, or unwired data sources introduced by this plan. `MetadataKeyResource` is fully wired to the `MetadataKey` model and its real relations.

## Self-Check: PASSED

- FOUND: app/Filament/Resources/MetadataKeys/MetadataKeyResource.php
- FOUND: app/Filament/Resources/MetadataKeys/Schemas/MetadataKeyForm.php
- FOUND: app/Filament/Resources/MetadataKeys/Tables/MetadataKeysTable.php
- FOUND: app/Filament/Resources/MetadataKeys/Pages/ListMetadataKeys.php
- FOUND: app/Filament/Resources/MetadataKeys/Pages/CreateMetadataKey.php
- FOUND: app/Filament/Resources/MetadataKeys/Pages/EditMetadataKey.php
- FOUND: tests/Feature/Metadata/MetadataKeyResourceTest.php
- FOUND commit: 50737fb
- FOUND commit: e1140df
- FOUND commit: b7d5050
