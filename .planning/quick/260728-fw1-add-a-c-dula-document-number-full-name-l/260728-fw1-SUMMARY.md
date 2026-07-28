---
phase: quick
plan: 260728-fw1
subsystem: forms
tags: [filament-v4, livewire-volt, eloquent, csv-import, production-backfill]

# Dependency graph
requires: []
provides:
  - "national_identity_records table + NationalIdentityRecord model + IdentityLookupService::findByDocumentNumber() — single shared cédula -> name lookup"
  - "identity:import-directory artisan command — 2-pass conflict detection, conflicts report via Storage disk, chunked upsert, --dry-run support, idempotent re-runs"
  - "Autofill + lock + unlock wired into all 5 name-entry touch points: Voter (Filament + Volt), Leader (Filament + Volt), Coordinator (Filament)"
  - "Both production databases (sigma on sigma-app-kb2mdl, sigma_betha on sigma-betha-app-pw6k9q) backfilled with 371,010 national_identity_records rows each"
affects: [voter-creation, leader-creation, coordinator-creation, campaign-onboarding]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Hidden name_locked form field (dehydrated(false)) + disabled(fn (Get $get) => (bool) $get('name_locked')) + suffixAction unlock, mirrored identically across 3 Filament resources"
    - "Volt public bool $nameLocked property + :disabled binding + unlockName() method, mirrored across 2 Volt components, layered independently on top of existing Registraduría/census updatedDocumentNumber() logic without disturbing it"
    - "2-pass LazyCollection streaming CSV import: pass 1 detects cédulas with conflicting tuples across rows, pass 2 buffers non-conflicting rows keyed by cédula (dedupes exact-duplicate rows) and chunked-upserts every 1000"

key-files:
  created:
    - database/migrations/2026_07_28_150000_create_national_identity_records_table.php
    - app/Models/NationalIdentityRecord.php
    - database/factories/NationalIdentityRecordFactory.php
    - app/Services/IdentityLookupService.php
    - app/Console/Commands/ImportIdentityDirectory.php
    - tests/fixtures/identity/identity-sample.csv
    - tests/Feature/Services/IdentityLookupServiceTest.php
    - tests/Feature/ImportIdentityDirectoryTest.php
    - tests/Feature/Filament/VoterIdentityLookupTest.php
    - tests/Feature/Leader/RegisterVoterIdentityLookupTest.php
    - tests/Feature/Filament/LeaderIdentityLookupTest.php
    - tests/Feature/Coordinator/CreateLeaderIdentityLookupTest.php
    - tests/Feature/Filament/CoordinatorIdentityLookupTest.php
  modified:
    - app/Filament/Resources/Voters/Schemas/VoterForm.php
    - resources/views/livewire/leader/register-voter.blade.php
    - app/Filament/Resources/Leaders/Schemas/LeaderForm.php
    - resources/views/livewire/coordinator/create-leader.blade.php
    - app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php

key-decisions:
  - "national_identity_records has no campaign_id — cross-instance reference catalog, mirrors NationalCensusRecord precedent (Aldemar and sigma-betha are separate databases, each gets its own independent full import)"
  - "identity:import-directory's printed 'Registros importados/actualizados' counter counts rows processed into the upsert buffer, not unique cédulas upserted — an exact-duplicate row for an already-seen cédula still increments the counter even though the keyed buffer dedupes it before the actual upsert call. Confirmed as expected/correct behavior (not a bug) when production counts showed 371,012 processed vs 371,010 actual rows in national_identity_records on both instances — the 2-row gap matches 2 exact-duplicate rows in the 371,232-row source CSV."
  - "Production backfill executed only after explicit human approval ('aplicar importación') at the Task 7 checkpoint, per plan gate. Task 6's dry-run preview and CSV staging had already been completed and verified (0 rows in either database, CSV present in both containers) before this session's Task 8 apply run."
  - "Re-verification re-queried both databases fresh (count + spot-check cédula 1053006255) after the real import, independent of the import command's own printed output, per plan requirement."

requirements-completed: []

# Metrics
duration: ~15min (Task 8 apply + re-verify + docs, this session; Tasks 1-7 completed in prior sessions)
completed: 2026-07-28
---

# Quick Task 260728-fw1: Cédula -> Full Name Lookup/Autofill/Lock Summary

**All 5 Coordinador/Líder/Apoyo creation touch points (Filament admin forms + Livewire Volt operational forms) now autofill and lock first/last name from a 371,010-row national identity directory the moment a known cédula is blurred, with a "¿Nombre incorrecto? Editar manualmente" unlock action always available to escape the lock; both production databases (`sigma` and `sigma_betha`) are now backfilled with the imported directory, verified independently on each instance.**

## Performance

