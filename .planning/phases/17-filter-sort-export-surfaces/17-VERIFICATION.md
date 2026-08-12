---
phase: 17-filter-sort-export-surfaces
verified: 2026-08-12T03:29:38Z
status: passed
score: 6/6 must-haves verified
---

# Phase 17: Filter/Sort/Export Surfaces Verification Report

**Phase Goal:** Operators can filter, sort, and export by any assigned metadata value across the relevant Filament tables, with correct numeric ordering for numeric keys.
**Verified:** 2026-08-12T03:29:38Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Querying users through `withCurrentValueSelects()` resolves each active metadata key's current value at SQL level, numeric-typed keys sort numerically | ✓ VERIFIED | `MetadataAssignmentService::withCurrentValueSelects()` (app/Services/MetadataAssignmentService.php:153-175) uses `CAST(value AS DECIMAL(20,4))` for numeric-typed keys; `MetadataAssignmentServiceQueryTest` proves 2 sorts before 10 |
| 2 | `applyMetadataFilter()` returns only users whose current value exactly equals the given value, excluding stale rows | ✓ VERIFIED | app/Services/MetadataAssignmentService.php:177-190; test "applyMetadataFilter matches only the current value, excluding stale…" passes |
| 3 | An operator viewing Usuarios/Coordinadores/Líderes/Articuladores can filter by metadata key+value and see only matching rows (FILT-01) | ✓ VERIFIED | All 4 `*Table.php` files call `MetadataTableFilter::make()` inside `->filters([...])`; `MetadataTableFilterAndSortTest` dataset-tests filtering across all 4 tables — all pass |
| 4 | An operator can sort any of the 4 tables by a metadata column; numeric-typed keys sort numerically, not alphabetically (FILT-02) | ✓ VERIFIED | All 4 tables append `...MetadataTableColumns::make()` (each column `->sortable()`); `MetadataTableFilterAndSortTest`'s "sorts the usuarios table by a numeric metadata key numerically" test passes |
| 5 | The metadata column/filter are present on all 4 tables, hidden-by-default per the toggleable convention | ✓ VERIFIED | `MetadataTableColumns::make()` sets `toggleable(isToggledHiddenByDefault: true)`; confirmed present via source read of all 4 `*Table.php` files and `assertTableColumnExists`/`assertTableFilterExists` tests |
| 6 | Coordinadores/Líderes/Anotadores/Testigos exports each include one column per active metadata key, current value only, inactive keys excluded (FILT-03) | ✓ VERIFIED | All 4 export classes (`CoordinatorsExport`, `LeadersExport`, `AnnotatorsExport`, `WitnessesExport`) apply `withCurrentValueSelects()` in `query()` and spread `activeMetadataKeys` into `headings()`/`map()`; `MetadataExportColumnsTest` (8 tests) proves headings/blank/inactive-exclusion/current-value across all 4 |

