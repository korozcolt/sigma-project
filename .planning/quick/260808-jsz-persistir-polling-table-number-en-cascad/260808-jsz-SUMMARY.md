---
phase: quick-260808-jsz
plan: 01
subsystem: database
tags: [eloquent, pest, artisan-command, polling-place-resolver, backfill]

requires:
  - phase: quick-260731-jmq (documented, not fixed there)
    provides: The already-fixed sibling bug (polling_place_id not persisted by resolver) whose exact fix shape and backfill-command precedent this task mirrors for polling_table_number
provides:
  - "PollingPlaceResolver::persist() now writes voters.polling_table_number for every automated resolution (resolveAutomated(), used by ReconcileFallbackPollingPlaces and VoterValidationService::validateAgainstCensus())"
  - "census:backfill-polling-table-number Artisan command to fix already-affected historical apoyos from local data only (no live/paid lookups)"
affects: [reports, voter-view, apoyo-exports]

tech-stack:
  added: []
  patterns:
    - "persist()'s polling_table_number write mirrors the polling_place_id FK block's overwrite philosophy: a real value always wins; only the weaker single-mesa '1' default is guarded to fill solely a currently-null value"

key-files:
  created:
    - app/Console/Commands/BackfillPollingTableNumber.php
    - tests/Feature/Console/BackfillPollingTableNumberTest.php
  modified:
    - app/Services/PollingPlaceResolver.php
    - tests/Feature/Services/PollingPlaceResolverTest.php

key-decisions:
  - "voters.polling_table_number is an unsignedSmallInteger column (not string) — persist() assigns $result->tableNumber (a string) to the update array, which MySQL/SQLite coerce to int on write; all new Pest assertions compare against int literals (e.g. toBe(7), not toBe('7')) to match the fetched attribute's actual PHP type."
  - "Worktree (agent-acda15161c21924ef) was one commit behind main at session start — missing this quick task's own PLAN.md, vendor/, and .env, the same recurring staleness class documented repeatedly in STATE.md Blockers/Concerns. Resolved with the established workaround: confirmed fast-forward ancestry (git merge-base --is-ancestor HEAD main), ran git merge --ff-only main, copied .env from the main checkout, ran composer install. STATE.md/SUMMARY.md updated by hand-editing the worktree copies directly, since gsd-tools init execute-phase still resolves project_root to the main checkout for this worktree (the previously-documented findProjectRoot() bug, reconfirmed here — phase_found: false was correctly reported for this non-numbered quick task, as expected, but project_root pointed at the main checkout, not this worktree)."

requirements-completed: []

duration: 20min
completed: 2026-08-08
---

# Phase quick-260808-jsz: Persistir polling_table_number en cascada de resolucion Summary

**Fixed `PollingPlaceResolver::persist()` to write `voters.polling_table_number` (the exact sibling of the already-fixed `polling_place_id` bug), plus a new `census:backfill-polling-table-number` Artisan command to recover the mesa number for already-affected historical apoyos from local audit data only.**

## Performance

- **Duration:** ~20 min (including worktree staleness recovery: fast-forward merge, .env copy, composer install)
- **Started:** 2026-08-08T19:15:00Z (approx.)
- **Completed:** 2026-08-08T19:31:00Z
- **Tasks:** 2/2 completed
- **Files modified:** 4 (2 created, 2 modified)

## Accomplishments

