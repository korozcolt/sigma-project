---
phase: 11-scheduled-reconciliation-job
plan: 03
subsystem: data
tags: [voters, migration, eloquent, reconciliation, schema]

# Dependency graph
requires: []
provides:
  - "voters.reconciliation_attempts (unsignedInteger, default 0) and voters.reconciliation_exhausted_at (nullable timestamp) columns, both wired into Voter's $fillable and casts()"
affects: [11-04-scheduled-reconciliation-job-logic]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Additive Schema::table migration matching the existing polling_place_source/polling_place_resolved_at precedent (column order via ->after())"]

key-files:
  created:
    - database/migrations/2026_07_26_120000_add_reconciliation_fields_to_voters_table.php
  modified:
    - app/Models/Voter.php
    - tests/Feature/VoterTest.php

key-decisions:
  - "Added protected $attributes = ['reconciliation_attempts' => 0] to Voter so a freshly created (in-memory, not re-fetched) Voter reflects the DB default immediately, matching the plan's must-haves truth — without this, Eloquent leaves the attribute unset in memory after insert since Laravel does not refresh DB-defaulted columns post-insert (Rule 1 fix, confirmed via tinker before/after)."

requirements-completed: []  # RECON-05 intentionally NOT marked complete by this plan alone — see Decisions Made below

# Metrics
duration: 16min
completed: 2026-07-26
---

# Phase 11 Plan 03: Reconciliation Schema Foundation (D-09) Summary

**Added `voters.reconciliation_attempts` (unsignedInteger, default 0) and `voters.reconciliation_exhausted_at` (nullable timestamp) columns and wired both into the `Voter` model's fillable/casts, ready for Plan 11-04's reconciliation job to read/write.**

## Performance

- **Duration:** ~16 min
- **Tasks:** 2
- **Files modified:** 1 created (migration), 2 modified (model, test)

## Accomplishments

- New migration `2026_07_26_120000_add_reconciliation_fields_to_voters_table.php` adds `reconciliation_attempts` (unsignedInteger, default 0, placed after `polling_place_resolved_at`) and `reconciliation_exhausted_at` (nullable timestamp, placed after `reconciliation_attempts`), matching the exact style of the existing `add_polling_place_source_to_voters_table` migration. Applied successfully (`php artisan migrate:status` shows `Ran`).
- `Voter` model's `$fillable` and `casts()` extended with both new columns (`integer` cast for attempts, `datetime` cast for exhausted-at), directly after the existing `polling_place_resolved_at` entries to mirror the migration's column order.
- New Pest test in `tests/Feature/VoterTest.php` proves a freshly-created voter defaults to `reconciliation_attempts === 0` / `reconciliation_exhausted_at === null`, and that setting + re-fetching `reconciliation_exhausted_at` returns a `Carbon` instance (datetime cast confirmed).
- Followed TDD: wrote the failing test first (RED, confirmed via `php artisan test --filter=VoterTest` showing 1 failure), then implemented the model changes (GREEN, all 35 tests passing).

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration for reconciliation_attempts + reconciliation_exhausted_at (D-09)** - `5da0943` (feat)
2. **Task 2 (RED): Add failing test for reconciliation fields** - `eebb9b6` (test)
2. **Task 2 (GREEN): Wire new columns into Voter model** - `a37a70a` (feat)

## Files Created/Modified

- `database/migrations/2026_07_26_120000_add_reconciliation_fields_to_voters_table.php` - new additive migration, applied
- `app/Models/Voter.php` - `$fillable` + `casts()` extended; `$attributes` default added for `reconciliation_attempts`
- `tests/Feature/VoterTest.php` - new test covering default values + datetime cast

## Decisions Made

- **[Rule 1 - Bug] Added `protected $attributes = ['reconciliation_attempts' => 0]` to `Voter`.** Found during Task 2's GREEN step: after `Voter::factory()->create()`, the in-memory model's `reconciliation_attempts` attribute was `null` (not `0`) because Eloquent does not re-fetch DB-defaulted columns after `INSERT` — only `$model->fresh()` reflected the DB default. The plan's `must_haves.truths` explicitly requires a freshly-created voter (no explicit override) to read `reconciliation_attempts === 0` without an extra re-fetch, so a model-level default attribute was added to satisfy this in-memory behavior. Confirmed via `php artisan tinker` before (returned `NULL`) and after (returned `0`) the fix, and via the passing Pest test. No default needed for `reconciliation_exhausted_at` since `null` is both the DB default and Eloquent's natural unset-attribute value.
- **RECON-05 intentionally NOT marked complete in REQUIREMENTS.md by this plan alone**, despite being listed in this plan's frontmatter `requirements` field. This plan only adds the persisted counter/timestamp columns (the schema foundation) — RECON-05's actual terminal/exhaustion-state claim is realized by Plan 11-04's reconciliation job logic that increments/resets `reconciliation_attempts` and sets `reconciliation_exhausted_at` at the threshold. Deferred requirement sign-off to phase completion, matching the precedent set in Phase 10 Plan 01 and Phase 11 Plan 01 for requirements split across multiple plans in a phase.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed in-memory default for `reconciliation_attempts` not reflecting DB default post-insert**
- **Found during:** Task 2 (TDD GREEN step)
- **Issue:** A freshly created `Voter` (via factory, no override) had `reconciliation_attempts === null` in memory instead of `0`, because Eloquent doesn't refresh DB-defaulted columns after insert.
- **Fix:** Added `protected $attributes = ['reconciliation_attempts' => 0];` to `Voter`.
- **Files modified:** `app/Models/Voter.php`
- **Commit:** `a37a70a`

## Issues Encountered

None beyond the auto-fixed issue above.

## User Setup Required

None.

## Next Phase Readiness

- `voters.reconciliation_attempts` and `voters.reconciliation_exhausted_at` are persisted, queryable, and Eloquent-accessible — Plan 11-04's reconciliation job can now increment/reset attempts and set the exhaustion timestamp without any further schema work.

---
*Phase: 11-scheduled-reconciliation-job*
*Completed: 2026-07-26*

## Self-Check: PASSED

All claimed files exist on disk (migration, `app/Models/Voter.php` wiring, `tests/Feature/VoterTest.php` test) and all three task commits (`5da0943`, `eebb9b6`, `a37a70a`) are present in git history.
