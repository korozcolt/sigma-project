---
phase: quick
plan: 260730-g0h
subsystem: ui
tags: [filament, tables, column-toggling]

requires: []
provides:
  - LeadersTable: all 5 columns toggleable, Correo (email) and Creado (created_at) hidden by default
  - VotersTable: Campaña (campaign.name) column hidden by default
affects: [leaders-list, voters-list]

tech-stack:
  added: []
  patterns:
    - "Filament TextColumn::toggleable(isToggledHiddenByDefault: true) for column-visibility defaults"
    - "Livewire Filament table tests use assertTableColumnExists(name, checkColumnUsing) + assertCanRenderTableColumn/assertCanNotRenderTableColumn to verify toggle config and default render state"

key-files:
  created:
    - tests/Feature/Filament/LeaderResourceColumnTogglingTest.php
    - tests/Feature/Filament/VoterResourceCampaignColumnTogglingTest.php
  modified:
    - app/Filament/Resources/Leaders/Tables/LeadersTable.php
    - app/Filament/Resources/Voters/Tables/VotersTable.php

key-decisions:
  - "Added dedicated Pest tests (not in original plan) per CLAUDE.md's test-enforcement rule, using Filament's assertTableColumnExists/assertCanNotRenderTableColumn helpers to lock in both the toggle config and the default-hidden render state."

patterns-established:
  - "Toggle-column default-visibility regression coverage: assertTableColumnExists(name, fn ($column) => $column->isToggleable() && $column->isToggledHiddenByDefault()) paired with assertCanNotRenderTableColumn(name)."

requirements-completed: []

duration: 8min
completed: 2026-07-30
---

# Quick Task 260730-g0h: Column toggling for LeadersTable + hide VotersTable Campaña Summary

**LeadersTable's 5 columns made toggleable (Correo/Creado hidden by default); VotersTable's Campaña column switched from visible-toggleable to hidden-by-default.**

## Performance

- **Duration:** 8 min
- **Tasks:** 2 completed
- **Files modified:** 2 (+ 2 new test files)

## Accomplishments
- Líderes list ("Alternar columnas" menu) now toggles all 5 columns; Correo and Creado start hidden, reducing visual clutter on first load.
- Apoyos list's Campaña column now starts hidden by default (consistent with the other low-priority toggleable columns in that table), while every other column's toggle state is untouched.
- Added regression tests for both changes since neither table had existing coverage for column-toggle defaults.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add column toggling to LeadersTable, hide Correo/Creado by default** - `1615c60` (feat)
2. **Task 2: Hide VotersTable's Campaña column by default** - `47e42e3` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Filament/Resources/Leaders/Tables/LeadersTable.php` - Added `->toggleable()` to all 5 columns (name, coordinator.name, registered_voters_count visible by default; email, created_at hidden by default)
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - `campaign.name` column changed from `->toggleable()` to `->toggleable(isToggledHiddenByDefault: true)`
- `tests/Feature/Filament/LeaderResourceColumnTogglingTest.php` - New: asserts all 5 LeadersTable columns' toggle config and default render state
- `tests/Feature/Filament/VoterResourceCampaignColumnTogglingTest.php` - New: asserts campaign.name's toggle config and default-hidden render state

## Decisions Made
- Added dedicated tests for both tables (not specified in the plan) to satisfy CLAUDE.md's "every change must have a test" rule — no existing test in the codebase covered Filament column-toggle defaults, so a reusable pattern (`assertTableColumnExists` with a `checkColumnUsing` closure + `assertCanNotRenderTableColumn`) was established for both.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical] Added test coverage for column-toggle defaults**
- **Found during:** Task 1 and Task 2
- **Issue:** Plan specified only grep-based automated verification, no test file, but CLAUDE.md mandates a test for every change and no existing test covered these tables' toggle behavior.
- **Fix:** Added `LeaderResourceColumnTogglingTest.php` and `VoterResourceCampaignColumnTogglingTest.php`, verifying both `isToggleable()`/`isToggledHiddenByDefault()` config and actual default-hidden rendering via Filament's Livewire testing helpers.
- **Files modified:** tests/Feature/Filament/LeaderResourceColumnTogglingTest.php, tests/Feature/Filament/VoterResourceCampaignColumnTogglingTest.php
- **Verification:** `php artisan test --filter=LeaderResourceColumnTogglingTest` (2 passed), `php artisan test --filter=VoterResourceCampaignColumnTogglingTest` (1 passed), plus full `VoterResourceTest` (33 passed) and related Leader/Voter table tests (11 passed) confirmed no regressions.
- **Committed in:** 1615c60 (Task 1), 47e42e3 (Task 2)

---

**Total deviations:** 1 auto-fixed (1 missing critical - test coverage)
**Impact on plan:** Pure additive test coverage; no scope creep, no behavior change beyond what the plan specified.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
Both Filament tables' column-visibility defaults now match the intended UX (less clutter on first load). No blockers for follow-on work.

---
*Phase: quick*
*Completed: 2026-07-30*

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Leaders/Tables/LeadersTable.php
- FOUND: app/Filament/Resources/Voters/Tables/VotersTable.php
- FOUND: tests/Feature/Filament/LeaderResourceColumnTogglingTest.php
- FOUND: tests/Feature/Filament/VoterResourceCampaignColumnTogglingTest.php
- FOUND commit: 1615c60
- FOUND commit: 47e42e3
