---
phase: 260830-iok
plan: 01
subsystem: auth
tags: [filament, livewire, volt, fortify, validation, migration]

# Dependency graph
requires:
  - phase: (pre-existing) FortifyServiceProvider dual login (email or document_number)
    provides: authenticateUsing() fallback to document_number already worked, untouched by this plan
  - phase: (pre-existing) users.document_number nullable+unique since 2025-11-03
    provides: the other half of the "one or the other" pair was already nullable
provides:
  - users.email is now nullable at the DB column level (unique index preserved)
  - 4 Filament Schemas (Leader/Coordinator/AreaCoordinator/User) require email XOR document_number instead of both
  - 3 Livewire Volt components (register-leader, create-leader, edit-leader) enforce the same cross-rule
  - Blank email/document_number always persists as NULL, never '', avoiding false unique-index collisions
affects: [auth, leader-onboarding, coordinator-onboarding, admin-user-management]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament cross-required fields: ->required(fn (Get $get): bool => blank($get('other_field'))) on both fields, each pointing at the other"
    - "Filament blank->NULL persistence: ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null) paired with the required() closure above"
    - "Livewire/Volt cross-required fields: #[Validate('nullable|...|required_without:other_field')] on both properties"
    - "Livewire/Volt blank->NULL persistence: blank($this->field) ? null : $this->field inline in the create()/update() array, since typed component properties can't hold null directly"

key-files:
  created:
    - database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php
    - tests/Feature/Auth/LoginWithoutEmailTest.php
    - tests/Feature/Filament/RequireEmailOrDocumentNumberTest.php
    - tests/Feature/RequireEmailOrDocumentNumberLivewireTest.php
  modified:
    - app/Filament/Resources/Leaders/Schemas/LeaderForm.php
    - app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php
    - app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php
    - app/Filament/Resources/Users/Schemas/UserForm.php
    - resources/views/livewire/public/register-leader.blade.php
    - resources/views/livewire/coordinator/create-leader.blade.php
    - resources/views/livewire/coordinator/edit-leader.blade.php
    - tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php

key-decisions:
  - "edit-leader.blade.php's mount() previously did $this->email = $leader->email directly onto a non-nullable public string $email property — now that users.email can be NULL in the DB, this would throw a TypeError for any leader created without an email. Fixed (Rule 1 - bug) with $this->email = $leader->email ?? '', matching the same string-typed-property convention already used by the other 2 Volt components."
  - "edit-leader.blade.php has no document_number form field (this form only edits name/email/password/neighborhood), so its cross-rule is validated against the leader's already-persisted $this->leader->document_number in save(), not against a sibling form field — exactly as scoped in the plan's interfaces section."
  - "Filament test setup: CampaignContext must be explicitly set per-test (CampaignContext::setCampaignId($this->campaign->id) for Coordinator/AreaCoordinator create tests, since their CreateRecord pages Halt without an unambiguous active campaign; left at setCampaignId(null) — global/all-campaigns mode — for Leader/User tests, since forcing a specific campaign would filter the Leader form's coordinator_user_id relationship options down to only coordinators already attached to that campaign, which is unrelated to what this plan tests). This mirrors the exact per-test pattern already used in the pre-existing LeaderResourceCampaignTest/CoordinatorResourceCampaignTest/AreaCoordinatorResourceCampaignTest files."

patterns-established:
  - "Any future 'require A or B, not both empty' field pair on a Filament form should use the same cross-closure + dehydrateStateUsing(null-on-blank) pair established in Task 2's 4 schemas."
  - "Any future Volt component with the same pattern should use nullable|...|required_without: cross-validation plus explicit blank()?null: conversion at the point of persistence."

requirements-completed: []

# Metrics
duration: ~40min
completed: 2026-08-30
---

# Quick Task 260830-iok: Quitar obligatoriedad del correo para líderes y coordinadores Summary

**Made `users.email` nullable at the DB level and replaced 7 hardcoded `->required()`/`required` rules (4 Filament schemas + 3 Livewire Volt components) with a cross-required "email OR document_number" rule, so líderes/coordinadores/articuladores/usuarios can register with only a cédula — closing the gap where `document_number` was already nullable+unique in the DB but effectively still mandatory in every form.**

## Performance

- **Duration:** ~40 min (including worktree provisioning: fast-forward merge, `composer install`, `.env`/`public/build` copy)
- **Started:** 2026-08-30 (session start)
- **Completed:** 2026-08-30
- **Tasks:** 3/3 completed
- **Files modified:** 8 modified, 4 created (1 migration + 3 new Pest test files)