**Score:** 6/6 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Services/MetadataAssignmentService.php` | `withCurrentValueSelects()` + `applyMetadataFilter()` | ✓ VERIFIED | Both methods present, exact signatures match plan, `CAST(value AS DECIMAL(20,4))` present |
| `app/Filament/Schemas/MetadataTableColumns.php` | `make(): array<int, TextColumn>` | ✓ VERIFIED | Exists, one toggleable+sortable TextColumn per active key |
| `app/Filament/Schemas/MetadataTableFilter.php` | `make(): Filter` cascading key→value | ✓ VERIFIED | Exists, `Filter::make('metadata')`, 4 type-gated value fields, exact-match query |
| `app/Filament/Resources/Users/Tables/UsersTable.php` | modifyQueryUsing + filter + columns wired | ✓ VERIFIED | All 3 wired in, existing filters/columns/bulk actions preserved |
| `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` | modifyQueryUsing + filter + columns wired (first `->filters()`) | ✓ VERIFIED | First `->filters([MetadataTableFilter::make()])` call added, columns appended |
| `app/Filament/Resources/Leaders/Tables/LeadersTable.php` | modifyQueryUsing + filter + columns wired (first `->filters()`) | ✓ VERIFIED | Same pattern confirmed |
| `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php` | modifyQueryUsing + filter + columns wired (first `->filters()`) | ✓ VERIFIED | Same pattern confirmed |
| `app/Exports/CoordinatorsExport.php` | dynamic headings/map + query() applies withCurrentValueSelects() | ✓ VERIFIED | `activeMetadataKeys` property, `withCurrentValueSelects($builder, $this->activeMetadataKeys)` in query(), headings/map spread confirmed |
| `app/Exports/LeadersExport.php` | same pattern | ✓ VERIFIED | Same pattern confirmed (`$leader` param) |
| `app/Exports/AnnotatorsExport.php` | same pattern | ✓ VERIFIED | Same pattern confirmed (`$user` param) |
| `app/Exports/WitnessesExport.php` | same pattern | ✓ VERIFIED | Same pattern confirmed (`$user` param) |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `MetadataTableFilter.php` | `MetadataAssignmentService.php` | `Filter::query()` calls `applyMetadataFilter()` | ✓ WIRED | Line 60: `app(MetadataAssignmentService::class)->applyMetadataFilter($query, $key, (string) $data['value'])` |
| `MetadataTableColumns.php` | `MetadataAssignmentService.php` | TextColumn names (`metadata_{id}`) match `addSelect` aliases | ✓ WIRED | Both use identical `"metadata_{$key->id}"` alias convention |
| `UsersTable.php` | `MetadataAssignmentService.php` | `->modifyQueryUsing(...->withCurrentValueSelects($query))` | ✓ WIRED | Line 28, confirmed present |
| `CoordinatorsTable.php` | `MetadataTableFilter.php` | `->filters([MetadataTableFilter::make()])` | ✓ WIRED | Line 62, confirmed present |
| `LeadersTable.php` | `MetadataTableColumns.php` | `->columns([...existing, ...MetadataTableColumns::make()])` | ✓ WIRED | Line 57, confirmed present |
| `AreaCoordinatorsTable.php` | `MetadataAssignmentService.php` / schema builders | `modifyQueryUsing` + `filters` + `columns` | ✓ WIRED | Lines 22, 57, 60, confirmed present |
| `CoordinatorsExport.php` | `MetadataAssignmentService.php` | `query()` returns `withCurrentValueSelects($builder, $this->activeMetadataKeys)` | ✓ WIRED | Line 54, confirmed present |
| `LeadersExport.php` | `MetadataAssignmentService.php` | constructor sets `activeMetadataKeys = activeKeys()` | ✓ WIRED | Line 38, confirmed present |
| `AnnotatorsExport.php` / `WitnessesExport.php` | `headings()`/`map()` | `...activeMetadataKeys->pluck('label')` / `...activeMetadataKeys->map(...metadata_{$key->id}...)` | ✓ WIRED | Confirmed present in both files |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|---------------------|--------|
| `MetadataTableColumns` columns (`metadata_{id}`) on all 4 tables | `metadata_{id}` addSelect alias | `MetadataAssignmentService::withCurrentValueSelects()` — correlated subquery against `user_metadata_values` (real table, ordered by `assigned_at DESC, id DESC`) | ✓ FLOWING | Confirmed via passing tests: "renders the current (latest) metadata value in the dynamic column, not a stale historical one" — actual DB row resolved, not static/hardcoded |
| Export `headings()`/`map()` cells | `activeMetadataKeys` Collection | `MetadataAssignmentService::activeKeys()` — real `MetadataKey::query()->where('is_active', true)` DB query | ✓ FLOWING | Confirmed via passing tests: inactive keys excluded from headings, current (not stale) value shown, blank-when-missing |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Numeric metadata key sorts numerically (10 above 2) at the query-builder level | `php artisan test tests/Feature/Metadata/MetadataAssignmentServiceQueryTest.php` | 4/4 pass | ✓ PASS |
| Filter/sort/column-existence work end to end via real Livewire table interaction across all 4 admin tables | `php artisan test tests/Feature/Filament/MetadataTableFilterAndSortTest.php` | 10/10 pass | ✓ PASS |
| Export classes produce correct dynamic columns end to end (query + headings + map) | `php artisan test tests/Feature/Metadata/MetadataExportColumnsTest.php` | 8/8 pass | ✓ PASS |
| Schema-builder shape (column naming, toggleable, sortable, filter naming) | `php artisan test tests/Feature/Metadata/MetadataTableSchemasTest.php` | 2/2 pass | ✓ PASS |
| No regression in pre-existing export test suites (LeadersExportTest, UsersExportTest) | `php artisan test tests/Feature/Coordinator/LeadersExportTest.php tests/Feature/CampaignAdmin/UsersExportTest.php` | 9/9 pass | ✓ PASS |
| No regression in Phase 16 metadata suite | `php artisan test tests/Feature/Metadata/` | 75/75 pass | ✓ PASS |
| `vendor/bin/pint --test` on all 11 Phase-17-touched files | pint | 11/11 files PASS, no style violations | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| FILT-01 | 17-01, 17-02 | Las tablas Filament de usuarios/coordinadores/líderes/articuladores permiten filtrar por llave y valor de metadata | ✓ SATISFIED | `MetadataTableFilter::make()` wired into all 4 tables' `->filters([...])`; `MetadataTableFilterAndSortTest` filter-dataset tests pass for all 4 tables |
| FILT-02 | 17-01, 17-02 | Las mismas tablas permiten ordenar por valor de una llave de metadata, con orden numérico correcto para llaves tipo número (no alfabético) | ✓ SATISFIED | `withCurrentValueSelects()` CASTs numeric-typed values to `DECIMAL(20,4)`; `MetadataTableColumns::make()` marks columns `->sortable()`; numeric-sort test (2 before 10) passes |
| FILT-03 | 17-03 | Los exports CSV existentes de usuarios/coordinadores/líderes incluyen las columnas de metadata asignada | ✓ SATISFIED | `CoordinatorsExport`, `LeadersExport`, `AnnotatorsExport`, `WitnessesExport` all append per-active-key columns via `withCurrentValueSelects()`; `MetadataExportColumnsTest` (8 tests) proves headings, blank-when-missing, inactive exclusion, current-value across all 4 |

No orphaned requirements — REQUIREMENTS.md maps exactly FILT-01/02/03 to Phase 17, and all three appear in plan frontmatter `requirements:` fields (17-01/17-02: `[FILT-01, FILT-02]`, 17-03: `[FILT-03]`).

### Anti-Patterns Found

None. Scanned all 11 Phase-17-touched files for TODO/FIXME/HACK/PLACEHOLDER/stub markers — zero matches beyond legitimate Filament `->placeholder('—')`/`->placeholder('Todos')` UI empty-state text (pre-existing convention, unrelated to metadata work).

### Independent Verification of Full-Suite Failure Claim (Requested by Task)

The task asked for independent re-verification (not taking the SUMMARY claim at face value) that 17-18 full-suite failures across `DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`, `RejectionsCountersOverviewTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `VoterResourceTest` are unrelated to Phase 17. Independently re-verified:

1. **Ran the 8 files together in isolation** (`php artisan test` with all 8 file paths, excluding the rest of the suite): **83/83 tests passed, 225 assertions**. Confirms the failures are order-dependent, not deterministic bugs in those files.
2. **Grepped all 8 files for any reference** to Phase 17's touched classes (`UsersTable`, `CoordinatorsTable`, `LeadersTable`, `AreaCoordinatorsTable`, `CoordinatorsExport`, `LeadersExport`, `AnnotatorsExport`, `WitnessesExport`, `MetadataAssignmentService`, `MetadataTableColumns`, `MetadataTableFilter`): the only match was a false positive (`TopCoordinatorsTableTest.php` references the pre-existing `App\Filament\Widgets\TopCoordinatorsTable` *widget*, an unrelated class whose name happens to substring-match `CoordinatorsTable`). No genuine references found — confirms these 8 files don't exercise any Phase 17 code path.
3. **Ran the full suite twice independently.** Run 1: 17 failures across exactly the 8 claimed files, plus one additional failure in `IsElectionDayMiddlewareTest`. Run 2: 17 failures across exactly the 8 claimed files, with `IsElectionDayMiddlewareTest` passing. This non-determinism (a different failure set between two back-to-back runs with zero code changes in between) is itself strong evidence of cross-test static-state pollution, not a deterministic regression tied to Phase 17's code.
4. **Cross-referenced `.planning/STATE.md`**, which documents this exact `CampaignContext` static-override test-pollution issue recurring since Phase 12 (line 199: "Full-suite `php artisan test` run showed 17-18 failures in files unrelated to this plan (report/jurisdiction/coordinator/voter-resource tests)... matches the already-documented `CampaignContext` static-override test-pollution issue"), reproduced identically across Phases 12, 14, and multiple quick-tasks (260730-cs3, 260805-qt1), always the same class of files (report/jurisdiction/top-N widget/voter-resource tests), always passing cleanly in isolation, never touching the files any of those tasks (including this one) modified.

**Conclusion: independently confirmed — not a regression introduced by Phase 17.** This is the same pre-existing, already-tracked `CampaignContext` static-override test-pollution issue documented in STATE.md's Blockers/Concerns section, predating this phase by at least 5 phases of history.

### Human Verification Required

None required for automated-testable behavior. Optional (per project's standing "browser-verify before prod" preference, not a phase-completion blocker): manually click through the Filament UI for one of the four tables (e.g. Usuarios) to visually confirm the metadata filter dropdown/value field renders correctly and the toggled-off-by-default metadata columns appear in the column-toggle panel, and download one export (e.g. Coordinadores CSV) to visually confirm the metadata columns appear correctly in the actual file. This is a nice-to-have visual sanity check, not required for goal verification — all underlying mechanics are proven by 24 phase-specific automated tests plus zero regressions across 84 related pre-existing tests.

### Gaps Summary

None. All 6 derived observable truths verified, all 11 required artifacts exist/substantive/wired, all 9 key links wired, all 3 requirement IDs (FILT-01, FILT-02, FILT-03) satisfied with evidence, zero anti-patterns, zero regressions in the metadata/export test suites, and the pre-existing full-suite `CampaignContext` test-pollution failures independently confirmed unrelated to this phase's changes.

---

*Verified: 2026-08-12T03:29:38Z*
*Verifier: Claude (gsd-verifier)*
