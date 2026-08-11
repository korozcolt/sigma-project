---
phase: 16-metadata-catalog-ui-assignment
plan: 04
subsystem: ui
tags: [filament, bulk-action, metadata, livewire-testing]

# Dependency graph
requires:
  - phase: 16-01
    provides: "MetadataAssignmentService + App\\Filament\\Schemas\\MetadataAssignment (bulkAction() builder, modal schema, service-backed assignMany())"
provides:
  - "Asignar metadata bulk action registered on CoordinatorsTable, LeadersTable, AreaCoordinatorsTable and UsersTable"
  - "UsersTable modernised from deprecated ->bulkActions() to ->toolbarActions()"
  - "Pest coverage proving bulk assignment through the real Filament table path on all four tables"
affects: [16-06, 17-filter-sort-export]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "MetadataAssignment::bulkAction() consumed as-is inside each table's existing BulkActionGroup, metadata action first / delete last"

key-files:
  created:
    - tests/Feature/Metadata/FilamentMetadataBulkActionTest.php
  modified:
    - app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php
    - app/Filament/Resources/Leaders/Tables/LeadersTable.php
    - app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php
    - app/Filament/Resources/Users/Tables/UsersTable.php

key-decisions:
  - "MetadataAssignment::bulkAction() placed first inside each BulkActionGroup so DeleteBulkAction stays last/destructive-at-the-bottom"
  - "UsersTable's deprecated ->bulkActions() replaced with ->toolbarActions() in the same edit, with zero changes to columns/filters/defaultSort/session-persistence calls"
  - "META-04 NOT marked complete — this plan only covers the admin-panel half; plan 16-06's Volt bulk UI for coordinador/articulador panels owes the rest"
  - "Livewire::test(...) used instead of the plan's literal livewire(...) helper — no pest-plugin-livewire dependency exists in this repo; Livewire::test() exposes the identical callTableBulkAction/assertTableBulkActionVisible testing macros"

patterns-established:
  - "Bulk-action Pest coverage pattern: create N direct-role fixtures with explicit document_number/phone, call Livewire::test(ListXxx::class)->callTableBulkAction('assignMetadata', $records, [...]), assert UserMetadataValue rows by user_id/value/assigned_by"

requirements-completed: []

# Metrics
duration: ~20min
completed: 2026-08-10
---

# Phase 16 Plan 04: Metadata Bulk Assignment on Admin Tables Summary

**Wired the shared `MetadataAssignment::bulkAction()` (built in 16-01) into all four admin Filament tables and modernised `UsersTable` off the deprecated `->bulkActions()` API, with 6 passing Pest tests exercising the real table path end to end.**

## Performance

- **Duration:** ~20 min (including worktree bootstrap)
- **Completed:** 2026-08-10T22:41:06-05:00
- **Tasks:** 2
- **Files modified:** 4 (tables) + 1 (test file created)

## Accomplishments
- `Asignar metadata` bulk action now appears in the bulk-action group of `CoordinatorsTable`, `LeadersTable`, `AreaCoordinatorsTable`, and `UsersTable`
- `UsersTable` no longer calls the deprecated v3 `->bulkActions()` — it now uses `->toolbarActions()` like the other three tables, with no other behavior change
- 6 Pest tests prove bulk assignment writes one correctly attributed `user_metadata_values` row per selected user across all four tables, including an append-only/id-tiebreak regression guard across two runs

## Task Commits

Each task was committed atomically:

1. **Task 1: Register the metadata bulk action on all four admin tables** - `cee9c03` (feat)
2. **Task 2: Pest coverage for bulk assignment across the four tables** - `6b6696a` (test)

**Plan metadata:** (this commit, pending)