- `PollingPlaceResolver::persist()` now writes `polling_table_number` for every automated resolution: a real `$result->tableNumber` always overwrites whatever is currently stored (matching the `polling_place_id` FK block's overwrite philosophy), while the single-mesa `'1'` default (used when the resolved `PollingPlace` has exactly one possible mesa, `max_tables === 1`) is guarded to only ever fill a currently-null value — a defaulted `'1'` never permanently strands an apoyo once a real mesa number becomes known on a later resolution.
- New `census:backfill-polling-table-number` Artisan command, mirroring `census:backfill-polling-place-id`'s exact conventions (`--dry-run`, `DB::transaction`, Spanish messaging), recovers historical mesa numbers for already-affected voters (`polling_place_id` set, `polling_table_number` null) from the most recent `polling_place_resolutions` audit row, falling back to the single-mesa default, using only already-local data.
- 11 new Pest tests (6 for `persist()`'s precedence, 5 for the backfill command) covering the full overwrite/default/never-strand matrix.

## Task Commits

1. **Task 1: Fix persist() to write polling_table_number (real values always win, single-mesa default only fills gaps)** - `19ca5c4` (feat)
2. **Task 2: Create census:backfill-polling-table-number command with Pest coverage** - `de4a421` (feat)

**Plan metadata:** (this commit, pending)

## Files Created/Modified

- `app/Services/PollingPlaceResolver.php` - `persist()` now writes `polling_table_number` under the corrected precedence (real value always overwrites; single-mesa `'1'` default only fills a null); extended docblock documenting the sibling-bug fix
- `tests/Feature/Services/PollingPlaceResolverTest.php` - 6 new tests in a `============ polling_table_number write in persist() ============` section, covering fresh-write, single-mesa default, no-default-when-max_tables>1, always-overwrite-with-real-value, never-overwrite-with-default, and correct-a-prior-default-with-a-later-real-value
- `app/Console/Commands/BackfillPollingTableNumber.php` - new `census:backfill-polling-table-number` command (history recovery -> single-mesa default -> skip), `--dry-run` support
- `tests/Feature/Console/BackfillPollingTableNumberTest.php` - 5 new tests covering history recovery (most-recent-wins), single-mesa default, skip-on-ambiguous, ignore-already-set, and dry-run-writes-nothing

## Decisions Made

- `voters.polling_table_number`'s underlying column type is `unsignedSmallInteger`, not `string` — all new test assertions compare fetched values as PHP ints (`toBe(7)`), not strings, even though `persist()`/the backfill command both write string values (`$result->tableNumber`, `$fromHistory->table_number`) which the DB layer silently coerces to int on write/read. This matches the existing `polling_place_id` test precedent (compares against `->id`, an int) and avoids strict-type assertion failures.
- No architectural changes — followed the plan's exact code shape (mirroring `polling_place_id`'s FK block and `BackfillPollingPlaceId.php`'s command structure) with no deviations from the specified precedence logic.

## Deviations from Plan

None - plan executed exactly as written. The only adjustment was test-assertion typing (int vs string literals) to match the DB column's real coercion behavior, which is not a deviation from the plan's specified *behavior* (the plan's behavior spec used bare numbers like "equals `7`", not quoted strings) — just a correction to the literal PHP test syntax needed to assert it correctly.

## Issues Encountered

- Worktree was stale by one commit at session start (missing this quick task's own PLAN.md, `vendor/`, and `.env`) — the same recurring class of issue documented repeatedly in STATE.md's Blockers/Concerns section. Resolved with the established workaround (fast-forward merge to main, `.env` copy, `composer install`).
- A pre-existing, unrelated test flake was observed twice during full-suite verification runs: `PollingPlaceResolverTest.php`'s `otherPlace`/adapter-fixture tests (e.g. "persist writes polling_place_id when a voter is re-resolved to a different PollingPlace", "resolveAutomated does NOT call a second live adapter startLookup when the first REACHABLE adapter returns status=error") intermittently fail with a SQLite `UNIQUE constraint failed: departments.code` error, because `PollingPlace::factory()`'s/`Department::factory()`'s random `dane_department_code`/`code` generation (range 1-99) occasionally collides with the hardcoded `code: '28'` (SUCRE) set up in this file's `beforeEach`. Confirmed NOT caused by this task's changes: reran the full targeted suite (`PollingPlaceResolverTest.php` + `PollingPlaceResolverPriorityTest.php` + `BackfillPollingPlaceIdTest.php` + `BackfillPollingTableNumberTest.php` + `ReconcileFallbackPollingPlacesTest.php`) a second time immediately after and all 78 tests passed cleanly with zero code changes in between — this is pre-existing, random-seed-dependent flakiness in a test file/fixture this task did not modify in that regard, out of scope per this task's boundary. Not fixed; logged here for visibility (same class of issue as the already-documented `CampaignContext` test-pollution flake in STATE.md).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `census:backfill-polling-table-number` exists and is verified via Pest, but per the plan's explicit scope boundary was NOT run against any real database (sigma-betha/Aldemar) in this task — running it against production data (mirroring the sibling `census:backfill-polling-place-id` command's precedent) is left to the user.
- Reports/widgets/the Apoyo view page that read `voters.polling_table_number` will now show real mesa numbers (instead of "Sin resolver") for every future automated resolution; already-affected historical apoyos require the backfill command to be run manually against real data.
- Manual browser verification (per standing project preference) is not required for this task — it is a pure backend/data-persistence fix with no new UI surface; the existing `ViewVoter.php` infolist (from quick task 260808-f0x) already renders `polling_table_number` correctly and was left untouched.

---
*Phase: quick-260808-jsz*
*Completed: 2026-08-08*

## Self-Check: PASSED

All 5 created/modified files confirmed present on disk; both task commits (`19ca5c4`, `de4a421`) confirmed present in git history.
