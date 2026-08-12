---
phase: 17-filter-sort-export-surfaces
plan: 01
subsystem: database
tags: [filament, eloquent, sqlite, correlated-subquery, metadata]

# Dependency graph
requires:
  - phase: 16-metadata-catalog-ui-assignment
    provides: MetadataKey/UserMetadataValue schema, MetadataAssignmentService (activeKeys/activeKeyOptions/findActiveKey/currentValues single-user PHP-side pattern), MetadataAssignment.php per-type field-dispatch pattern
provides:
  - "MetadataAssignmentService::withCurrentValueSelects(Builder $query, ?Collection $keys = null): Builder — one addSelect correlated subquery per active metadata key, CAST(value AS DECIMAL(20,4)) for numeric-typed keys so sortable() sorts numerically"
  - "MetadataAssignmentService::applyMetadataFilter(Builder $query, MetadataKey $key, string $value): Builder — exact-match current-value filter excluding stale/superseded rows"
  - "MetadataTableColumns::make(): array<int, TextColumn> — one toggleable(hidden-by-default)+sortable TextColumn per active metadata key"
  - "MetadataTableFilter::make(): Filter — D-01 cascading key->value filter with per-type value field, exact-match query"
affects: [17-02-wave2-table-wiring, 17-03-wave2-export-wiring]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Correlated addSelect subquery for 'current value per row' at SQL scale (whereColumn + orderByDesc(assigned_at)/orderByDesc(id) + limit(1)), reused identically for table columns/sort and exports"
    - "Subquery-where() equality filter (Laravel's native subquery comparison overload) for exact current-value matching, independent of the addSelect numeric-cast alias"

key-files:
  created:
    - app/Filament/Schemas/MetadataTableColumns.php
    - app/Filament/Schemas/MetadataTableFilter.php
    - tests/Feature/Metadata/MetadataAssignmentServiceQueryTest.php
    - tests/Feature/Metadata/MetadataTableSchemasTest.php
  modified:
    - app/Services/MetadataAssignmentService.php

key-decisions:
  - "withCurrentValueSelects() derives the base table name from $query->getModel()->getTable() rather than hardcoding 'users', keeping it reusable if called against a non-User query in the future"
  - "applyMetadataFilter() intentionally re-derives its own plain-value subquery rather than reusing withCurrentValueSelects()'s CAST-ed alias, preserving D-03's exact string-equality contract for all four metadata types"
  - "MetadataTableFilter's value fields are NOT ->required() (unlike MetadataAssignment::modalSchema()) — a blank filter value means 'no filter applied', not a validation error"

patterns-established:
  - "Pattern 1 (query-scale current-value resolution) is the single mechanism both Wave 2 table-wiring and export-wiring plans must call — no per-plan re-derivation of the append-only-latest-row SQL"

requirements-completed: [FILT-01, FILT-02]

# Metrics
duration: ~30min
completed: 2026-08-12
---

# Phase 17 Plan 01: Query-Scale Metadata Resolution + Filament Schema Builders Summary

**Correlated-subquery `addSelect`/`where` mechanism resolving each user's current metadata value at SQL scale (numeric-cast for correct numeric sort), plus two reusable Filament schema-builder classes (`MetadataTableColumns`, `MetadataTableFilter`) ready for Wave 2 table/export wiring.**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-08-12T02:30:00Z (approx.)
- **Completed:** 2026-08-12T03:01:54Z
- **Tasks:** 2 completed
- **Files modified:** 5 (1 modified, 4 created)

## Accomplishments
- `MetadataAssignmentService::withCurrentValueSelects()` attaches one `addSelect` correlated subquery per active metadata key to any Eloquent query, resolving "current value" (latest by `assigned_at` DESC, `id` DESC tiebreak) at the SQL level — numeric-typed keys are `CAST` to `DECIMAL(20,4)` so `->sortable()` produces true numeric order (10 > 2), not lexicographic order.
- `MetadataAssignmentService::applyMetadataFilter()` filters a query to only rows whose *current* value for a given key exactly equals the given string, correctly excluding stale/superseded historical rows for the same key.
- `MetadataTableColumns::make()` and `MetadataTableFilter::make()` exist as ready-to-wire Filament schema builders — Wave 2 plans can consume both without further design work.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add query-scale current-value resolution to MetadataAssignmentService** - `b4ab5d4` (feat, TDD RED→GREEN in one commit per task convention)
2. **Task 2: Build MetadataTableColumns and MetadataTableFilter Filament schema classes** - `a9eab5b` (feat, TDD RED→GREEN in one commit per task convention)

