---
phase: quick
plan: 260731-o5i
subsystem: ui
tags: [filament, infolist, audit-log, bugfix]

# Dependency graph
requires:
  - phase: quick-260731-nuk
    provides: AuditLogResource (index + view pages) with old_values/new_values rendered as pretty-printed JSON
provides:
  - ViewAuditLog page no longer 500s for AuditLog records with mixed scalar-type old_values/new_values payloads
affects: [audit-log-viewing]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament TextEntry->state(fn ($record) => ...) reads a model attribute directly, bypassing Filament's per-array-element ->formatStateUsing() iteration when the bound state resolves to an array"

key-files:
  created: []
  modified:
    - app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
    - tests/Feature/Filament/AuditLogResourceTest.php

key-decisions:
  - "Replaced ->formatStateUsing(fn (?array $state) ...) with ->state(fn (AuditLog $record) => ...) on both old_values and new_values TextEntry blocks, reading the model's cast array attribute directly instead of letting Filament auto-iterate over an array-typed component state"
  - "Removed the now-redundant ->placeholder('—') calls on both entries since ->state() already supplies the '—' fallback itself"

patterns-established:
  - "When a Filament TextEntry's state resolves to an array with a formatter closure, use ->state(fn ($record) => ...) reading the record's attribute directly instead of ->formatStateUsing() — Filament treats an array-typed state as a list and invokes the formatter once per element"

requirements-completed: []

# Metrics
duration: 20min
completed: 2026-07-31
---

# Quick Task 260731-o5i: Fix 500 error in ViewAuditLog Summary

**Fixed a 500 TypeError on `/admin/audit-logs/{id}` caused by Filament's TextEntry auto-iterating array-typed state per-element and passing scalars into a `?array`-typed formatter closure; switched to `->state()` reading the model attribute directly.**

## Performance

- **Duration:** ~20 min
- **Tasks:** 1 completed
- **Files modified:** 2

## Accomplishments
- Diagnosed and fixed the root cause: Filament's `TextEntry`, when bound state resolves to a PHP array, invokes `formatStateUsing()` once per array element rather than once with the whole array — any non-array scalar element (e.g. an int `id`) threw `TypeError: Argument #1 ($state) must be of type ?array, int given`.
- `old_values`/`new_values` entries on `ViewAuditLog.php` now use `->state(fn (AuditLog $record): string => ...)` to read the model's cast array attribute directly, sidestepping Filament's array-as-list auto-iteration entirely.
- Added a Pest regression test with a real mixed int/string payload (mirroring a User-creation audit record) that reproduces the original crash; confirmed RED against the buggy code, then GREEN after the fix.
- All 11 tests in `AuditLogResourceTest.php` pass (10 pre-existing + 1 new regression test), no regressions.

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix TextEntry array-iteration bug and add regression test** - `5b5bb65` (fix)

_Note: This TDD task's RED (failing test confirmation) and GREEN (fix) were both verified locally before committing; only the final passing state was committed, per the plan's single-file-pair task scope._

## Files Created/Modified
- `app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php` - `old_values`/`new_values` `TextEntry` blocks now use `->state(fn (AuditLog $record): string => ...)` instead of `->formatStateUsing(fn (?array $state): string => ...)`; added `use App\Models\AuditLog;` import; removed redundant `->placeholder('—')` calls on both entries.
- `tests/Feature/Filament/AuditLogResourceTest.php` - Added regression test `'super admin can view an audit log with mixed int/string new_values without a 500 error'` asserting a 200 response and rendered JSON content for a record with `old_values: null` and `new_values` containing mixed int/string values.

## Decisions Made
- Used `->state()` (reads the model attribute directly) rather than wrapping the formatter to defensively check `is_array($state)` — `->state()` is the idiomatic Filament pattern for bypassing per-element iteration entirely, and it also naturally supplies its own `null`/empty fallback, letting `->placeholder('—')` be removed as redundant on both entries.
- No new Composer dependency added, per plan constraint and CLAUDE.md.

## Deviations from Plan

**Worktree staleness (environmental, not a plan deviation):** This session's worktree (`agent-aa5ca36f945ae7ec8`) was stale at start — missing the entire Phase `260731-nuk` audit-log resource commit range, `vendor/`, `.env`, and compiled `public/build/` assets, plus this quick task's own plan directory (never committed to git, only present untracked in the main checkout). Resolved with the established, previously-documented workaround (see `.planning/STATE.md` Blockers/Concerns): confirmed fast-forward ancestry (`git merge-base --is-ancestor`), ran `git merge --ff-only main`, copied `.env` and `public/build/` from the main checkout, ran `composer install`, and copied the untracked `260731-o5i` plan directory into the worktree. This is now a recurring, previously-logged class of issue in this repo's worktree-based execution flow — no code fix attempted here (out of scope for this task).

### Auto-fixed Issues

None - plan executed exactly as written (Task 1's action steps were followed verbatim: RED test added and confirmed failing with the exact documented `TypeError`, then GREEN fix applied and confirmed passing).

## Known Stubs

None.

## Self-Check: PASSED

- FOUND: app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
- FOUND: tests/Feature/Filament/AuditLogResourceTest.php
- FOUND: commit 5b5bb65
