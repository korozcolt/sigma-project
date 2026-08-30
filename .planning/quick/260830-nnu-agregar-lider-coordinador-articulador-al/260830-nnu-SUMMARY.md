---
phase: quick
plan: 260830-nnu
subsystem: ui
tags: [filament, infolist, voter, chain-of-command]

requires:
  - phase: 260830-il4
    provides: "Coordinador/Articulador chain-of-command resolution pattern already proven in VotersTable/LeadersTable/CoordinatorsTable"
provides:
  - "ViewVoter infolist now shows Líder, Coordinador, Articulador (3 new TextEntry fields), matching VotersTable's on-screen columns"
  - "resolveRecord() override eager-loading registeredBy.coordinator.areaCoordinator on the ViewVoter page"
affects: [voter-detail, filament-voters]

tech-stack:
  added: []
  patterns:
    - "ViewRecord pages needing eager-loaded relations for infolist ->state() closures: override resolveRecord() and chain ->loadMissing(...) onto parent::resolveRecord(), instead of duplicating the resource's base query."

key-files:
  created: []
  modified:
    - app/Filament/Resources/Voters/Pages/ViewVoter.php
    - tests/Feature/Filament/VoterResourceTest.php

key-decisions:
  - "Extracted the 3 resolution closures into private methods (resolveLiderLabel/resolveCoordinadorLabel/resolveArticuladorLabel) rather than inline closures, following the existing latestValidationSource()/nextStepGuidance()/missingDataSummary() precedent in the same class."
  - "Coordinador/Articulador closures are byte-for-byte the same logic already used in VotersTable.php (134-158) — only the Líder closure is new (not present in the listing), since VotersTable never needed a dedicated Líder column (registeredBy.name already covers it in the listing's 'Registrado por' column)."

patterns-established:
  - "Reuse the exact registrador -> coordinator -> areaCoordinator ambiguity-resolution logic (leader vs. direct-registering coordinator) across every new UI surface that needs chain-of-command display, rather than reinventing it per screen."

requirements-completed: []

duration: 15min
completed: 2026-08-30
---

# Quick Task 260830-nnu: Agregar Líder/Coordinador/Articulador al detalle de Apoyo Summary

**Added Líder/Coordinador/Articulador TextEntry fields to ViewVoter's infolist, reusing VotersTable's exact chain-of-command resolution logic and eager-loading registeredBy.coordinator.areaCoordinator via a resolveRecord() override to avoid N+1.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-08-30T22:07:10Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- `ViewVoter`'s "Ver" detail page for an apoyo now shows Líder, Coordinador, and Articulador — closing the inconsistency the client noticed between the listing (which already had this from 260830-il4) and the individual detail view.
- The ambiguous case (registrador is a coordinador registrando directo, no líder intermedio) resolves identically to `VotersTable`: Líder → "N/A", Coordinador → the coordinador itself, Articulador → that coordinador's `areaCoordinator`.
- No N+1: added a `resolveRecord()` override that chains `->loadMissing('registeredBy.coordinator.areaCoordinator')` onto the parent's already-resolved record.

## Task Commits

1. **Task 1: Agregar Líder/Coordinador/Articulador al infolist de ViewVoter** - `0c9ad96` (feat)

**Plan metadata:** (this commit, docs — see final commit below)

## Files Created/Modified
- `app/Filament/Resources/Voters/Pages/ViewVoter.php` - Added `resolveRecord()` override + 3 new `TextEntry` fields (`lider`, `coordinador`, `articulador`) + 3 private resolver methods reusing VotersTable's exact closures.
- `tests/Feature/Filament/VoterResourceTest.php` - Added 2 new Pest tests: registrador-es-líder case (all 3 names visible) and registrador-es-coordinador-directo case ("N/A" for Líder, coordinador's own name for Coordinador, its areaCoordinator's name for Articulador).

## Decisions Made
- Extracted the 3 resolution closures into private methods, matching the existing `latestValidationSource()`/`nextStepGuidance()`/`missingDataSummary()` pattern already in `ViewVoter.php`, to keep the `infolist()` method readable.
- The Líder closure (`hasRole(LEADER) ? name : 'N/A'`) is new — `VotersTable.php` never needed one since its "Registrado por" column (`registeredBy.name`) already implicitly shows the líder's name when the registrador is a líder. The plan explicitly called for a dedicated "Líder" TextEntry on the detail view, independent of "Registrado por".

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- No blockers. Real-browser verification of the new Líder/Coordinador/Articulador fields on `/admin/voters/{id}` (both the líder-registrador case and the coordinador-directo case) is still pending, per the user's standing browser-verify-before-prod preference — not yet performed in this session.
- Full regression sweep (`--filter="Voter"`, 473 tests, 1364 assertions) showed zero regressions.

---
*Phase: quick*
*Completed: 2026-08-30*

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Voters/Pages/ViewVoter.php
- FOUND: tests/Feature/Filament/VoterResourceTest.php
- FOUND: .planning/quick/260830-nnu-agregar-lider-coordinador-articulador-al/260830-nnu-SUMMARY.md
- FOUND commit: 0c9ad96
