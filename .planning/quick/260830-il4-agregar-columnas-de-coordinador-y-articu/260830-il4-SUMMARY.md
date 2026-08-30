---
phase: 260830-il4
plan: 01
subsystem: filament-resources
tags: [filament-v4, eloquent-eager-loading, pest, voters, leaders, coordinators, articuladores]

# Dependency graph
requires:
  - phase: (pre-existing) User.coordinator()/areaCoordinator() BelongsTo relations + Voter.registeredBy() BelongsTo, plus the ApoyosLideresCoordinadoresTable widget's coordinador-resolution ->state() pattern
    provides: chain-of-command relations and the polymorphic (leader-vs-coordinator registrant) resolution pattern this plan reused verbatim
provides:
  - VotersTable now shows "Coordinador" and "Articulador" derived columns, correctly resolving both the leader-registered and coordinator-direct-registered cases
  - LeadersTable now shows an "Articulador" column (leader's coordinator's areaCoordinator)
  - CoordinatorsTable now shows an "Articulador" column (coordinator's own areaCoordinator)
  - All three tables eager-load the new relation chains to avoid N+1 queries
affects: [voter-operations, coordinator-panel, leader-panel, articulador-panel, reports]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Extended the existing ApoyosLideresCoordinadoresTable coordinador-resolution ->state() closure pattern one level further (->areaCoordinator) to add an Articulador column, reused identically in VotersTable"
    - "Dot-notation TextColumn (e.g. coordinator.areaCoordinator.name) for single-fixed-path relations, reserving ->state() closures only for the polymorphic registrant case (Voter.registeredBy can be a leader OR a coordinator)"

key-files:
  created:
    - tests/Feature/Filament/VoterResourceHierarchyColumnsTest.php
    - tests/Feature/Filament/LeaderResourceArticuladorColumnTest.php
    - tests/Feature/Filament/CoordinatorResourceArticuladorColumnTest.php
  modified:
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - app/Filament/Resources/Leaders/Tables/LeadersTable.php
    - app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php

key-decisions:
  - "VotersTable's Coordinador/Articulador columns deliberately omit ->searchable()/->sortable() since they are derived ->state() closures over a polymorphic relation, matching the exact precedent set by ApoyosLideresCoordinadoresTable's own 'coordinador' column."
  - "LeadersTable/CoordinatorsTable's Articulador columns use plain dot-notation (coordinator.areaCoordinator.name / areaCoordinator.name) with full searchable()/sortable()/placeholder('—') support, since registeredBy is always fixed to a single relation path in those two resources (no leader-vs-coordinator ambiguity)."

patterns-established: []

requirements-completed: []

# Metrics
duration: ~20min
completed: 2026-08-30
---

# Quick Task 260830-il4: Agregar columnas de Coordinador y Articulador Summary

**Added Coordinador/Articulador chain-of-command columns to the Apoyos, Líderes, and Coordinadores Filament table viewers, reusing the existing polymorphic-registrant resolution pattern from `ApoyosLideresCoordinadoresTable` and extending it one hierarchy level deeper for the Articulador.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-08-30T18:10:00Z (approx, session start)
- **Completed:** 2026-08-30T18:28:00Z
- **Tasks:** 2/2 completed
- **Files modified:** 3 modified, 3 test files created

## Accomplishments
- `VotersTable` now exposes toggleable "Coordinador" and "Articulador" columns that correctly resolve the chain-of-command whether the apoyo was registered directly by a coordinator or by one of their leaders, eager-loading `registeredBy.coordinator.areaCoordinator` to avoid N+1 queries.
- `LeadersTable` now exposes an "Articulador" column (`coordinator.areaCoordinator.name`), eager-loaded alongside the existing `MetadataAssignmentService` query modification.
- `CoordinatorsTable` now exposes an "Articulador" column (`areaCoordinator.name`), eager-loaded the same way.
- 3 new Pest test files (7 tests total) lock in both the happy path and the "no articulador assigned" fallback (`'N/A'` for Voters' derived columns, `'—'` placeholder for the two dot-notation columns), using Filament's `assertTableColumnStateSet()` to assert actual rendered column state per record, not just column existence.

## Task Commits

Each task was committed atomically:

1. **Task 1: Agregar columnas Coordinador y Articulador a VotersTable** - `1bc50fb` (feat)
2. **Task 2: Agregar columna Articulador a LeadersTable y CoordinatorsTable** - `6d7a6a6` (feat)

**Plan metadata:** (this commit, docs: complete quick task)

## Files Created/Modified
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - Added `->modifyQueryUsing()` eager load of `registeredBy.coordinator.areaCoordinator`, plus `coordinador`/`articulador` derived `TextColumn`s right after "Registrado por"
- `app/Filament/Resources/Leaders/Tables/LeadersTable.php` - Chained `->with('coordinator.areaCoordinator')` onto the existing `MetadataAssignmentService` query modification, plus a `coordinator.areaCoordinator.name` column after "Coordinador"
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` - Chained `->with('areaCoordinator')` onto the existing query modification, plus an `areaCoordinator.name` column after "Correo"
- `tests/Feature/Filament/VoterResourceHierarchyColumnsTest.php` (new) - 3 tests: leader-registered apoyo, coordinator-direct-registered apoyo, missing-articulador N/A case
- `tests/Feature/Filament/LeaderResourceArticuladorColumnTest.php` (new) - 2 tests: assigned articulador, unassigned (placeholder) case
- `tests/Feature/Filament/CoordinatorResourceArticuladorColumnTest.php` (new) - 2 tests: assigned articulador, unassigned (placeholder) case

## Decisions Made

See `key-decisions` in frontmatter above — both decisions were direct extrapolations of the plan's own explicit guidance (searchable/sortable omission for Voters' derived columns; plain dot-notation for the two fixed-path resources), no new judgment calls required.

## Deviations from Plan

None - plan executed exactly as written. All interface details (relation names, eager-load chains, column placement, test file naming/style) verified in the plan's `<interfaces>` section matched the codebase exactly on first read, and every task's Pest tests passed on the first run with no debugging required.

## Issues Encountered

None. The plan's own `<action>` blocks were followed verbatim, including method names (`assertTableColumnStateSet`) and relation chains, all of which existed exactly as documented in `app/Models/User.php` and `app/Models/Voter.php`.

## Verification Results

```
php artisan test --filter=VoterResourceHierarchyColumnsTest                                                      → 3 passed (12 assertions)
php artisan test --filter="LeaderResourceArticuladorColumnTest|CoordinatorResourceArticuladorColumnTest"         → 4 passed (8 assertions)
php artisan test --filter="VoterResourceHierarchyColumnsTest|LeaderResourceArticuladorColumnTest|CoordinatorResourceArticuladorColumnTest|VoterResourceCampaignColumnTogglingTest|LeaderResourceColumnTogglingTest" → 10 passed (40 assertions), no regressions
vendor/bin/pint --dirty                                                                                          → PASS, 0 files needing changes
```

## Known Stubs

None. All three new/extended columns read real, persisted relation data (`coordinator_user_id`/`area_coordinator_user_id` on `users`) — no placeholders or hardcoded empty values.

## Pending Manual Verification

Per the user's standing preference (browser-verify before considering UI changes deployed), the following has NOT yet been done in this session:

- Real-browser confirmation that the "Coordinador"/"Articulador" columns render correctly and toggle on/off in the Apoyos list view.
- Real-browser confirmation that the "Articulador" column renders correctly (including the `—` placeholder for unassigned cases) in both the Líderes and Coordinadores list views.

This joins the other pending `checkpoint:human-verify` items already tracked in STATE.md's Blockers/Concerns section (this quick task did not have an explicit checkpoint task in its PLAN.md — it was fully autonomous — but the project's standing feedback preference still applies).

## Self-Check: PASSED

All claimed files verified to exist on disk; all claimed commit hashes verified present in git log.

---
*Quick task: 260830-il4*
*Completed: 2026-08-30*
