---
phase: 07-source-flag-schema-resolution-audit-trail
plan: 01
subsystem: database
tags: [eloquent, migrations, enums, filament, audit-trail, pest]

# Dependency graph
requires:
  - phase: 06-national-census-snapshot-import
    provides: national_census_records table (referenced conceptually by the SNAPSHOT enum case, no FK dependency)
provides:
  - "voters.polling_place_source (nullable, indexed enum-backed string) + voters.polling_place_resolved_at (nullable timestamp)"
  - "polling_place_resolutions append-only audit table with nullable resolved_by (D-05 headless tolerance)"
  - "PollingPlaceSource backed enum (LIVE/DB_RECONSTRUCTION/SNAPSHOT/MANUAL) with Filament HasColor/HasDescription/HasIcon/HasLabel"
  - "PollingPlaceResolution Eloquent model + factory with interactive()/reconciliation() states"
  - "Voter::pollingPlaceResolutions(): HasMany relation"
affects: [08-resilient-pollingplaceresolver-service, 10-operator-provenance-fallback-controls, 11-scheduled-reconciliation-job]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Append-only audit-history table pattern (mirrors ValidationHistory) with one intentional divergence: nullable actor FK for headless/system writes"
    - "Backed string enum implementing Filament's HasColor/HasDescription/HasIcon/HasLabel, cast directly via Eloquent casts() method"

key-files:
  created:
    - database/migrations/2026_07_24_130001_add_polling_place_source_to_voters_table.php
    - database/migrations/2026_07_24_130002_create_polling_place_resolutions_table.php
    - app/Enums/PollingPlaceSource.php
    - app/Models/PollingPlaceResolution.php
    - database/factories/PollingPlaceResolutionFactory.php
    - tests/Feature/PollingPlaceResolutionTest.php
  modified:
    - app/Models/Voter.php

key-decisions:
  - "No default value set on voters.polling_place_source — no existing voter has ever had a source, so nullable with no backfill is correct (per 07-CONTEXT.md discretion)."
  - "resolved_by is nullable + nullOnDelete (D-05) — the one place this schema diverges from ValidationHistory's non-null validated_by, tolerating Phase 11's headless reconciliation writes."
  - "polling_place_id + table_number on polling_place_resolutions are value snapshots (nullable, nullOnDelete) capturing which specific place a resolution produced, not just the source label (D-06)."
  - "resolved_via is a plain required string (D-08), not a backed enum, matching ValidationHistory.validation_type's precedent — new values addable without a migration."

patterns-established:
  - "PollingPlaceResolution mirrors ValidationHistory's shape/scopes (forVoter/byVia~byType/recent) exactly, adapted for a nullable actor."

requirements-completed: [SRC-03]

# Metrics
duration: 25min
completed: 2026-07-24
---

# Phase 7 Plan 01: Source-Flag Schema & Resolution Audit Trail Summary

**Persisted `polling_place_source`/`polling_place_resolved_at` on `voters` plus an append-only `polling_place_resolutions` audit table and `PollingPlaceResolution` model, tolerating a nullable headless actor for automated reconciliation writes.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-07-24T13:03:00Z
- **Completed:** 2026-07-24T13:28:00Z
- **Tasks:** 2 completed
- **Files modified:** 7 (6 created, 1 modified)

## Accomplishments
- `voters` table gained a nullable, indexed, default-less `polling_place_source` (backed by the new `PollingPlaceSource` enum) + `polling_place_resolved_at` timestamp
- New `polling_place_resolutions` append-only audit table records `previous_source -> new_source`, a `polling_place_id`/`table_number` value snapshot, and a nullable `resolved_by` actor tolerant of headless/system writes
- `PollingPlaceResolution` Eloquent model + factory (with `interactive()`/`reconciliation()` states) mirror the existing `ValidationHistory` precedent exactly, with the one intentional actor-nullability divergence
- `Voter::pollingPlaceResolutions(): HasMany` wires the new relation alongside the existing `validationHistories()`
- Full Pest feature suite (19 tests, 35 assertions) covers schema, enum casts, relations, the D-05 nullable-actor tolerance, scopes, and cascade/nullOnDelete behavior — all green

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrations + PollingPlaceSource enum (schema contracts)** - `8de053b` (feat)
2. **Task 2: PollingPlaceResolution model + factory + Voter wiring + feature test** - `83657f9` (test, RED) then `37f3f49` (feat, GREEN)

