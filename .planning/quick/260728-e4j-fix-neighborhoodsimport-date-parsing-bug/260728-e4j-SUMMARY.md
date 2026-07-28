---
phase: quick
plan: 260728-e4j
subsystem: database
tags: [maatwebsite-excel, phpspreadsheet, pest, mysql, production-backfill]

# Dependency graph
requires: []
provides:
  - "NeighborhoodsImport forces plain-string cell binding (WithCustomValueBinder) and an explicit comma CSV delimiter (WithCustomCsvSettings), so date-like barrio names can no longer be truncated on import"
  - "Regression test proving date-like barrio names survive import intact even when AdvancedValueBinder is the active global PhpSpreadsheet binder"
  - "10 corrupted Sincelejo neighborhood names corrected in both production databases (sigma, sigma_betha)"
affects: [neighborhoods-import, voters-territorial-data, sincelejo-municipality-data]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "WithCustomValueBinder on Maatwebsite Excel imports to force TYPE_STRING cell binding and disable PhpSpreadsheet date/number auto-detection"
    - "WithCustomCsvSettings to pin an explicit delimiter and avoid PhpSpreadsheet's delimiter auto-detection misfiring on single-column CSVs"

key-files:
  created: []
  modified:
    - app/Imports/NeighborhoodsImport.php
    - tests/Feature/NeighborhoodsImportTest.php

key-decisions:
  - "Added WithCustomCsvSettings (comma delimiter) in addition to the plan's WithCustomValueBinder fix — discovered while writing the regression test that PhpSpreadsheet's delimiter auto-detection also misfires on single-column CSVs with no commas, splitting 'day de month' names into bogus extra columns even with the value binder fixed"
  - "Production backfill executed only after explicit human approval ('aplicar backfill') at the Task 3 checkpoint, per plan gate"
  - "Backfill re-derived the corrupted-row query fresh at apply time (not reused from the earlier preview) and guarded on count === 10 before applying any UPDATE, per plan safety requirement"

requirements-completed: []

# Metrics
duration: ~25min (Task 1 + Tasks 2-4)
completed: 2026-07-28
---

# Quick Task 260728-e4j: Fix NeighborhoodsImport Date-Parsing Bug + Backfill Summary

**`NeighborhoodsImport` now forces string-only cell binding (`WithCustomValueBinder`) and an explicit CSV delimiter (`WithCustomCsvSettings`) so Spanish "day de month" barrio names can never be auto-parsed as dates again; the 10 already-corrupted Sincelejo neighborhood names are corrected on both production databases (`sigma` and `sigma_betha`) after explicit human-approved backfill.**

## Performance

- **Duration:** ~25 min total across all tasks
- **Completed:** 2026-07-28
- **Tasks:** 4 (Task 1 code fix + test, Task 2 preview, Task 3 human checkpoint, Task 4 apply + verify)
- **Files modified:** 2 (code) + 0 (production DBs mutated via SSH, no repo files)

## Accomplishments
- `NeighborhoodsImport` implements `WithCustomValueBinder`, forcing every imported cell to bind as `DataType::TYPE_STRING` via `setValueExplicit()`, regardless of whatever value binder is globally active on `PhpOffice\PhpSpreadsheet\Cell\Cell` at import time.
- Also implements `WithCustomCsvSettings` to pin the CSV delimiter to `,` explicitly — found necessary while writing the regression test, since PhpSpreadsheet's delimiter auto-detection can misfire on a single-column barrio CSV (no commas anywhere in the file) and pick a space instead, splitting names like "20 De Julio I" into bogus extra columns even after the value-binder fix.
- New Pest regression test (`tests/Feature/NeighborhoodsImportTest.php`) forces the global binder to `AdvancedValueBinder` (simulating the exact production failure mode) and proves "20 De Julio I", "7 De Agosto", and "28 De Mayo" import as full strings, with no bare numeric names ("20", "7", "28") created.
- Full `NeighborhoodsImportTest` suite passes (6 tests, 23 assertions); `vendor/bin/pint --dirty` clean on both modified files.
- Production backfill applied to both instances after explicit human approval ("aplicar backfill"): 10 corrupted Sincelejo neighborhood rows corrected per an authoritative id-ordered mapping, re-derived fresh at apply time and guarded on the count still being exactly 10 before any UPDATE ran.
- Independent post-apply re-verification (separate query, not reused from the apply script) confirms 0 purely-numeric Sincelejo names and all 10 full names present in both `sigma` and `sigma_betha`.

