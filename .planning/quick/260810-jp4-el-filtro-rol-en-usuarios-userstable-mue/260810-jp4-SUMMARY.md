---
phase: quick-260810-jp4
plan: 01
subsystem: filament-users
tags: [filament, table-filter, i18n, spatie-permission]
requires: []
provides:
  - "UsersTable roles SelectFilter renders Spanish UserRole labels"
affects:
  - app/Filament/Resources/Users/Tables/UsersTable.php
tech-stack:
  added: []
  patterns:
    - "SelectFilter::getOptionLabelFromRecordUsing() for relationship-backed table filters (relationship() + options() is dead code once relationship() is set)"
key-files:
  created: []
  modified:
    - app/Filament/Resources/Users/Tables/UsersTable.php
    - tests/Feature/Filament/UserResourceTest.php
decisions:
  - "Placed the new `use Spatie\\Permission\\Models\\Role;` import in true alphabetical order (last, after Filament\\Tables\\Table) rather than the plan's literal 'before Filament\\Actions\\*' instruction, since App < Filament < Spatie alphabetically and CLAUDE.md/Pint mandate alphabetical use-statement order; confirmed via `vendor/bin/pint` passing clean on the file."
metrics:
  duration: "~15m"
  completed: "2026-08-10"
---

# Quick Task 260810-jp4: Fix Rol Filter Spanish Labels Summary

Fixed the "Rol" `SelectFilter` on the Usuarios table (`UsersTable`) so its dropdown options render Spanish `UserRole::getLabel()` strings (e.g. "Articulador", "Administrador de Campaña") instead of raw English Spatie role names (e.g. "area_coordinator", "admin_campaign") — root cause was a Filament v4 dead-code trap where `->relationship()` on a `SelectFilter` makes `getFormField()` ignore any manual `->options()` closure entirely.

## What Was Built

**Task 1 — Fix the filter (`app/Filament/Resources/Users/Tables/UsersTable.php`):**
- Added `use Spatie\Permission\Models\Role;` import.
- Replaced the dead `->options(fn () => collect(UserRole::cases())->mapWithKeys(...))` call on `SelectFilter::make('roles')` with `->getOptionLabelFromRecordUsing(fn (Role $record): string => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)` — the callback Filament's `SelectFilter::getFormField()` actually consults once `->relationship()` is set. `->relationship('roles', 'name')`, `->multiple()`, and `->preload()` were left completely unchanged, so filter values (role primary keys) and query behavior are identical to before.
- Audited for the same `relationship()+options()` dead-code pattern elsewhere via `grep -rn "relationship('roles'" app/Filament/`: the only other hit is `UserForm.php` (a form `Select`, not a table filter — out of scope, form-field `relationship()` resolves labels correctly via the title attribute). Confirmed `CoordinatorsTable.php`, `LeadersTable.php`, and `AreaCoordinatorsTable.php` have no `SelectFilter`/`relationship`/`UserRole` usage at all — nothing else to fix.

**Task 2 — Regression test (`tests/Feature/Filament/UserResourceTest.php`):**
- Added `roles filter displays spanish labels instead of raw role names`, placed immediately after the existing `can filter users by role` test. Pulls the mounted `roles` filter's resolved `Select` field via `Livewire::test(ListUsers::class)->instance()->getTableFiltersForm()->getComponentByStatePath('roles')->getChildSchema()->getFlatFields()['values']->getOptions()` and asserts the `area_coordinator` role ID maps to `'Articulador'` (not the raw `'area_coordinator'` string) and the `admin_campaign` role ID maps to `'Administrador de Campaña'` — covering two roles so the fix isn't accidentally single-role-specific.

## Deviations from Plan

### Auto-fixed Issues

None beyond the noted alphabetical-order adjustment (see Decisions above) — the plan's own literal text for import placement conflicted with true alphabetical order; resolved in favor of the project's stated Pint/alphabetical convention, verified clean.

## Verification

- `php artisan test --filter="roles filter displays spanish labels"` — passes (3 assertions).
- `php artisan test tests/Feature/Filament/UserResourceTest.php` — 28/29 pass; the sole failure (`can update user campaigns`) is the pre-existing, already-documented intermittent `CampaignContext` static-override test-pollution flake (see STATE.md Blockers/Concerns) — re-ran in isolation immediately after (`php artisan test --filter="can update user campaigns"`) and it passed cleanly, confirming this is not a regression from this change.
- `vendor/bin/pint app/Filament/Resources/Users/Tables/UsersTable.php tests/Feature/Filament/UserResourceTest.php` — both files pass clean, no style violations.

## Worktree Provisioning

This worktree (`agent-a4fdae389dd0214e0`) was stale at session start — one fast-forward behind `main`, missing this quick task's own PLAN.md, `vendor/`, and `.env` — the same recurring staleness class documented repeatedly in STATE.md. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`. STATE.md updated by hand-editing this worktree's own copy directly (not re-verified via `gsd-tools` this session, per the established `findProjectRoot()` worktree-redirection precedent — ROADMAP.md intentionally not touched, per this quick task's explicit constraints).

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Users/Tables/UsersTable.php (getOptionLabelFromRecordUsing present)
- FOUND: tests/Feature/Filament/UserResourceTest.php (new test present)
- FOUND commit 819c80b (Task 1)
- FOUND commit 7f75170 (Task 2)
