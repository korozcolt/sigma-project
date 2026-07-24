# Deferred Items — Phase 04.1

Items discovered during execution that are out of this phase's scope (per executor scope-boundary rule) and were NOT fixed here.

## `Tests\Feature\Filament\UserResourceTest > can update user campaigns` — intermittent flake

- **Found during:** Plan 04.1-05, Task 2 (full-suite regression gate)
- **Symptom:** Fails roughly 1 in 3 full-suite (`composer test`) runs with a report of a mismatched campaign assignment assertion; passes reliably every time when run in isolation (`php artisan test --filter="can update user campaig"`).
- **Why out of scope:** `UserResourceTest` and `UserResource` are not part of this phase's file set (no plan in 04.1 touches `app/Filament/Resources/Users/*` or `UserResourceTest.php`). The flake reproduces identically on repeated `composer test` runs regardless of whether this phase's widget changes are present, indicating a pre-existing test-order/state-leak issue unrelated to the 6 new report widgets or the D-01 TopLeadersTable fix.
- **Evidence:** 3 consecutive `composer test` runs during this plan: 829 passed / 828 passed+1 failed / 829 passed (829 = 828 pre-phase baseline + 1 new widget-wiring test added in this plan's Task 1). The only failure was this test; all 829 (or 828) other tests were green across all 3 runs.
- **Recommendation:** Investigate in a future hardening pass — likely a shared-state or seeded-role leak between test cases in the `Feature` suite ordering. Not blocking for `/gsd:verify-work` since the full suite is green in the majority of runs and the failure is unrelated to this phase's surface.