## Task Commits

1. **Task 1: Force string-only cell binding in NeighborhoodsImport + regression test** - `f1cbaf7` (fix) — includes the plan's `WithCustomValueBinder` fix plus the additional `WithCustomCsvSettings` deviation (see Decisions).
2. **Task 2: Production backfill preview (read-only)** - no repo commit (read-only SSH query); output reported below.
3. **Task 3: Human checkpoint** - no repo commit; approved via "aplicar backfill".
4. **Task 4: Apply production backfill and re-verify** - no repo commit (production DB mutation via SSH); output reported below.

**Plan metadata:** `8233cac` (docs: create plan for NeighborhoodsImport date-parsing bug fix + backfill)

## Files Created/Modified
- `app/Imports/NeighborhoodsImport.php` - Implements `WithCustomValueBinder` (forces `TYPE_STRING` on every cell) and `WithCustomCsvSettings` (forces `,` delimiter); `model()` logic unchanged.
- `tests/Feature/NeighborhoodsImportTest.php` - Added regression test forcing `AdvancedValueBinder` globally and asserting date-like barrio names import intact.

## Decisions Made
- Added `WithCustomCsvSettings` (comma delimiter) beyond the plan's literal final-file spec, because the regression test — written exactly as specified — initially failed for a second, independent reason: PhpSpreadsheet's delimiter auto-detection on a single-column CSV (no commas in the file) can pick a space delimiter, splitting "20 De Julio I" into multiple bogus columns before the value binder even sees the string. Both fixes were needed to fully close the bug; this is a necessary correctness fix within the same file, not scope creep.
- Followed the plan's production-safety design exactly: Task 2 (preview) and Task 4 (apply) both independently re-query the corrupted rows and guard on `count === 10` before any mutation, so a changed row count between preview and apply would abort rather than silently mis-map names.
- Applied the backfill only after the user explicitly typed "aplicar backfill" per the Task 3 blocking checkpoint.

## Deviations from Plan

### Auto-fixed Issues

**1. [Correctness] Added `WithCustomCsvSettings` to force comma delimiter**
- **Found during:** Task 1 (writing the regression test with `AdvancedValueBinder` forced globally)
- **Issue:** Even with `WithCustomValueBinder` forcing string binding, PhpSpreadsheet's CSV delimiter auto-detection could still misfire on a single-column barrio CSV containing no commas, splitting "20 De Julio I" into extra bogus columns and defeating the fix.
- **Fix:** Implemented `WithCustomCsvSettings::getCsvSettings()` returning `['delimiter' => ',']`, pinning the delimiter explicitly.
- **Files modified:** `app/Imports/NeighborhoodsImport.php`
- **Verification:** `php artisan test --filter=NeighborhoodsImportTest` — all 6 tests pass, including the new regression test.
- **Committed in:** `f1cbaf7` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 correctness fix, additive and self-contained within the same file the plan already targeted).
**Impact on plan:** Necessary to fully close the bug the plan targeted; no scope creep, no other files touched.

## Issues Encountered
None beyond the delimiter deviation above, which was resolved within Task 1 before moving to the backfill tasks.

## Production Backfill Output

### Task 2 — Preview (re-run again at Task 4 start to re-derive fresh state)

**sigma-app-kb2mdl (DB `sigma`):**
```
Municipality: Sincelejo (id=999)
Corrupted rows found: 10 (expected 10)
1) id=10 current='2' -> proposed='2 De Septiembre'
2) id=50 current='6' -> proposed='6 De Febrero'
3) id=64 current='20' -> proposed='20 De Enero'
4) id=82 current='28' -> proposed='28 De Mayo'
5) id=83 current='20' -> proposed='20 De Julio I'
6) id=84 current='20' -> proposed='20 De Julio Ii'
7) id=85 current='20' -> proposed='20 De Julio Iii'
8) id=121 current='7' -> proposed='7 De Agosto'
9) id=145 current='6' -> proposed='6 De Enero'
10) id=151 current='17' -> proposed='17 De Septiembre'
```

