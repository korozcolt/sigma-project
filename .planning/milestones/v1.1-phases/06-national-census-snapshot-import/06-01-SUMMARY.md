---
phase: 06-national-census-snapshot-import
plan: 01
subsystem: database
tags: [laravel, eloquent, artisan, csv-import, lazycollection, upsert, iso-8859-1]

# Dependency graph
requires: []
provides:
  - "national_census_records table: cédula-unique, nullable polling_place_id FK, no campaign_id (isolated from campaign scope)"
  - "NationalCensusRecord Eloquent model with pollingPlace() belongsTo enrichment"
  - "NationalCensusRecordFactory for seeding lookups in future phases"
  - "census:import-national Artisan command: streaming ISO-8859-1 import, divipol-join enrichment, idempotent upsert, unmatched-% report"
affects: [08-resilient-pollingplaceresolver-service]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "In-memory keyed-map divipol join (mirrors PollingPlaceSeeder): pre-load polling_places once, resolve per-row in O(1), no per-row query"
    - "Streaming LazyCollection + chunked deduped upsert for large CSV imports (avoids loading 216K rows into memory)"
    - "Per-line mb_convert_encoding for Latin-1 -> UTF-8 transcoding during streaming read"

key-files:
  created:
    - database/migrations/2026_07_24_123559_create_national_census_records_table.php
    - app/Models/NationalCensusRecord.php
    - database/factories/NationalCensusRecordFactory.php
    - app/Console/Commands/ImportNationalCensus.php
    - tests/Feature/ImportNationalCensusTest.php
    - tests/fixtures/census/national-sample.csv
  modified:
    - .gitattributes

key-decisions:
  - "national_census_records has NO campaign_id and NO HasCampaignContext trait — deliberate isolation from campaign-scoped data (national reference data)"
  - "Divipol codes stored as unsignedSmallInteger (not string) to match polling_places' join-key column types exactly"
  - "Fixture CSV marked -text in .gitattributes so the repo's `text=auto eol=lf` rule never rewrites its CRLF terminators on checkout"

patterns-established:
  - "Streaming Latin-1 CSV import via LazyCollection + fgets + mb_convert_encoding + chunked upsert — reusable for future large reference-data imports"

requirements-completed: [CENSO-02, CENSO-03]

# Metrics
duration: ~20min
completed: 2026-07-24
---

# Phase 06 Plan 01: National Census Snapshot Import Summary

**`census:import-national` streams the 216K-row ISO-8859-1 national census CSV into a new cédula-indexed `national_census_records` table, enriching each row with a `polling_place_id` FK via an in-memory divipol join, upserting idempotently, and reporting the unmatched-divipol percentage without ever aborting.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-07-24T12:33:00Z (approx)
- **Completed:** 2026-07-24T12:42:00Z (approx)
- **Tasks:** 3 completed
- **Files modified:** 7 (6 created, 1 modified)

## Accomplishments
- New `national_census_records` table isolated from campaign scope (no `campaign_id`), keyed uniquely on `document_number`, with a nullable `polling_place_id` FK
- `NationalCensusRecord` model exposing `pollingPlace()` for department/municipality/address enrichment
- `census:import-national` command: streams the Latin-1 CSV via `LazyCollection`, resolves polling places from a pre-loaded keyed map (no per-row queries), dedupes duplicate cédulas per chunk (last row wins), upserts idempotently, and reports the unmatched % on completion
- Full Pest feature test suite (7 tests, 20 assertions) covering CENSO-02 (count, FK enrichment, encoding, isolation), CENSO-03 (null FK + reported %), D-05 (last-wins), and D-06 (idempotent re-run) — all green

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration + NationalCensusRecord model + factory** - `5dc784d` (feat)
2. **Task 2: ISO-8859-1 fixture + failing feature test (TDD RED)** - `01e490c` (test)
   - Follow-up fix for CRLF preservation - `5f2485a` (fix)
3. **Task 3: Implement census:import-national command (TDD GREEN)** - `2805307` (feat)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified
- `database/migrations/2026_07_24_123559_create_national_census_records_table.php` - Isolated national reference table schema
- `app/Models/NationalCensusRecord.php` - Model with `pollingPlace()` belongsTo, `casts()`, timestamps disabled
- `database/factories/NationalCensusRecordFactory.php` - Factory for seeding lookups in this/later phases
- `app/Console/Commands/ImportNationalCensus.php` - Streaming importer command
- `tests/Feature/ImportNationalCensusTest.php` - Feature test covering all plan success criteria
- `tests/fixtures/census/national-sample.csv` - ISO-8859-1/CRLF fixture (matching, accented, unmatched, duplicate rows)
- `.gitattributes` - Marks the fixture `-text` to preserve exact CRLF/Latin-1 bytes on checkout