## Files Created/Modified
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` - added `MetadataAssignment::bulkAction()` to the existing `BulkActionGroup`
- `app/Filament/Resources/Leaders/Tables/LeadersTable.php` - same addition
- `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php` - same addition
- `app/Filament/Resources/Users/Tables/UsersTable.php` - same addition, plus `->bulkActions()` → `->toolbarActions()` rename
- `tests/Feature/Metadata/FilamentMetadataBulkActionTest.php` - new Pest suite, 6 tests, 39 assertions

## Decisions Made
- Metadata bulk action placed first in each `BulkActionGroup` (delete stays last/destructive-at-the-bottom) — matches the plan's explicit instruction, no independent judgment call needed.
- `UsersTable`'s rename to `->toolbarActions()` was scoped to exactly that one method call; columns, filters, `defaultSort`, and the three `persist*InSession()` calls were left untouched and verified unchanged via `grep -c "persistColumnSearchesInSession"` returning `1`.
- META-04 left `Pending` in REQUIREMENTS.md — the phase's own objective states this plan is "the admin panel half of bulk assignment," with 16-06 delivering the Volt/Flux hand-rolled bulk UI for the coordinador/articulador panels. Marking META-04 `Done` here would be premature per the project's established split-requirement precedent (see Phase 10/13/14/15 records in STATE.md).
- Used `Livewire\Livewire::test(...)` (not the plan's literal `livewire(...)` helper syntax) for all six tests — no `pestphp/pest-plugin-livewire` dependency exists in this repo, and no global `livewire()` testing function is defined anywhere in `vendor/livewire/*`. `Livewire::test()` returns the same `Testable` instance with the same `callTableBulkAction`/`assertTableBulkActionVisible` macros mixed in (`vendor/filament/tables/src/Testing/TestsBulkActions.php`), so behavior is identical; this matches the exact convention already used by `MetadataKeyResourceTest.php` and `CoordinatorResourceCampaignTest.php` in this same test suite.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Plan's `livewire(...)` test helper does not exist in this repo**
- **Found during:** Task 2 (writing the Pest suite)
- **Issue:** The plan's `<interfaces>` block and `<action>` both write test calls as `livewire(ListCoordinators::class)->...`, implying a global `livewire()` helper function. No such function is registered anywhere in `vendor/livewire/livewire`, `vendor/livewire/flux`, or via a `pestphp/pest-plugin-livewire` dependency (confirmed absent from `composer.json`).
- **Fix:** Used `Livewire\Livewire::test(ListCoordinators::class)->...` instead — the standard Livewire testing entry point already used throughout this test suite. All bulk-action testing macros (`callTableBulkAction`, `assertTableBulkActionVisible`) are identical regardless of entry-point syntax, since they're mixed into the same `Testable` object.
- **Files modified:** tests/Feature/Metadata/FilamentMetadataBulkActionTest.php
- **Verification:** `php artisan test --filter=FilamentMetadataBulkActionTest` → 6 passed, 39 assertions
- **Committed in:** `6b6696a` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** No scope creep — the fix is a test-syntax substitution with identical runtime behavior, not a functional change.

## Issues Encountered

Worktree (`agent-a3ea9c85e3017e94b`) was stale at session start — missing the entire Phase 16 planning corpus (`16-CONTEXT.md`, `16-RESEARCH.md`, all six `16-*-PLAN.md` files) plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`. No `npm install`/`npm run build` was needed — this plan's scope is pure PHP (Filament tables + Pest), no frontend asset changes. `gsd-tools init execute-phase 16` confirmed the recurring `findProjectRoot()` worktree-redirection bug (`project_root` resolved to the main checkout, not this worktree) — STATE.md/ROADMAP.md were updated by hand-editing this worktree's own copies directly instead of via the CLI.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The admin-panel half of META-04 is done: all four admin tables (Coordinadores, Líderes, Articuladores, Usuarios) expose `Asignar metadata` as a bulk action, backed by the same `MetadataAssignmentService::assignMany()` write path used everywhere else in this phase.
- `UsersTable` is now fully on the modern Filament v4 `toolbarActions()` API — no remaining `->bulkActions()` call anywhere under `app/Filament/Resources/Users`.
- Plan 16-06 (Volt bulk-selection UI for coordinador/articulador panels) is the only remaining piece of META-04's full requirement — it depends on `MetadataAssignmentService` (16-01) only, not on this plan's table edits, so no blocker exists for it to proceed independently.
- Phase 17 (Filter/Sort/Export) can rely on `user_metadata_values` rows now being reachable via bulk writes from every admin-panel surface, in addition to the individual-assignment surface from 16-03.

---
*Phase: 16-metadata-catalog-ui-assignment*
*Completed: 2026-08-10*

## Self-Check: PASSED

All 6 created/modified files confirmed present on disk; both task commits (`cee9c03`, `6b6696a`) confirmed present in git history.
