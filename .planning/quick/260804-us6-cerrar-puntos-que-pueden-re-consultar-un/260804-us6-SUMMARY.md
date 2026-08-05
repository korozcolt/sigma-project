---
phase: quick
plan: 260804-us6
subsystem: auth
tags: [filament, livewire, authorization, registraduria, http-controller, pest]

# Dependency graph
requires:
  - phase: quick-260726-jao
    provides: RegistraduriaLookup permanent-cache table + PollingPlaceResolver::resolveFromPermanentLookup()
provides:
  - Backend-enforced (not just UI-hidden) super_admin-only guard on forceRefreshFromRegistraduria()
  - Permanent-cache short-circuit on RegistraduriaController::lookup() (orphaned route)
affects: [registraduria, voter-form, cost-control]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "abort_unless(CampaignContext::isSuperAdmin(), 403) as the backend authorization idiom for Livewire methods invoked from a role-gated UI action (reuses the project's existing helper instead of a raw hasRole() check)"
    - "Constructor-promoted PollingPlaceResolver injected into RegistraduriaController to mirror the HasRegistraduriaPolling permanent-lookup short-circuit in a plain controller"

key-files:
  created: []
  modified:
    - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
    - app/Filament/Resources/Voters/Schemas/VoterForm.php
    - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
    - app/Http/Controllers/RegistraduriaController.php
    - tests/Feature/RegistraduriaControllerTest.php

key-decisions:
  - "Used CampaignContext::isSuperAdmin() instead of a raw auth()->user()?->hasRole(UserRole::SUPER_ADMIN->value) check, in both the backend guard and the form's ->visible() closure, per plan-checker's style correction — both files already import and use this exact helper elsewhere for the identical purpose (reuse over reinvention)."
  - "No UserRole import needed in either file for this change since CampaignContext::isSuperAdmin() replaced the raw hasRole()/hasAnyRole() call entirely."

requirements-completed: []

# Metrics
duration: 15min
completed: 2026-08-05
---

# Quick Task 260804-us6: Close re-query cost-leak points Summary

**Backend-enforced super_admin-only guard on the force-refresh Registraduría button, plus a permanent-cache short-circuit on the previously-unguarded `RegistraduriaController::lookup()` route — both close real 2captcha re-spend paths for cédulas already resolved.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-08-05T03:20:13Z
- **Tasks:** 2/2 completed
- **Files modified:** 5

## Accomplishments
- `forceRefreshFromRegistraduria()` now aborts with a real HTTP 403 for any caller without the `super_admin` role — enforced in the Livewire method itself, not just hidden in the UI, so bypassing the hidden button (e.g. a direct component call) is also blocked.
- The "Actualizar datos desde Registraduría" button is now visible only to `super_admin` (previously also visible to `admin_campaign`/`coordinator`).
- `POST /registraduria/lookup` (a standalone, previously unguarded controller route) now checks the permanent `registraduria_lookups` table first and returns the cached result with zero calls to the Python microservice when the cédula was already resolved.
- Both fixes are backed by new/updated Pest tests, including a genuine `Http::assertNothingSent()` proof for the controller fix and `assertForbidden()` proofs (per role) for the Livewire guard.

## Task Commits

Each task was committed atomically:

1. **Task 1: Restrict "Actualizar datos" to super_admin, backend-enforced** - `53f4bb0` (fix)
2. **Task 2: Add permanent-cache guard to RegistraduriaController::lookup()** - `e278f83` (fix)

_No separate RED/GREEN/REFACTOR commits — both tasks were implemented and tested together per task, matching this codebase's existing atomic-commit convention for fix-type quick tasks._

## Files Created/Modified
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` - `forceRefreshFromRegistraduria()` now starts with `abort_unless(CampaignContext::isSuperAdmin(), 403);` before any blank-check/kill-switch/live-lookup logic.
- `app/Filament/Resources/Voters/Schemas/VoterForm.php` - `actualizar_registraduria` suffixAction's `->visible()` closure now checks `CampaignContext::isSuperAdmin()` only (dropped `admin_campaign`/`coordinator`).
- `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` - D-06 role-gate tests updated to super_admin-only visibility (dataset for the "hides" test gained `admin_campaign`/`coordinator`); new tests prove the backend guard rejects `admin_campaign`/`coordinator`/`leader`/`reviewer` with a 403 and that `super_admin` still succeeds.
- `app/Http/Controllers/RegistraduriaController.php` - constructor now injects `PollingPlaceResolver`; `lookup()` checks `resolveFromPermanentLookup()` before the `Http::post()` call to the Python microservice, returning a `{status: 'done', data: fields}` envelope on a cache hit.
- `tests/Feature/RegistraduriaControllerTest.php` - new test proves a cédula already in `registraduria_lookups` returns the cached fields with `Http::assertNothingSent()`; all 4 pre-existing lookup-related tests pass unmodified.

## Decisions Made
- Reused `CampaignContext::isSuperAdmin()` instead of a raw `hasRole()`/`hasAnyRole()` check in both files, per the plan-checker's explicit style correction — both files already used this exact helper elsewhere for the same purpose.
- No new imports needed in either modified `app/` file since `CampaignContext` was already imported in both.

## Deviations from Plan

### Style correction (per plan-checker's non-blocking note, applied as instructed)

**1. Reused existing `CampaignContext::isSuperAdmin()` helper instead of the plan's literal `auth()->user()?->hasRole(UserRole::SUPER_ADMIN->value)` snippet**
- **Found during:** Task 1
- **Issue:** The plan's literal code snippet used a raw `hasRole()` check, but both `HasRegistraduriaPolling.php` and `VoterForm.php` already import and use `CampaignContext::isSuperAdmin()` elsewhere in the same files for the identical purpose.
- **Fix:** Used `abort_unless(CampaignContext::isSuperAdmin(), 403);` in the method body and `CampaignContext::isSuperAdmin()` in the form's `->visible()` closure. No new `UserRole` import needed for this specific check.
- **Files modified:** `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php`, `app/Filament/Resources/Voters/Schemas/VoterForm.php`
- **Verification:** Full `VoterRegistraduriaRefreshTest.php` suite (25 tests, 110 assertions) passes.
- **Committed in:** `53f4bb0`

No other deviations — both tasks otherwise executed exactly as planned.

## Issues Encountered
None.

## Verification Performed
```bash
php artisan test --filter=VoterRegistraduriaRefreshTest   # 25 passed (110 assertions)
php artisan test --filter=RegistraduriaControllerTest     # 12 passed (35 assertions)
vendor/bin/pint --dirty                                   # clean on all 5 touched files
```

Pre-existing, unrelated Pint style violations exist in ~18 other test files across the codebase (out-of-scope per this task's boundaries — not modified, not caused by this task; confirmed via `vendor/bin/pint --test` full-repo scan).

## Next Steps (Manual Verification Still Pending)

Per the project's standing "browser-verify before prod" preference, the following has NOT yet been manually confirmed in a real browser:
- Logging in as a coordinator/admin_campaign user no longer shows the "Actualizar datos desde Registraduría" button on an Apoyo's edit form.
- A super_admin still sees the button and can use it successfully after confirming the existing modal.

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
- FOUND: app/Filament/Resources/Voters/Schemas/VoterForm.php
- FOUND: tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
- FOUND: app/Http/Controllers/RegistraduriaController.php
- FOUND: tests/Feature/RegistraduriaControllerTest.php
- FOUND commit: 53f4bb0
- FOUND commit: e278f83