**Plan metadata:** (this commit, docs: complete plan)

_Note: Task 2 used TDD — RED (failing test) then GREEN (implementation), no REFACTOR commit needed._

## Files Created/Modified
- `database/migrations/2026_07_24_130001_add_polling_place_source_to_voters_table.php` - Adds nullable indexed `polling_place_source` + `polling_place_resolved_at` to `voters`, no default
- `database/migrations/2026_07_24_130002_create_polling_place_resolutions_table.php` - Creates the append-only `polling_place_resolutions` audit table (D-05/D-06/D-07/D-08 column shapes)
- `app/Enums/PollingPlaceSource.php` - Backed string enum (LIVE/DB_RECONSTRUCTION/SNAPSHOT/MANUAL) implementing Filament's HasColor/HasDescription/HasIcon/HasLabel
- `app/Models/PollingPlaceResolution.php` - Eloquent model: voter()/pollingPlace()/resolver() belongsTo, forVoter/byVia/recent scopes, enum casts
- `database/factories/PollingPlaceResolutionFactory.php` - Factory with interactive()/reconciliation() states for seeding audit rows (used by this and later phases 8/11)
- `tests/Feature/PollingPlaceResolutionTest.php` - 19 Pest cases covering SRC-03 in full
- `app/Models/Voter.php` - Added `polling_place_source`/`polling_place_resolved_at` to fillable+casts, added `pollingPlaceResolutions(): HasMany`

## Decisions Made
None beyond what 07-CONTEXT.md already specified (D-01 through D-08) — plan executed exactly as written against those decisions.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**Stale worktree required manual sync before any work could begin** (same class of issue logged in Phase 06's SUMMARY as a "concern carried forward"): this execution's git worktree (`worktree-agent-ae0adbb8ac28629ba`) was checked out at an old commit (`78c1f69`, pre-dating Phase 6/7 entirely) with no `vendor/`, no `.env`, and no `.planning/phases/07-*` directory. Resolved by:
1. `git merge --ff-only 66e8127` (main's HEAD was a fast-forward descendant of the worktree's stale branch tip)
2. `composer install` (vendor was gitignored and absent)
3. Copying the project's `.env` (gitignored, mysql-backed local dev DB) into the worktree so `php artisan migrate --env=testing` had a working DB connection (verified reachable via a raw `mysqli` connect test first)

All of this was environment setup, not plan-scope work — no plan files were affected, and it required no user decision (Rule 3: auto-fix blocking issue).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 8 (Resilient PollingPlaceResolver Service) can now read/write `voters.polling_place_source`/`polling_place_resolved_at` and create `PollingPlaceResolution` rows via the interactive-actor path
- Phase 10 (Operator Provenance & Fallback Controls) can read `Voter::pollingPlaceResolutions()` for UI history display and use `PollingPlaceSource`'s Filament interfaces directly for a badge
- Phase 11 (Scheduled Reconciliation Job) can create `PollingPlaceResolution` rows with `resolved_by = null` and `resolved_via = 'reconciliation'` — verified working via the factory's `reconciliation()` state and the corresponding test case
- No blockers carried forward from this plan; the stale-worktree sync issue is now a recurring, known, quick-to-resolve pattern (documented here and in Phase 06's SUMMARY) rather than a blocker

---
*Phase: 07-source-flag-schema-resolution-audit-trail*
*Completed: 2026-07-24*

## Self-Check: PASSED

All 7 created/modified files verified present on disk. All 3 task commit hashes (`8de053b`, `83657f9`, `37f3f49`) verified present in git log.
