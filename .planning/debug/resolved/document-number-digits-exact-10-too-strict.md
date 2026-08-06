---
status: resolved
trigger: "document-number-digits-exact-10-too-strict"
created: 2026-08-05T00:00:00Z
updated: 2026-08-06T00:00:00Z
---

## Symptoms

expected: A coordinator/leader/public self-registration form should accept any valid Colombian document number (cédula/NUIP, 6-11 digits) and show a Spanish-translated field name in validation errors.
actual: Coordinator reported an urgent, production-blocking bug: creating a líder/apoyo failed with "El campo document number debe tener 10 dígitos" for an 8-digit cédula (a valid, common length), and the error message showed the raw English attribute name "document number" instead of "Número de Documento".
errors: Livewire validation error, `digits:10` rule failure.
reproduction: Enter any document_number with fewer than 10 digits in the coordinator "Agregar Apoyo", leader "Registrar Apoyo", or public voter self-registration forms.
started: Structural — present since these 3 forms were created; not a recent regression.

## Resolution

root_cause: Three independent copies of the same voter/apoyo registration validation (`resources/views/livewire/coordinator/leader-add-voter.blade.php`, `resources/views/livewire/leader/register-voter.blade.php`, `app/Http/Controllers/PublicVoterRegistrationController.php`) used `digits:10` (exactly 10) for `document_number`, when Colombian cédulas/NUIP legitimately range 6-11 digits (NUIP assigned at birth becomes the adult cédula unchanged, and can reach 11 digits). Separately, `lang/es/validation.php`'s `attributes` array had no `document_number` entry, so any validation error on that field fell back to Laravel's auto-humanized English key.
fix: Changed all 3 occurrences from `digits:10` to `digits_between:6,11` for `document_number` (left `phone`/`secondary_phone` at `digits:10`, correct for Colombian cell numbers). Updated the matching `preg_match('/^\d{10}$/', ...)` gates (used to decide when to trigger the automatic Registraduría/census identity lookup while typing) to `/^\d{6,11}$/` in the same 2 Livewire components, so the lookup still fires for valid non-10-digit cédulas. Added `'document_number' => 'Número de Documento'` to `lang/es/validation.php`'s attributes array — a single fix that corrects the label for every form validating this field, not just these 3.
verification: Full Leader + Coordinator Pest suites pass (52 assertions across `RegisterVoterCensusWarningTest`, `RegisterVoterIdentityLookupTest`, `RegisterVoterRegistraduriaLookupTest`, `LeaderAppTest`). `vendor/bin/pint --dirty` clean. Deployed to both instances (commits a2eed4a, then 3bdf95f widening the initial 6-10 range to 6-11 per product-owner correction). Verified end-to-end in a real browser against sigma-betha production: created a temporary coordinator+leader, registered an apoyo with an 8-digit cédula (64545032) — no validation error, correct real-name autofill from the identity directory (confirms the regex gate still triggers the lookup), saved successfully. Test data cleaned up immediately after (0 leftover).
files_changed:
  - lang/es/validation.php
  - resources/views/livewire/coordinator/leader-add-voter.blade.php
  - resources/views/livewire/leader/register-voter.blade.php
  - app/Http/Controllers/PublicVoterRegistrationController.php
