---
phase: 260830-iok-quitar-obligatoriedad-del-correo-para-li
verified: 2026-08-30T00:00:00Z
status: passed
score: 7/7 must-haves verified
---

# Quick Task 260830-iok: Quitar obligatoriedad del correo Verification Report

**Task Goal:** Quitar obligatoriedad del correo para líderes y coordinadores, exigiendo al menos correo o cédula
**Verified:** 2026-08-30
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Coordinador puede crear un líder (Volt) con solo cédula, sin correo | ✓ VERIFIED | `create-leader.blade.php:24,33,194,197` cross-required + null persistence; test `coordinator create-leader saves with only document_number and no email` passes |
| 2 | Líder puede autoregistrarse (Volt público) con solo cédula tras OTP | ✓ VERIFIED | `register-leader.blade.php:24,33,167,170` same pattern; test `public register-leader saves with only document_number and no email` passes (full OTP flow) |
| 3 | Coordinador puede editar líder existente y dejar correo en blanco si el líder ya tiene cédula | ✓ VERIFIED | `edit-leader.blade.php:19,86-94,105` validates against persisted `$this->leader->document_number`; test `coordinator edit-leader saves with a blank email when the leader already has a document_number` passes |
| 4 | Admin/super_admin puede crear Coordinador/Articulador/Usuario (Filament) con solo cédula | ✓ VERIFIED | All 4 Filament schemas (Leader/Coordinator/AreaCoordinator/User forms) have identical cross-required closures; 12 Filament tests pass |
| 5 | Crear/editar sin correo NI cédula falla validación en los 7 puntos (3 Volt + 4 Filament) | ✓ VERIFIED | All 7 files enforce `required_without`/cross-closure; both Filament ("fails validation on both fields") and Livewire ("fails validation on both fields") test groups pass |
| 6 | Usuario sin correo (con cédula) puede iniciar sesión con cédula vía login dual existente | ✓ VERIFIED | `FortifyServiceProvider.php:58-59` unchanged, falls back `email` → `document_number`; `LoginWithoutEmailTest` (2 tests) passes |
| 7 | Formularios de apoyo (Voter) no se tocan | ✓ VERIFIED | Files modified list contains zero Voter-related files; git diff scope confirmed to auth/leader/coordinator/user forms only |