_Note: both tasks are `tdd="true"`; RED state was confirmed via a failing test run before implementation, then GREEN confirmed before committing — each task's test file and implementation were committed together as a single feat commit per this project's established single-commit-per-task convention (no separate `test(...)` commit was requested by the plan)._

## Files Created/Modified
- `app/Services/MetadataAssignmentService.php` - Added `use Illuminate\Support\Facades\DB;` import plus `withCurrentValueSelects()` and `applyMetadataFilter()` methods (existing methods/imports untouched)
- `app/Filament/Schemas/MetadataTableColumns.php` - New: dynamic per-active-key `TextColumn[]` builder
- `app/Filament/Schemas/MetadataTableFilter.php` - New: D-01 cascading `Filter::make('metadata')` with per-type value field dispatch mirroring `MetadataAssignment::modalSchema()`
- `tests/Feature/Metadata/MetadataAssignmentServiceQueryTest.php` - New: 4 tests (numeric sort, id-tiebreak, null-when-unassigned, stale-value exclusion)
- `tests/Feature/Metadata/MetadataTableSchemasTest.php` - New: 2 tests (column shape/naming, filter naming)

## Decisions Made
- `applyMetadataFilter()` deliberately does not reuse `withCurrentValueSelects()`'s `CAST`-ed alias — it re-derives its own plain-`value` subquery so filter matching stays exact string equality (D-03) for all four metadata types, not numeric equality for numeric-typed keys.
- `MetadataTableFilter`'s value fields omit `->required()` (unlike the assignment modal's fields) since a blank filter value means "no filter applied," not a validation error — this matches the plan's explicit note and the `blank()` guard in the filter's `query()` closure.
- Table name resolution uses `$query->getModel()->getTable()` in both new service methods rather than hardcoding `'users'`, per the plan's explicit reusability note.

## Deviations from Plan

None — plan executed exactly as written. Both tasks' action code, test behavior specs, and acceptance criteria were followed verbatim.

## Issues Encountered
- This worktree (`agent-a60ff45ac8585c40b`) was stale at session start — missing Phases 16 and 17 entirely (including this plan's own PLAN.md), plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established project workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, copied `.env` from the main checkout, `composer install`, `npm install && npm run build` (the missing Vite manifest was initially causing 3 spurious failures in the pre-existing `MetadataKeyResourceTest.php` from Phase 16 — confirmed not a regression from this plan's own 6 new tests, which passed both before and after the asset build; all 73 metadata tests pass after building assets).
- `npm install` regenerated `package-lock.json` with only the `name` field changed to the worktree's directory name (`agent-a60ff45ac8585c40b` instead of `sigma-project`) — a cosmetic npm artifact of running install inside a differently-named worktree directory, not a real dependency change. Discarded via `git checkout -- package-lock.json` before committing.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
Wave 2 plans (table wiring across `UsersTable`/`CoordinatorsTable`/`LeadersTable`/`AreaCoordinatorsTable`, and export wiring across `CoordinatorsExport`/`LeadersExport`/`AnnotatorsExport`/`WitnessesExport`) can now call `MetadataAssignmentService::withCurrentValueSelects()`/`::applyMetadataFilter()` and `MetadataTableColumns::make()`/`MetadataTableFilter::make()` directly — no further design or SQL-pattern work needed. No blockers identified.

---
*Phase: 17-filter-sort-export-surfaces*
*Completed: 2026-08-12*

## Self-Check: PASSED

All 6 created/modified files confirmed present on disk; both task commits (`b4ab5d4`, `a9eab5b`) confirmed present in git history.