**sigma-betha-app-pw6k9q (DB `sigma_betha`):**
```
Municipality: Sincelejo (id=999)
Corrupted rows found: 10 (expected 10)
1) id=10 current='2' -> proposed='2 De Septiembre'
2) id=50 current='6' -> proposed='6 De Febrero'
3) id=64 current='20' -> proposed='20 De Enero'
4) id=82 current='28' -> proposed='28 De Mayo'
5) id=83 current='20' -> proposed='20 De Julio I'
6) id=84 current='20' -> proposed='20 De Julio Ii'
7) id=85 current='20' -> proposed='20 De Julio Iii'
8) id=121 current='7' -> proposed='7 De Agosto'
9) id=145 current='6' -> proposed='6 De Enero'
10) id=151 current='17' -> proposed='17 De Septiembre'
```

Both instances confirmed exactly 10 corrupted rows, ids and ordinal mapping identical across both databases — approved by human ("aplicar backfill").

### Task 4 — Apply

**sigma-app-kb2mdl (DB `sigma`):**
```
Updated id=10: '2' -> '2 De Septiembre'
Updated id=50: '6' -> '6 De Febrero'
Updated id=64: '20' -> '20 De Enero'
Updated id=82: '28' -> '28 De Mayo'
Updated id=83: '20' -> '20 De Julio I'
Updated id=84: '20' -> '20 De Julio Ii'
Updated id=85: '20' -> '20 De Julio Iii'
Updated id=121: '7' -> '7 De Agosto'
Updated id=145: '6' -> '6 De Enero'
Updated id=151: '17' -> '17 De Septiembre'
Remaining purely-numeric Sincelejo neighborhood names: 0 (expect 0)
Confirmed full names now present: 10 (expect 10)
```

**sigma-betha-app-pw6k9q (DB `sigma_betha`):**
```
Updated id=10: '2' -> '2 De Septiembre'
Updated id=50: '6' -> '6 De Febrero'
Updated id=64: '20' -> '20 De Enero'
Updated id=82: '28' -> '28 De Mayo'
Updated id=83: '20' -> '20 De Julio I'
Updated id=84: '20' -> '20 De Julio Ii'
Updated id=85: '20' -> '20 De Julio Iii'
Updated id=121: '7' -> '7 De Agosto'
Updated id=145: '6' -> '6 De Enero'
Updated id=151: '17' -> '17 De Septiembre'
Remaining purely-numeric Sincelejo neighborhood names: 0 (expect 0)
Confirmed full names now present: 10 (expect 10)
```

### Independent post-apply re-verification (separate query from the apply script)

**sigma-app-kb2mdl:** Purely-numeric names remaining: `0`. Full names found: `10/10` — 17 De Septiembre | 2 De Septiembre | 20 De Enero | 20 De Julio I | 20 De Julio Ii | 20 De Julio Iii | 28 De Mayo | 6 De Enero | 6 De Febrero | 7 De Agosto. Missing: `NONE`.

**sigma-betha-app-pw6k9q:** Purely-numeric names remaining: `0`. Full names found: `10/10` — 17 De Septiembre | 2 De Septiembre | 20 De Enero | 20 De Julio I | 20 De Julio Ii | 20 De Julio Iii | 28 De Mayo | 6 De Enero | 6 De Febrero | 7 De Agosto. Missing: `NONE`.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Bug fully closed: future imports of barrio CSVs with date-like Spanish names can no longer be corrupted, regardless of ambient PhpSpreadsheet value-binder/delimiter state.
- Both production instances' historical corruption is corrected and independently re-verified.
- No blockers. Code changes (`f1cbaf7`) live on branch `worktree-agent-a96338b557412163e`, one commit ahead of `main` at the time of this task — merge/PR still needed to land the fix on `main`.

---
*Phase: quick*
*Completed: 2026-07-28*
