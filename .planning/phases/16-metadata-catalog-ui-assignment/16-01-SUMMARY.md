---
phase: 16-metadata-catalog-ui-assignment
plan: 01
subsystem: backend
tags: [filament-v4, laravel, eloquent, pest, metadata-catalog, hierarchy]

# Dependency graph
requires:
  - phase: 12-hierarchy-metadata-schema-foundation
    provides: metadata_keys / user_metadata_values schema, MetadataKey/UserMetadataValue models
  - phase: 13-hierarchy-authorization-call-site-audit
    provides: User::coordinator()/leaders()/areaCoordinator()/coordinators() relations, campaign membership scoping
provides:
  - "App\\Services\\MetadataAssignmentService — shared subordinate resolution, catalog lookup, type validation, append-only write, deterministic current-value resolution"
  - "User::metadataValues() HasMany relation"
  - "App\\Filament\\Schemas\\MetadataAssignment — reusable Section (individual, D-04) + BulkAction (bulk, D-06/D-07) builders for Filament edit forms/tables"
  - "resources/views/filament/components/metadata-current-values.blade.php — x-filament:: current-value display partial"
affects: [16-02-metadata-key-resource, 16-03-filament-edit-form-sections, 16-04-filament-bulk-actions, 16-05-volt-metadata-panel, 16-06-volt-bulk-selection]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shared cross-boundary service (App\\Services\\*) consumed via app(...) from both Filament and Volt, mirroring the existing IdentityLookupService precedent"
    - "Append-only audit-by-write-path: the only mutation is UserMetadataValue::create(), never updateOrCreate/update"
    - "Deterministic current-value resolution via orderByDesc('assigned_at')->orderByDesc('id') to break same-second timestamp ties"
    - "Type-conditional form fields: 4 mutually-exclusive same-named 'value' fields gated by ->visible(fn (Get $get) => ...), matching QuestionsRelationManager's existing idiom"

key-files:
  created:
    - app/Services/MetadataAssignmentService.php
    - app/Filament/Schemas/MetadataAssignment.php
    - resources/views/filament/components/metadata-current-values.blade.php
    - tests/Feature/Metadata/MetadataAssignmentServiceTest.php
  modified:
    - app/Models/User.php

key-decisions:
  - "Rephrased two plan-mandated inline comments to avoid the literal substrings 'updateOrCreate' and 'teamCoordinatorUserIds' that the plan's own acceptance-criteria greps required to be absent — kept the intended meaning (D-03 direct-vs-transitive distinction, META-06 insert-only guarantee) without breaking the negative-match checks"

patterns-established:
  - "Filament-panel presentation components must use x-filament:: markup exclusively — zero flux: usage — per the verified Filament/Volt asset-loading boundary"

requirements-completed: [META-02, META-03, META-05, META-06]

# Metrics
duration: 40min
completed: 2026-08-11
---

# Phase 16 Plan 01: MetadataAssignmentService & Filament Schema Builder Summary

**Shared `MetadataAssignmentService` (D-03 direct-subordinate resolution, append-only assign/assignMany, id-tiebreak current-value resolution) plus a reusable Filament `Section`+`BulkAction` schema builder that four downstream admin-panel plans will consume.**

## Performance

- **Duration:** ~40 min
- **Started:** 2026-08-11T02:44:00Z (approx.)
- **Completed:** 2026-08-11T03:24:42Z
- **Tasks:** 3 completed
- **Files modified:** 5 (4 created, 1 modified)

## Accomplishments
- `App\Services\MetadataAssignmentService` with all 11 public methods: `activeKeys`, `activeKeyOptions`, `findActiveKey`, `directSubordinatesQuery`, `canAssignTo`, `subordinatesByIds`, `validateValue`, `assign`, `assignMany`, `currentValues`, `currentValueFor`
- `User::metadataValues(): HasMany` relation added next to `coordinators()`
- 14-test Pest suite (`MetadataAssignmentServiceTest`) covering D-03 role-based subordinate scoping, META-05 audit fields, META-06 append-only writes and the `assigned_at`/`id` tie-break, and client-supplied-ID re-filtering — all passing, 48 assertions
- `App\Filament\Schemas\MetadataAssignment` reusable builder: `modalSchema()` (4 type-conditional value fields), `section()` (individual assignment, `visibleOn('edit')`, keyed `metadataAssignmentSection`), `bulkAction()` (bulk assignment, `deselectRecordsAfterCompletion()`, no wrapping transaction)
- `metadata-current-values.blade.php` Filament partial — `x-filament::` only, zero Flux markup, confirmed by grep

## Task Commits

Each task was committed atomically:

1. **Task 1: Create MetadataAssignmentService and User::metadataValues()** - `24180a2` (feat)
2. **Task 2: Write the META-03/05/06 Pest suite for the service** - `383c654` (test)
3. **Task 3: Build the reusable Filament MetadataAssignment schema builder** - `b3c7792` (feat)

**Preparatory:** `627a838` (chore: sync phase 16 planning docs into worktree — the worktree was missing 16-CONTEXT.md/16-RESEARCH.md/16-01..06-PLAN.md, present only in the main working tree as uncommitted files)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `app/Services/MetadataAssignmentService.php` - Shared subordinate resolution, catalog lookup, type validation, append-only write, deterministic current-value resolution
- `app/Models/User.php` - Added `metadataValues(): HasMany` relation
- `app/Filament/Schemas/MetadataAssignment.php` - Reusable `Section`/`BulkAction` builder for the four Filament edit forms/tables in plans 03/04
- `resources/views/filament/components/metadata-current-values.blade.php` - Read-only current-values display for the Filament section body
- `tests/Feature/Metadata/MetadataAssignmentServiceTest.php` - 14 Pest tests covering META-03/05/06