## Accomplishments
- `users.email` column is now `nullable` via a `->change()` migration (same doctrine/dbal-free pattern already used by 7 prior migrations in this project) — the existing unique index is untouched, since MySQL permits multiple `NULL`s under a unique index.
- All 4 admin-facing Filament forms (Leader, Coordinator, AreaCoordinator, User) now require `email` only when `document_number` is blank and vice versa, via cross-referencing `->required(fn (Get $get) ...)` closures, with `->dehydrateStateUsing()` ensuring a blank field is persisted as `NULL` rather than `''` (which would otherwise collide on the unique index for a second blank record).
- All 3 self-service/coordinator Livewire Volt components (`public.register-leader`, `coordinator.create-leader`, `coordinator.edit-leader`) enforce the identical cross-rule via `required_without:` validation attributes, with the same blank-to-`NULL` conversion applied explicitly before `User::create()`/`update()`.
- The pre-existing dual login (`FortifyServiceProvider::configureAuthentication()`, email-or-document_number fallback) required zero code changes — now covered by a new regression test proving a user with no email can log in with just their cédula.
- Fixed a latent bug (Rule 1) in `edit-leader.blade.php`'s `mount()`: it assigned `$leader->email` directly onto a non-nullable `public string $email` property, which would throw a `TypeError` the first time a coordinator tried to edit a leader created without an email (a scenario this very plan makes possible for the first time).
- Rewrote the one pre-existing test broken by the new behavior (`CreateLeaderRegistraduriaLookupTest`'s "save without a document_number" case) to reflect that email-present + cédula-blank is now a valid, successful save rather than a validation failure.

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration — users.email nullable + login-without-email test** - `46fca1d` (feat)
2. **Task 2: Regla cruzada email/document_number en los 4 Filament Schemas** - `bc697dd` (feat)
3. **Task 3: Regla cruzada email/document_number en los 3 componentes Livewire Volt** - `fc70199` (feat)

**Plan metadata:** (this commit, docs: complete quick task)

## Files Created/Modified
- `database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php` (new) - Makes `users.email` nullable via `->change()`, preserving the unique index
- `tests/Feature/Auth/LoginWithoutEmailTest.php` (new) - Proves a user can be created with `email = null` and log in via `document_number` through the existing dual-login path
- `app/Filament/Resources/Leaders/Schemas/LeaderForm.php` - Cross-required email/document_number + dehydrateStateUsing(null) + helperText
- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` - Same cross-rule applied
- `app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php` - Same cross-rule applied
- `app/Filament/Resources/Users/Schemas/UserForm.php` - Same cross-rule applied (respecting its existing `maxLength(255)` on `document_number`, unlike the other 3 forms' `maxLength(50)`)
- `tests/Feature/Filament/RequireEmailOrDocumentNumberTest.php` (new) - 12 tests (4 resources x 3 scenarios: cédula-only succeeds, email-only succeeds, neither fails on both fields)
- `resources/views/livewire/public/register-leader.blade.php` - `required_without:` cross-validation + blank->NULL persistence + updated labels/helper text
- `resources/views/livewire/coordinator/create-leader.blade.php` - Same cross-validation/persistence/label changes as register-leader
- `resources/views/livewire/coordinator/edit-leader.blade.php` - `nullable` email validated against the leader's persisted `document_number` (no form field for it here) + mount() null-safety fix + updated label/helper text
- `tests/Feature/RequireEmailOrDocumentNumberLivewireTest.php` (new) - 6 tests covering all 3 components' success/failure paths
- `tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php` - Rewrote the "save without a document_number" test to expect success (per the new rule) instead of a validation failure

## Decisions Made
See `key-decisions` in the frontmatter above — summarized: (1) fixed a latent `edit-leader.blade.php` mount() TypeError that this plan's own DB change would otherwise trigger; (2) set `CampaignContext` explicitly per test in the new Filament test file, matching the exact per-test pattern already established in the 3 pre-existing `*ResourceCampaignTest.php` files, rather than a single blanket `beforeEach` value (the two resource families need opposite defaults: Coordinator/AreaCoordinator's `CreateRecord` pages `Halt` without an unambiguous active campaign, while Leader's coordinator-select relationship gets filtered out entirely under a *specific* campaign context if the test coordinator isn't attached to it).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a null-to-non-nullable-string TypeError in edit-leader.blade.php's mount()**
- **Found during:** Task 3
- **Issue:** `mount()` assigned `$this->email = $leader->email;` directly onto a `public string $email` property (not nullable). Once `users.email` became nullable (Task 1) and both create-paths (Task 3) started allowing leaders with `email = null`, opening the edit form for such a leader would throw a `TypeError: Cannot assign null to property ... of type string`.
- **Fix:** Changed to `$this->email = $leader->email ?? '';`, consistent with how the other 2 Volt components already treat `email`/`document_number` as string-typed with `''` as the "empty" sentinel.
- **Files modified:** `resources/views/livewire/coordinator/edit-leader.blade.php`
- **Verification:** Covered by the new test `'coordinator edit-leader saves with a blank email when the leader already has a document_number'` in `RequireEmailOrDocumentNumberLivewireTest.php`, which opens the edit form for a leader with `document_number` set and no email.
- **Committed in:** `fc70199` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 bug fix, Rule 1)
**Impact on plan:** Necessary for correctness — without this fix, the plan's own new "leader without email" capability would have made editing that same leader crash. No scope creep; the fix is a one-line null-coalesce scoped exactly to the property this plan's DB change affects.

## Issues Encountered

- Initial test-writing pass for `RequireEmailOrDocumentNumberTest.php` (Task 2) hit two false starts before all 12 cases passed: (1) setting a single, blanket `CampaignContext::setCampaignId(...)` value for the whole file caused the Leader tests' `coordinator_user_id` Select to fail relationship-existence validation (the campaign-membership global scope on `User` filtered the freshly-created coordinator out of the options, since it wasn't attached to that specific campaign), while (2) `Coordinator`/`AreaCoordinator`'s `CreateRecord::mutateFormDataBeforeCreate()` silently `Halt`s (no form error, just a danger notification) when no unambiguous active campaign is resolvable. Resolved by setting `CampaignContext` explicitly per test to whichever value that resource family actually needs (mirrored from the pre-existing `*ResourceCampaignTest.php` files), rather than one shared default.
- This project's already-documented pre-existing `CampaignContext` static-override test-pollution flake (`UserResourceTest > can update user campaigns`) surfaced once during a mixed-filter run and passed cleanly both in isolation and in the full `Voter|Leader|Coordinator|User` regression sweep (785 passed, 0 failures) — confirmed unrelated to this plan's changes, not fixed (out of scope, already tracked in STATE.md).

## Verification Results

```
php artisan test --filter=LoginWithoutEmailTest                                          → 2 passed (7 assertions)
php artisan test --filter=RequireEmailOrDocumentNumberTest                                → 12 passed (64 assertions)
php artisan test --filter=RequireEmailOrDocumentNumberLivewireTest                        → 6 passed (31 assertions)
php artisan test tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php        → 7 passed (31 assertions)
php artisan test --filter="LeaderResourceCampaignTest|CoordinatorResourceCampaignTest|AreaCoordinatorResourceCampaignTest" → 15 passed (67 assertions)
php artisan test --filter="PublicLeaderRegistrationTest|CreateLeaderIdentityLookupTest"   → 12 passed (43 assertions)
vendor/bin/pint --dirty                                                                   → PASS, 0 files needing changes
php artisan test --filter="Voter|Leader|Coordinator|User"                                 → 785 passed (2272 assertions), no regressions
php artisan migrate:rollback --step=1 && php artisan migrate                              → clean round-trip, no errors
```

## Known Stubs

None. Every field touched by this plan (email, document_number) is real, persisted, form-bound data — no placeholders or hardcoded empty values feeding the UI.

## Pending Manual Verification

Per the user's standing preference (browser-verify before considering UI changes deployed), the following has NOT yet been done in this session and is recommended before this is considered fully verified in production:

- Create a líder from the public self-registration link (`public.register-leader`) with only a cédula, no correo, completing the real SMS OTP flow, and confirm the account is created and can log in with the cédula.
- Create a líder from the coordinator panel (`coordinator.create-leader`) with only a cédula, and separately with only a correo, confirming both succeed and the "at least one" helper text/labels render correctly without the `*` markers.
- Edit an existing líder (`coordinator.edit-leader`) that has a cédula on file, clearing the correo field, and confirm the save succeeds and no crash occurs (this is the scenario the Task 3 mount() fix specifically protects against).
- Create a Coordinador, Articulador, and admin Usuario from their respective Filament panels with only a cédula (no correo) each, confirming the helper text appears and the record saves.
- Log in as a user with no correo, using their cédula in the login form's "correo" field, confirming the existing dual-login path still redirects correctly by role.

This joins the other pending `checkpoint:human-verify` items already tracked in STATE.md's Blockers/Concerns section (this quick task did not have an explicit checkpoint task in its PLAN.md — it was fully autonomous — but the project's standing feedback preference still applies).

## Self-Check: PASSED

All claimed files verified to exist on disk; all claimed commit hashes verified present in git log.
