---
phase: quick
plan: 260730-goi
subsystem: database
tags: [filament, eloquent, sql, call-center, call-assignments]

# Dependency graph
requires:
  - phase: quick-260730-gk3
    provides: CallQueueTable eager-load closure fix (HasMany typing), same widget file
provides:
  - Ambiguous-column-safe status filtering on CallAssignment queries (CallQueueTable + model scopes)
affects: [call-center, call-queue, call-assignments]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Always table-qualify a column name in ->where()/->whereIn() when the model's queries can be joined to another table sharing that column name (e.g. `status`)"]

key-files:
  created: []
  modified:
    - app/Filament/Widgets/CallQueueTable.php
    - app/Models/CallAssignment.php
    - tests/Feature/Filament/CallQueueTableTest.php

key-decisions:
  - "Qualified only the `status` column (call_assignments.status) across CallQueueTable::query()'s whereIn and CallAssignment's pending()/inProgress()/completed() scopes — campaign_id, assigned_to, priority left untouched as instructed since no collision exists for those columns in this join context."

patterns-established:
  - "Table-qualify shared column names (status) in model scopes/queries that may run under a Filament sortable-relation join, not just the one call site that happened to error."

requirements-completed: []

# Metrics
duration: 10min
completed: 2026-07-30
---

# Quick Task 260730-goi: Fix Ambiguous `status` Column SQL Error Summary

**Qualified `call_assignments.status` in CallQueueTable's query and CallAssignment's status scopes, fixing a live SQLSTATE[23000] ambiguous-column error triggered by sorting the Call Center queue by a joined relation column (e.g. Municipio).**

## Performance

- **Duration:** 10 min
- **Started:** 2026-07-30T16:52:00Z
- **Completed:** 2026-07-30T17:02:24Z
- **Tasks:** 1
- **Files modified:** 3

## Accomplishments
- Reproduced the exact production error (`SQLSTATE[23000]: ... Column 'status' in where clause is ambiguous`) via a RED test that sorts `CallQueueTable` by `voter.municipality.name`, which makes Filament LEFT JOIN `voters` and `municipalities` into the query
- Fixed `CallQueueTable::query()`'s `->whereIn('status', [...])` to `->whereIn('call_assignments.status', [...])`
- Defensively qualified the same unqualified `status` column in `CallAssignment`'s `scopePending`/`scopeInProgress`/`scopeCompleted` local scopes (same latent bug class, would break identically if ever used inside a joined query)
- Added a regression guard confirming the pending/in_progress filter still correctly excludes completed assignments even when the join is present

## Task Commits

1. **Task 1: Qualify all unqualified `status` column references on CallAssignment queries** - `0d02a80` (fix)

_Note: Single commit — RED test, GREEN fix, and regression guard were verified in sequence but committed together per the plan's single-task scope._

## Files Created/Modified
- `app/Filament/Widgets/CallQueueTable.php` - `whereIn('status', ...)` qualified as `whereIn('call_assignments.status', ...)` in `query()`
- `app/Models/CallAssignment.php` - `scopePending`/`scopeInProgress`/`scopeCompleted` now filter on `call_assignments.status` instead of unqualified `status`
- `tests/Feature/Filament/CallQueueTableTest.php` - two new tests: sorting by `voter.municipality.name` no longer throws, and the pending/in_progress filter still excludes completed assignments under that same join

## Decisions Made
- Left `campaign_id`, `assigned_to`, and `priority` untouched in both files per the plan's explicit scope boundary — no other table joined by `CallQueueTable` (`voters`, `municipalities`) has those exact column names, so only `status` was ambiguous.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. RED test reproduced the exact production SQL error on the first attempt (`sortTable('voter.municipality.name')` triggers Filament's automatic relation-sort JOINs); the fix was a straightforward column-qualification matching the plan's prescribed action.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The Call Center queue table (`CallQueueTable`) can now be sorted by Municipio or Barrio without 500ing in production.
- `CallAssignment`'s status scopes are now safe to use in any future joined-query context (e.g. reporting widgets that join `voters`).
- No further action needed; this is a standalone fix with no downstream dependents introduced.

---
*Phase: quick*
*Completed: 2026-07-30*

## Self-Check: PASSED

- FOUND: app/Filament/Widgets/CallQueueTable.php
- FOUND: app/Models/CallAssignment.php
- FOUND: tests/Feature/Filament/CallQueueTableTest.php
- FOUND: .planning/quick/260730-goi-fix-ambiguous-status-column-sql-error-in/260730-goi-SUMMARY.md
- FOUND commit: 0d02a80