## Decisions Made
- Rephrased two plan-mandated code comments (see Deviations below) — no behavioral change, just wording to satisfy the plan's own negative-match acceptance greps.
- Worktree was missing the phase 16 planning corpus (present only uncommitted in the main repo's working tree per the documented worktree-staleness pattern); copied all `16-*.md` files into this worktree and committed them as a prerequisite `chore` commit before starting Task 1.
- Ran `composer install` and copied `.env` into the worktree (both were absent) since `vendor/` and `APP_KEY` are required for `php -r` reflection checks and `php artisan test`. `node_modules`/Vite build were not required — this plan has no HTTP-level/browser tests.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Rephrased plan-mandated comment conflicting with its own acceptance-criteria grep (D-03 comment)**
- **Found during:** Task 1
- **Issue:** The plan's `<action>` literally instructs adding the comment `// D-03: subordinados DIRECTOS. No usar User::teamCoordinatorUserIds() (equipo transitivo, Fase 13).` above `directSubordinatesQuery()`, but the same task's `<acceptance_criteria>` requires `grep -c "teamCoordinatorUserIds" app/Services/MetadataAssignmentService.php` to return `0`. Following the comment text verbatim would fail the acceptance check.
- **Fix:** Kept the comment's intent (this method resolves direct subordinates, not Phase 13's transitive team) but reworded it to `// D-03: subordinados DIRECTOS, no el equipo transitivo resuelto en Fase 13 para AUTHZ-01.` — no literal `teamCoordinatorUserIds` substring.
- **Files modified:** app/Services/MetadataAssignmentService.php
- **Verification:** `grep -c "teamCoordinatorUserIds" app/Services/MetadataAssignmentService.php` → `0`
- **Committed in:** 24180a2 (Task 1 commit)

**2. [Rule 3 - Blocking] Rephrased plan-mandated comment conflicting with its own acceptance-criteria grep (META-06 comment)**
- **Found during:** Task 1
- **Issue:** Same pattern — the plan's mandated comment above `assign()` is `// META-06: única ruta de escritura. INSERT puro, nunca updateOrCreate.`, but the acceptance criteria requires `grep -c "updateOrCreate\|firstOrNew" app/Services/MetadataAssignmentService.php` to return `0`.
- **Fix:** Reworded to `// META-06: única ruta de escritura. INSERT puro, nunca una actualización de fila existente.` — same meaning, no literal `updateOrCreate` substring.
- **Files modified:** app/Services/MetadataAssignmentService.php
- **Verification:** `grep -c "updateOrCreate\|firstOrNew" app/Services/MetadataAssignmentService.php` → `0`
- **Committed in:** 24180a2 (Task 1 commit)

**3. [Rule 3 - Blocking] Worktree missing phase 16 planning docs, `.env`, and `vendor/`**
- **Found during:** Pre-execution setup
- **Issue:** This worktree was at the same commit as `origin/main` but the phase 16 `PLAN`/`CONTEXT`/`RESEARCH`/`DISCUSSION-LOG` files existed only as uncommitted files in the main repo's working tree (not yet part of any commit), so a `git merge --ff-only` would not have surfaced them. `vendor/` and `.env` were also absent.
- **Fix:** Copied the 9 `16-*.md` files, `.env`, and `.planning/STATE.md`/`ROADMAP.md` (which were also ahead in the main tree) from the main repo path into the worktree; ran `composer install`.
- **Files modified:** `.planning/phases/16-metadata-catalog-ui-assignment/*.md`, `.env` (untracked, not committed), `.planning/STATE.md`, `.planning/ROADMAP.md`
- **Verification:** `php artisan test`, `php -r` reflection checks, and `vendor/bin/pint` all run successfully afterward.
- **Committed in:** 627a838 (planning docs only; `.env` is gitignored and STATE.md/ROADMAP.md are committed in the final metadata commit)

---

**Total deviations:** 3 auto-fixed (2 blocking-comment rewordings, 1 blocking environment setup)
**Impact on plan:** All three were necessary to make the plan's own acceptance criteria pass or to make the worktree executable at all. No scope creep — no code behavior changed beyond comment wording.

## Issues Encountered
None beyond the deviations documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `MetadataAssignmentService` and `App\Filament\Schemas\MetadataAssignment` are ready for consumption by 16-02 (MetadataKeyResource), 16-03 (Filament edit-form sections), 16-04 (Filament bulk actions on admin tables), 16-05 (Volt metadata panel), and 16-06 (Volt bulk selection) — all per this phase's fan-out plan.
- No blockers. `MetadataAssignmentServiceTest` (14/14) and the Phase 12 regression `MetadataCatalogSchemaTest` (6/6) both pass in isolation.

---
*Phase: 16-metadata-catalog-ui-assignment*
*Completed: 2026-08-11*

## Self-Check: PASSED

All created files verified present on disk (`app/Services/MetadataAssignmentService.php`, `app/Filament/Schemas/MetadataAssignment.php`, `resources/views/filament/components/metadata-current-values.blade.php`, `tests/Feature/Metadata/MetadataAssignmentServiceTest.php`, `.planning/phases/16-metadata-catalog-ui-assignment/16-01-SUMMARY.md`) and all 4 commits (`627a838`, `24180a2`, `383c654`, `b3c7792`) verified present in `git log --oneline --all`.