## Decisions Made
- Divipol codes stored as `unsignedSmallInteger` (matches `polling_places`' column types exactly, per plan's explicit refinement over the original architecture doc's `string` suggestion)
- ISO-8859-1 to UTF-8 conversion done per-line with `mb_convert_encoding` during the streaming read (simpler than a one-time `iconv` pre-pass, per CONTEXT.md's "Claude's discretion" note)
- Fixture CSV marked `-text` in `.gitattributes` to prevent the repo-wide `text=auto eol=lf` rule from normalizing away the CRLF terminators the encoding test depends on

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Synced stale worktree branch to `main` before any file edits**
- **Found during:** Task 1 setup
- **Issue:** The assigned worktree (`worktree-agent-ae9f012d50fef4e54`) was checked out at a commit that predated Phase 06 entirely — no `.planning/phases/06-*` files existed, and STATE.md/PROJECT.md were stale v1.0-era content. The worktree branch tip was a strict ancestor of `main` with zero divergent commits.
- **Fix:** Fast-forwarded the worktree branch to `main` (`git merge --ff-only main`), then ran `composer install` (vendor was gitignored and absent) and created an empty `database/database.sqlite` + copied the untracked `database/external-data/censo_decoded_202310210734.csv` data file (matching main's untracked state) so `php artisan migrate --env=testing` and the command's default path would work.
- **Files modified:** None tracked by git beyond the plan's own scope; this was environment setup, not code.
- **Verification:** `git log` confirmed zero unique commits lost; migrations and tests ran clean afterward.
- **Committed in:** N/A (git merge + local environment setup, not a content commit)

**2. [Rule 3 - Blocking] Marked fixture CSV `-text` in `.gitattributes`**
- **Found during:** Task 2, right after committing the fixture
- **Issue:** `git commit` warned "CRLF will be replaced by LF the next time Git touches it" — the repo's `* text=auto eol=lf` rule would silently strip the fixture's required CRLF terminators on a future checkout, undermining the encoding-fixture's intent (even though the specific automated `grep "ISO-8859"` check would still pass either way).
- **Fix:** Added `tests/fixtures/census/national-sample.csv -text` to `.gitattributes` and renormalized the index entry. Verified byte-for-byte identical content before/after (`cmp` confirmed no bytes changed — only git's internal normalization metadata changed).
- **Files modified:** `.gitattributes`
- **Verification:** `file tests/fixtures/census/national-sample.csv` still reports "ISO-8859 text, with CRLF line terminators"; `git show HEAD:...` byte-identical to working tree.
- **Committed in:** `5f2485a`

---

**Total deviations:** 2 auto-fixed (both Rule 3 - blocking environment/tooling issues, no code-behavior changes to the plan's scope)
**Impact on plan:** No scope creep — both fixes were prerequisites for executing the plan at all in the assigned worktree, or for the fixture to reliably stay Latin-1/CRLF across future checkouts.

## Issues Encountered
- Running the full `php artisan test` suite (not scoped to this plan) in the freshly-synced worktree surfaced widespread pre-existing failures unrelated to this plan (Survey/User/TopCoordinators Filament tests, a PHP memory-exhaustion fatal partway through) — logged to `.planning/phases/06-national-census-snapshot-import/deferred-items.md` as out of scope per the scope boundary rule. This plan's own test file is fully green in isolation (`php artisan test --filter=ImportNationalCensus` → 7 passed, 20 assertions, 0 failed).
- A pre-existing `.env`-missing warning appears on every test in this worktree (including unrelated pre-existing tests) — environmental, not introduced by this plan.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

`national_census_records` now exists as the last-resort fallback data source Phase 8's `PollingPlaceResolver` will read from. This plan intentionally does NOT wire the snapshot into any lookup cascade and builds no UI — that is explicitly Phase 8 (resolver) and Phase 10 (operator UI) scope.

**Concern carried forward:** the worktree used for this execution required manual `git merge --ff-only main` + `composer install` + local `.sqlite`/CSV setup before any plan work could begin. If Phase 07 (parallel wave, no dependency on this phase) is executed in a similarly stale worktree, the same sync steps will likely be needed there too.

## Self-Check: PASSED

All created files verified present on disk; all 4 task commit hashes (`5dc784d`, `01e490c`, `5f2485a`, `2805307`) verified present in git history.

---
*Phase: 06-national-census-snapshot-import*
*Completed: 2026-07-24*
