---
phase: 12-hierarchy-metadata-schema-foundation
plan: 02
subsystem: database
tags: [eloquent, migrations, pest, metadata, catalog]

# Dependency graph
requires: []
provides:
  - "metadata_keys catalog table (key unique, type enum numeric/text/date/select, options json, is_active)"
  - "user_metadata_values append-only table (user_id cascade, metadata_key_id restrict, assigned_by nullable, no unique constraint)"
  - "MetadataKey and UserMetadataValue Eloquent models with factories"
affects: [16-metadata-catalog-ui-assignment, 17-filter-sort-export-surfaces]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Superadmin-managed global reference catalog (mirrors existing Gremio pattern) — no campaign_id, DB-level restrictOnDelete backstop for 'no hard delete of keys with existing assignments'"
    - "Append-only value history table (no unique constraint on (user_id, metadata_key_id)) as a free per-assignment audit trail, instead of a separate audit log table"

key-files:
  created:
    - database/migrations/2026_08_10_120100_create_metadata_keys_table.php
    - database/migrations/2026_08_10_120200_create_user_metadata_values_table.php
    - app/Models/MetadataKey.php
    - app/Models/UserMetadataValue.php
    - database/factories/MetadataKeyFactory.php
    - database/factories/UserMetadataValueFactory.php
    - tests/Feature/MetadataCatalogSchemaTest.php
  modified: []

key-decisions:
  - "Followed D-02 exactly: user_metadata_values has no unique constraint on (user_id, metadata_key_id) — every assignment is a new row, giving native audit history for free"
  - "metadata_key_id uses restrictOnDelete() (not cascadeOnDelete()) as a DB-level backstop against deleting a catalog key with existing assignments"
  - "No JSON metadata column added to users — confirmed by a dedicated test asserting Schema::hasColumn('users', 'metadata') is false"

patterns-established:
  - "Global superadmin-managed catalog tables (metadata_keys) mirror the existing Gremio precedent: no campaign_id, unique natural key, is_active toggle"

requirements-completed: [ARTIC-04, ARTIC-05]

# Metrics
duration: 17min
completed: 2026-08-10
---

# Phase 12 Plan 02: Metadata Catalog Schema Summary

**Typed, catalog-backed `metadata_keys` + append-only `user_metadata_values` schema (numeric/text/date/select types, no JSON column on users)**

## Performance

- **Duration:** ~17 min (Task 1 commit to Task 2 commit)
- **Started:** 2026-08-10T10:18Z (after worktree provisioning)
- **Completed:** 2026-08-10T10:28Z
- **Tasks:** 2/2 completed
- **Files modified:** 7 created

## Accomplishments
- `metadata_keys` global reference catalog table (key unique, type enum, options json, is_active) mirroring the existing `Gremio` catalog pattern
- `user_metadata_values` append-only assignment table with `user_id`/`metadata_key_id`/`assigned_by` FKs and deliberately no unique constraint, giving native per-assignment audit history per D-02
- `MetadataKey` and `UserMetadataValue` Eloquent models + factories following the project's existing catalog/pivot conventions
- 6-test Pest suite (`MetadataCatalogSchemaTest`) covering columns, FK relations, key uniqueness, append-only behavior, and the explicit no-JSON-on-users guarantee

## Task Commits

Each task was committed atomically:

1. **Task 1: Create metadata_keys and user_metadata_values migrations, models, and factories** - `8e618c2` (feat)
2. **Task 2: Metadata catalog schema tests** - `0e42562` (test)

_No plan metadata commit yet — pending STATE.md/ROADMAP.md update in this same execution._

## Files Created/Modified
- `database/migrations/2026_08_10_120100_create_metadata_keys_table.php` - metadata_keys catalog table
- `database/migrations/2026_08_10_120200_create_user_metadata_values_table.php` - append-only assignment table
- `app/Models/MetadataKey.php` - MetadataKey Eloquent model (hasMany UserMetadataValue)
- `app/Models/UserMetadataValue.php` - UserMetadataValue Eloquent model (belongsTo User/MetadataKey/assignedByUser)
- `database/factories/MetadataKeyFactory.php` - factory for MetadataKey
- `database/factories/UserMetadataValueFactory.php` - factory for UserMetadataValue
- `tests/Feature/MetadataCatalogSchemaTest.php` - 6-test schema coverage suite

## Decisions Made
None beyond the plan's own D-01/D-02/D-03 references — followed the plan exactly as written, including the explicit instruction to NOT add a unique constraint on `(user_id, metadata_key_id)`.

## Deviations from Plan

None - plan executed exactly as written. All migration/model/factory/test code matches the plan's `<action>` blocks verbatim.

## Issues Encountered

**Worktree was stale and unprovisioned at session start** (pre-existing, documented class of issue in STATE.md's Blockers/Concerns):
- Worktree (`agent-a8ab1a1bce4d62ed3`) was 2 commits behind local `main` and was missing this phase's own `12-01-PLAN.md`/`12-02-PLAN.md`, plus `vendor/`, `.env`, `node_modules/`, and `public/build/`.
- Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, `npm install`, `npm run build`.

**Full-suite run showed 17-18 pre-existing test failures unrelated to this plan** (`DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`, `RejectionsCountersOverviewTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `VoterResourceTest`, `IsElectionDayMiddlewareTest`) — confirmed pre-existing by re-running `VoterResourceTest` in isolation (60/60 pass) and matches the already-documented `CampaignContext` static-override test-pollution issue in STATE.md. Logged in `.planning/phases/12-hierarchy-metadata-schema-foundation/deferred-items.md`, not fixed (out of scope per this plan's boundary — none of the failing files are touched by this plan).

This plan's own test file (`MetadataCatalogSchemaTest`, 6/6) and `vendor/bin/pint --dirty --test` both pass cleanly.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `metadata_keys` and `user_metadata_values` tables are ready for Phase 16 (Metadata Catalog UI & Assignment) to build the superadmin catalog-management UI and the superior-to-subordinate assignment UI directly on top of, and for Phase 17 (Filter/Sort/Export Surfaces) to query/filter/sort against.
- `users` table schema remains completely untouched, satisfying the phase's "no JSON column on users" success criterion.
- The pre-existing `CampaignContext` test-pollution issue (Blockers/Concerns in STATE.md) remains an open, unrelated concern for a future cleanup task — not a blocker for Phase 16/17.

---
*Phase: 12-hierarchy-metadata-schema-foundation*
*Completed: 2026-08-10*

## Self-Check: PASSED

All 7 created files confirmed present on disk; both task commits (`8e618c2`, `0e42562`) confirmed present in `git log`.