- **Duration:** ~15 min for this session's Task 8 (apply + re-verify + docs); full plan (Tasks 1-8) spans multiple sessions
- **Completed:** 2026-07-28
- **Tasks:** 8 (5 auto/TDD code tasks, 1 dry-run preview, 1 human checkpoint, 1 production apply + re-verify)
- **Files modified:** 17 (5 created source files + 8 created test files + 1 fixture, 5 modified source files) + 0 further repo files for Tasks 6-8 (SSH-only production mutation)

## Accomplishments
- `national_identity_records` table (cedula unique, nombre1/nombre2/apellido1/apellido2, timestamps), `NationalIdentityRecord` model + factory, and `IdentityLookupService::findByDocumentNumber()` — one shared lookup used by all 5 touch points, no duplicated logic.
- `identity:import-directory` artisan command: 2-pass conflict detection (discards every cédula whose source rows disagree on name data), writes a standalone conflicts report CSV via `Storage::disk('local')`, chunked upsert (1000/batch), `--dry-run` support, and idempotent re-runs. 5 Pest tests cover clean import, conflicts report content, blank-row skipping, dry-run (zero writes), and idempotency.
- Autofill + lock + unlock wired into all 5 touch points: `VoterForm.php` (Filament) + `register-voter.blade.php` (Volt) for Apoyo; `LeaderForm.php` (Filament) + `create-leader.blade.php` (Volt) for Líder; `CoordinatorForm.php` (Filament) for Coordinador. Each uses the same `Hidden('name_locked')` + `disabled()`/`dehydrated()` + `suffixAction` unlock pattern (Filament) or `nameLocked` property + `:disabled` + `unlockName()` (Volt), layered on top of existing Registraduría/census logic without disturbing it.
- 37 total Pest tests pass across the new identity-lookup suites plus the pre-existing `RegisterVoterCensusWarningTest`, `CreateLeaderOtpTest`, `LeaderResourceCampaignTest`, and `CoordinatorResourceCampaignTest` regression suites (131 assertions, no regressions). `vendor/bin/pint --test` clean on all 17 plan-listed files.
- Production backfill completed on both instances after explicit human approval ("aplicar importación"): `sigma-app-kb2mdl` (DB `sigma`) and `sigma-betha-app-pw6k9q` (DB `sigma_betha`) each independently imported the same 371,232-row source CSV, landing 371,010 `national_identity_records` rows on each (99 conflicting cédulas discarded, 22 blank rows skipped, 2 exact-duplicate rows deduped by the keyed upsert buffer on each instance). Cédula `1053006255` resolves to `LANNA JAVIANA CONTRERAS ORTEGA` on both.

## Task Commits

1. **Task 1: national_identity_records table + model + factory + IdentityLookupService** - `9cf3e7a` (feat)
2. **Task 2: identity:import-directory command** - `a888e12` (feat)
3. **Task 3: Voter (Apoyo) — Filament VoterForm + Volt register-voter.blade.php autofill/lock/unlock** - `c7dc733` (feat)
4. **Task 4: Líder — Filament LeaderForm + Volt create-leader.blade.php autofill/lock/unlock** - `573b4ad` (feat)
5. **Task 5: Coordinador — Filament CoordinatorForm autofill/lock/unlock** - `aa9a4f4` (feat)
6. **Task 6: Production backfill — PREVIEW only (dry-run, no mutation)** - no repo commit (read-only SSH dry-run + migration + CSV staging); output reported below.
7. **Task 7: Human checkpoint** - no repo commit; approved via "aplicar importación".
8. **Task 8: Apply the production import and re-verify** - no repo commit (production DB mutation via SSH); output reported below.

**Plan metadata:** `c90543e` (docs: add PLAN.md), `178dfbd` (fix: drop duplicate Set import instruction in Task 4)

