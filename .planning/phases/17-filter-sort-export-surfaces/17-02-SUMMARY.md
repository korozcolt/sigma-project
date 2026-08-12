---
phase: 17-filter-sort-export-surfaces
plan: 02
subsystem: ui
tags: [filament, livewire, tables, filter, sort, metadata]

# Dependency graph
requires:
  - phase: 17-filter-sort-export-surfaces (plan 01)
    provides: "MetadataAssignmentService::withCurrentValueSelects()/applyMetadataFilter(), MetadataTableColumns::make(), MetadataTableFilter::make()"
provides:
  - "UsersTable, CoordinatorsTable, LeadersTable, AreaCoordinatorsTable all call ->modifyQueryUsing(withCurrentValueSelects()), append MetadataTableColumns::make() to ->columns(), and expose MetadataTableFilter::make() via ->filters()"
  - "A Livewire feature test (MetadataTableFilterAndSortTest) proving exact-value filtering, filter+column existence, numeric-correct sort, and current-value rendering across all four tables"
affects: [17-03 (export wiring, same tables)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament table query augmentation via ->modifyQueryUsing() + correlated addSelect subqueries for per-key metadata columns"

key-files:
  created:
    - tests/Feature/Filament/MetadataTableFilterAndSortTest.php
  modified:
    - app/Filament/Resources/Users/Tables/UsersTable.php
    - app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php
    - app/Filament/Resources/Leaders/Tables/LeadersTable.php
    - app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php

key-decisions:
  - "Test fixture bug fix: assertTableColumnStateSet() must receive the record's primary key, not the in-memory Model instance, to re-resolve state through the table's ->modifyQueryUsing()-augmented query (matches the Phase 14 Plan 01 precedent already documented in STATE.md)."

patterns-established: []

requirements-completed: [FILT-01, FILT-02]

# Metrics
duration: 20min
completed: 2026-08-12
---

# Phase 17 Plan 02: Wire Metadata Filter/Sort into Admin Tables Summary

**All four Filament admin tables (Usuarios, Coordinadores, Líderes, Articuladores) now filter and sort by assigned metadata values, with numeric-typed keys sorting numerically instead of lexicographically, proven by 10 passing Livewire feature tests.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-08-12T03:09Z (approx, worktree setup included)
- **Completed:** 2026-08-12T03:29Z
- **Tasks:** 3
- **Files modified:** 4 (tables) + 1 (new test file)

## Accomplishments
- `UsersTable`, `CoordinatorsTable`, `LeadersTable`, `AreaCoordinatorsTable` all call `->modifyQueryUsing(fn (Builder $query) => app(MetadataAssignmentService::class)->withCurrentValueSelects($query))`, attaching one correlated `metadata_{key_id}` select per active metadata key to the base query.
- All four tables append `...MetadataTableColumns::make()` to their `->columns([...])` array (hidden-by-default, toggleable, sortable).
- All four tables expose `MetadataTableFilter::make()` via `->filters([...])` — appended to `UsersTable`'s pre-existing filters array; a brand-new `->filters([...])` call added to `CoordinatorsTable`, `LeadersTable`, and `AreaCoordinatorsTable` (their first ever).
- New `tests/Feature/Filament/MetadataTableFilterAndSortTest.php` (10 tests, 23 assertions) proves: exact-value metadata filtering works on all 4 tables (dataset-driven), the metadata filter+column are exposed on all 4 tables (dataset-driven), a numeric-typed key sorts `2` before `10` (not lexicographically), and the rendered column always shows the current (latest), not stale, assigned value.
- Full regression pass across 9 related existing test files (61 tests, 263 assertions) confirms zero existing columns, filters, or bulk actions were removed — only appended to.

## Task Commits

Each task was committed atomically:

1. **Task 1: Wire metadata filter + columns into UsersTable and CoordinatorsTable** - `a23ca3a` (feat)
2. **Task 2: Wire metadata filter + columns into LeadersTable and AreaCoordinatorsTable** - `5a8cd90` (feat)
3. **Task 3: Prove filter + numeric sort work end to end via Livewire table tests** - `7857ece` (test)

**Plan metadata:** (this commit) `docs(17-02): complete plan`

## Files Created/Modified
- `app/Filament/Resources/Users/Tables/UsersTable.php` - `modifyQueryUsing`, `MetadataTableColumns::make()` appended to columns, `MetadataTableFilter::make()` appended to existing filters
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` - `modifyQueryUsing`, `MetadataTableColumns::make()` appended, first `->filters([...])` call added with `MetadataTableFilter::make()`
- `app/Filament/Resources/Leaders/Tables/LeadersTable.php` - same pattern as Coordinators
- `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php` - same pattern as Coordinators
- `tests/Feature/Filament/MetadataTableFilterAndSortTest.php` - new Livewire feature test proving FILT-01/FILT-02 end to end across all four tables

## Decisions Made
- [Rule 1 - Bug] The plan's own literal test code for "renders the current (latest) metadata value" passed the in-memory `$coordinador` Model instance directly to `assertTableColumnStateSet()`. Filament's `TestsColumns::assertTableColumnStateSet()` only re-resolves a record through the table's own (query-augmented) source when given a record key — passing a Model instance short-circuits that and uses the plain unaugmented model, which has no `metadata_{id}` attribute (returns null). Fixed by passing `$coordinador->getKey()` instead of `$coordinador`. This matches an already-documented precedent in `.planning/STATE.md` (Phase 14 Plan 01 decision) for the identical Filament testing-harness behavior.
- Worktree (`agent-ab5abe09224a52926`) was stale at session start — missing Phases 16 and 17 entirely (including this plan's own `17-02-PLAN.md`), plus `vendor/`, `.env`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`. No `npm run build` was needed — this plan is pure PHP/Filament/Pest, no frontend asset changes.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed test's `assertTableColumnStateSet()` call passing a Model instance instead of its key**
- **Found during:** Task 3 (Livewire table tests)
- **Issue:** The plan's literal test code passed `$coordinador` (a Model instance) as the third argument, which caused Filament's testing harness to bypass the table's `->modifyQueryUsing()`-augmented query and use the plain factory-created model, which never had the `metadata_{id}` select attribute populated on it — the assertion failed with `null` instead of `'valor-actual'`.
- **Fix:** Changed the call to pass `$coordinador->getKey()` instead of `$coordinador`.
- **Files modified:** `tests/Feature/Filament/MetadataTableFilterAndSortTest.php`
- **Verification:** `php artisan test tests/Feature/Filament/MetadataTableFilterAndSortTest.php` — all 10 tests pass (23 assertions).
- **Committed in:** `7857ece` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Test-only fix, no production code touched. No scope creep.

## Issues Encountered
None beyond the deviation documented above. Worktree staleness was resolved via the established fast-forward + `.env` copy + `composer install` workaround (no frontend build needed for this PHP/Pest-only plan).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- FILT-01 and FILT-02 are now fully satisfied end to end (mechanism from 17-01 + UI wiring from this plan), proven by automated Livewire tests on all four tables.
- 17-03 (export wiring, FILT-03) can proceed independently — it consumes the same 17-01 service/schema-builder foundation, targeting the export classes rather than the table UI.
- No blockers identified.

---
*Phase: 17-filter-sort-export-surfaces*
*Completed: 2026-08-12*

## Self-Check: PASSED

All 4 modified table files, the new test file, and this SUMMARY.md verified to exist on disk. All 3 task commit hashes (`a23ca3a`, `5a8cd90`, `7857ece`) verified present in git log.