**Score:** 7/7 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php` | `users.email` nullable via `->change()` | ✓ VERIFIED | File matches plan exactly; migration `Ran` per `php artisan migrate:status`; DB column confirmed `nullable => 1` via `Schema::getColumns('users')` |
| `app/Filament/Resources/Leaders/Schemas/LeaderForm.php` | cross-required email/document_number | ✓ VERIFIED | Lines 91-107 exactly as specified in plan |
| `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` | same cross-rule | ✓ VERIFIED | Lines 47-63 match plan |
| `app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php` | same cross-rule | ✓ VERIFIED | Lines 45-61 match plan |
| `app/Filament/Resources/Users/Schemas/UserForm.php` | same cross-rule (maxLength 255 preserved) | ✓ VERIFIED | Lines 64-80 match plan, maxLength(255) preserved per plan's explicit note |
| `resources/views/livewire/public/register-leader.blade.php` | Livewire cross-validation + null persistence | ✓ VERIFIED | Lines 24,33,167,170 match plan |
| `resources/views/livewire/coordinator/create-leader.blade.php` | same pattern | ✓ VERIFIED | Lines 24,33,194,197 match plan |
| `resources/views/livewire/coordinator/edit-leader.blade.php` | email optional validated against persisted document_number | ✓ VERIFIED | Lines 19,45,86-94,105 match plan, including the mount() null-safety fix (`$leader->email ?? ''`) documented as a deviation |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `TextInput::make('email')->required(fn (Get $get) => blank($get('document_number')))` | `TextInput::make('document_number')->required(fn (Get $get) => blank($get('email')))` | closures cruzadas, 4 Filament schemas | ✓ WIRED | Confirmed present verbatim in all 4 schema files |
| `#[Validate('nullable|email|...|required_without:document_number')]` | `#[Validate('nullable|string|...|required_without:email')]` | Livewire cross-validation | ✓ WIRED | Confirmed in register-leader.blade.php and create-leader.blade.php |
| `User::create([...])` / `$this->leader->update([...])` | `blank($this->email) ? null : $this->email` | explicit blank-to-null conversion | ✓ WIRED | Confirmed in all 3 Volt save() methods for both email and document_number |
| `FortifyServiceProvider::configureAuthentication()` | `User::where('document_number', $login)->first()` | pre-existing fallback, untouched | ✓ WIRED | Confirmed unchanged at lines 58-59; covered by new `LoginWithoutEmailTest` |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Login-without-email tests | `php artisan test --filter=LoginWithoutEmailTest` | 2 passed (7 assertions) | ✓ PASS |
| Filament cross-required rule (4 resources x 3 scenarios) | `php artisan test --filter=RequireEmailOrDocumentNumberTest` | 12 passed (64 assertions) | ✓ PASS |
| Livewire cross-required rule (3 components) | `php artisan test --filter=RequireEmailOrDocumentNumberLivewireTest` | 6 passed (31 assertions) | ✓ PASS |
| Regression: Leader/Coordinator/AreaCoordinator campaign tests + public registration + registraduria lookup | `php artisan test --filter="LeaderResourceCampaignTest\|CoordinatorResourceCampaignTest\|AreaCoordinatorResourceCampaignTest\|PublicLeaderRegistrationTest\|CreateLeaderIdentityLookupTest\|CreateLeaderRegistraduriaLookupTest"` | 34 passed (141 assertions), 0 failures | ✓ PASS |
| Code style | `vendor/bin/pint --dirty --test` | 0 files needing changes | ✓ PASS |
| DB schema | `Schema::getColumns('users')` inspected via tinker | `email` column: `nullable => 1` | ✓ PASS |
| Migration applied | `php artisan migrate:status` | `2026_08_30_120000_make_email_nullable_on_users_table` → `Ran` (batch 10) | ✓ PASS |

### Requirements Coverage

No formal REQUIREMENTS.md IDs were declared for this quick task (`requirements: []` in PLAN frontmatter) — not applicable.

### Anti-Patterns Found

None. Grep for `TODO|FIXME|XXX|HACK|placeholder|not implemented` across all 7 modified core files returned only legitimate UI `placeholder="..."` input attributes and an unrelated pre-existing `Filament\Forms\Components\Placeholder` import/usage — no stub code, no empty implementations, no hardcoded empty values feeding rendering.

### Human Verification Required

None strictly required to confirm goal achievement — all behaviors are covered by automated Pest tests that exercise the real validation/persistence paths (including a full OTP flow for the public registration form). Per the project's standing "browser-verify before prod" preference, the executor's own SUMMARY.md already lists recommended manual browser checks (creating líderes/coordinadores/articuladores/usuarios with only cédula from each surface, editing a líder with a blank correo, and logging in with a cédula) — these remain good practice before considering this deployed to production, but are not blockers to this quick task's own completion since the task was fully autonomous with no explicit checkpoint.

### Gaps Summary

No gaps found. All 7 must-have truths verified against the live codebase (not just SUMMARY claims): the migration is applied and the `users.email` column is confirmed nullable in the actual database; all 4 Filament schemas and all 3 Livewire Volt components contain the exact cross-required validation and blank-to-NULL persistence logic described in the plan; the pre-existing dual-login fallback in `FortifyServiceProvider` is untouched and now covered by a passing regression test; Voter/apoyo forms were not touched. All 20 new tests and 34 regression tests pass, and `pint` reports no style violations.

---

_Verified: 2026-08-30_
_Verifier: Claude (gsd-verifier)_
