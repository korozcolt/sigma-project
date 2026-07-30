---
phase: quick
plan: 260730-gk3
subsystem: filament-admin
tags: [filament, eloquent, eager-loading, bugfix, production-hotfix]
requires: []
provides:
  - "CallQueueTable no longer 500s when a caller has an assignment with verification calls"
affects:
  - app/Filament/Widgets/CallQueueTable.php
tech-stack:
  added: []
  patterns:
    - "Top-level (non-dot-notation) Eloquent eager-load relation constraint closures must be typed with the actual Relation subclass (e.g. HasMany), never a plain Builder — Laravel passes the real Relation instance, which does not extend Builder"
key-files:
  created:
    - tests/Feature/Filament/CallQueueTableTest.php
  modified:
    - app/Filament/Widgets/CallQueueTable.php
decisions: []
metrics:
  duration: 12min
  completed: 2026-07-30
---

# Quick Task 260730-gk3: Fix TypeError in CallQueueTable Eager-Load Closure Summary

Fixed a live production 500 error caused by a strict PHP type mismatch: `CallQueueTable`'s `verificationCalls` eager-load constraint closure was typed `fn (Builder $query)`, but Laravel always passes the real `Relation` subclass instance (`HasMany`, per `CallAssignment::verificationCalls()`) to top-level relation constraint closures — never a plain `Builder`. `HasMany` does not extend `Builder`, so PHP's strict argument type-check threw a `TypeError` the moment the widget rendered a row (it was previously masked because Eloquent skips eager-loading entirely when the base query returns zero rows, and every prior render happened to have none).

## What Changed

- `app/Filament/Widgets/CallQueueTable.php`: added `use Illuminate\Database\Eloquent\Relations\HasMany;` and re-typed the `verificationCalls` eager-load closure parameter from `Builder $query` to `HasMany $query`. The unrelated `->when($userId, fn (Builder $query) => ...)` closure on the next line (a genuine top-level query `Builder`, not a relation constraint) was left untouched, as directed by the plan.
- `tests/Feature/Filament/CallQueueTableTest.php` (new): two Pest/Livewire tests — (1) renders `CallQueueTable` for a caller with a `CallAssignment` that has an associated `VerificationCall`, asserting no exception and the row is visible (this reproduced the exact production `TypeError` before the fix, confirmed via a RED run); (2) regression guard confirming the zero-assignments empty state still renders without error.

## Verification

- TDD RED: ran the new test suite against the unmodified widget — reproduced the exact production error verbatim: `TypeError: ...{closure}(): Argument #1 ($query) must be of type Illuminate\Database\Eloquent\Builder, Illuminate\Database\Eloquent\Relations\HasMany given`.
- TDD GREEN: applied the type-hint fix — both tests pass (`php artisan test tests/Feature/Filament/CallQueueTableTest.php` → 2 passed, 3 assertions).
- `grep -n 'verificationCalls' app/Filament/Widgets/CallQueueTable.php` confirms the closure now reads `fn (HasMany $query) => $query->latest('call_date')`.
- `vendor/bin/pint --dirty` ran clean (1 file fixed: import ordering in the new test file; app file needed no changes).

## Deviations from Plan

None — plan executed exactly as written.

## Commits

- `ab93f3f` — test(260730-gk3): add failing test reproducing CallQueueTable TypeError
- `b8ee16d` — fix(260730-gk3): type eager-load closure as HasMany, not Builder

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/CallQueueTable.php (HasMany import + typed closure present)
- FOUND: tests/Feature/Filament/CallQueueTableTest.php
- FOUND: commit ab93f3f in git log
- FOUND: commit b8ee16d in git log