## Files Created/Modified
- `database/migrations/2026_07_28_150000_create_national_identity_records_table.php` - Creates `national_identity_records` (cedula unique, nombre1, nombre2 nullable, apellido1, apellido2 nullable, timestamps).
- `app/Models/NationalIdentityRecord.php` - Eloquent model, fillable cedula/nombre1/nombre2/apellido1/apellido2.
- `database/factories/NationalIdentityRecordFactory.php` - Factory for tests.
- `app/Services/IdentityLookupService.php` - `findByDocumentNumber(string $documentNumber): ?NationalIdentityRecord`, shared by all 5 touch points.
- `app/Console/Commands/ImportIdentityDirectory.php` - `identity:import-directory` command: 2-pass conflict detection, conflicts report, chunked upsert, `--dry-run`.
- `tests/fixtures/identity/identity-sample.csv` - Fixture proving clean/conflicting/duplicate/blank row handling.
- `app/Filament/Resources/Voters/Schemas/VoterForm.php` - `document_number` afterStateUpdated triggers lookup, sets/locks first_name+last_name, unlock Action on last_name.
- `resources/views/livewire/leader/register-voter.blade.php` - `updatedDocumentNumber()` identity lookup sets/locks first_name+last_name, `unlockName()`, disabled inputs + unlock button.
- `app/Filament/Resources/Leaders/Schemas/LeaderForm.php` - Same pattern for the single `name` field.
- `resources/views/livewire/coordinator/create-leader.blade.php` - Same Volt pattern for `name`.
- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` - Same Filament pattern for `name`.
- 8 new Pest test files covering all of the above (see key-files above).

## Decisions Made
- `national_identity_records` intentionally has no `campaign_id` — cross-instance reference catalog, mirroring the `NationalCensusRecord` precedent, since Aldemar (`sigma`) and sigma-betha (`sigma_betha`) are separate databases each requiring their own independent full import.
- The import command's printed "Registros importados/actualizados" counter reflects rows processed into the upsert buffer, not unique cédulas actually upserted (the buffer is keyed by cédula and dedupes exact-duplicate rows before the real `upsert()` call). This produced an expected, non-alarming 2-row gap in production (371,012 processed vs. 371,010 actual `national_identity_records` rows) — confirmed via independent re-query, identical on both instances, consistent with 2 exact-duplicate rows existing in the 371,232-row source CSV.
- The production backfill (Tasks 6-8) ran only after explicit human approval ("aplicar importación") at the Task 7 blocking checkpoint. Task 6 (migration + CSV staging + dry-run preview) had already been completed and confirmed clean (0 rows in either database beforehand) in an earlier session; this session ran only the real (non-dry-run) import and the post-apply re-verification, per the human's explicit instruction to proceed with Task 8.
- Re-verification queried both databases fresh and independently (count + spot-check on cédula `1053006255`) after the real import, rather than trusting the import command's own printed summary, per the plan's re-verify requirement.

## Deviations from Plan
None - Tasks 1-8 executed exactly as specified. The only notable observation (the 371,012 vs. 371,010 counter/actual-row gap) is expected behavior of the command as designed (see Decisions above), not a deviation or bug.

## Issues Encountered
None. Both production imports completed on the first attempt with identical, deterministic results across independently-isolated databases.

## Production Backfill Output

### Task 6 — Dry-run preview (completed in a prior session; migration + CSV staging confirmed still in place at this session's start)
- Migration `2026_07_28_150000_create_national_identity_records_table` confirmed `Ran` via `migrate:status` on both `sigma-app-kb2mdl` and `sigma-betha-app-pw6k9q`.
- `identity-directory.csv` (14,274,914 bytes) confirmed present in both containers' `/app/storage/app/`.
- `national_identity_records` confirmed at `0` rows on both instances immediately before the Task 8 apply.

### Task 8 — Apply (this session)

**sigma-app-kb2mdl (DB `sigma`):**
```
Filas leídas: 371232.
Filas vacías omitidas (cédula/nombre1/apellido1 en blanco): 22.
Cédulas en conflicto descartadas: 99 (reporte: storage/app/identity-import-conflicts-20260728_175030.csv)
Registros importados/actualizados: 371012.
```

**sigma-betha-app-pw6k9q (DB `sigma_betha`):**
```
Filas leídas: 371232.
Filas vacías omitidas (cédula/nombre1/apellido1 en blanco): 22.
Cédulas en conflicto descartadas: 99 (reporte: storage/app/identity-import-conflicts-20260728_175052.csv)
Registros importados/actualizados: 371012.
```

### Re-verification (independent fresh query, post-apply)

**sigma-app-kb2mdl:**
```
Total national_identity_records: 371010
1053006255 -> LANNA JAVIANA CONTRERAS ORTEGA
```

**sigma-betha-app-pw6k9q:**
```
Total national_identity_records: 371010
1053006255 -> LANNA JAVIANA CONTRERAS ORTEGA
```

Both instances landed identical row counts and identical spot-check results, confirming a deterministic, correctly-isolated import into each independently-owned production database.

## User Setup Required
None - no external service configuration required. The source CSV was already staged in both containers from the prior Task 6 session.

## Next Phase Readiness
- Feature fully live: all 5 Coordinador/Líder/Apoyo creation touch points autofill+lock names from the national identity directory in both production environments.
- No blockers. Conflicts reports (99 discarded cédulas each) are available at `storage/app/identity-import-conflicts-20260728_175030.csv` (sigma-app-kb2mdl) and `storage/app/identity-import-conflicts-20260728_175052.csv` (sigma-betha-app-pw6k9q) inside each container, for optional manual review of the discarded name-conflict cédulas.

---
*Phase: quick*
*Completed: 2026-07-28*
