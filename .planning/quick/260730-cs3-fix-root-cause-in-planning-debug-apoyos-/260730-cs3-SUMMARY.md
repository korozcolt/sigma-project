---
phase: quick
plan: 260730-cs3
subsystem: voter-validation
tags: [laravel, filament, pest, census-validation, polling-place-resolution, eloquent]

requires:
  - phase: 11-scheduled-reconciliation-job
    provides: PollingPlaceResolver::resolveAutomated() cascade, registraduria_lookups permanent cache, ReconcileFallbackPollingPlaces
provides:
  - Unified census + polling-place resolution in one VoterValidationService pass
  - RevalidationRun progress-tracking model/table for headless jobs
  - census:remediate-misrejected operator command
  - RevalidationProgressWidget non-blocking UI banner
affects: [voter-validation, census-reconciliation, filament-voters-resource]

tech-stack:
  added: []
  patterns:
    - "Headless/queued jobs that call VoterValidationService::validateAndUpdate() must tolerate a null Auth::id() — validation_histories.validated_by is now nullable (cascadeOnDelete preserved)."
    - "Constructor-promoted service dependency with an inert default (`PollingPlaceResolver $resolver = new PollingPlaceResolver([])`) lets direct `new Service()` test instantiation stay safe/offline while container-resolved instances get the real, provider-bound adapters."

key-files:
  created:
    - app/Models/RevalidationRun.php
    - app/Console/Commands/RemediateMisrejectedCensusVoters.php
    - app/Filament/Widgets/RevalidationProgressWidget.php
    - resources/views/filament/widgets/revalidation-progress-widget.blade.php
    - database/migrations/2026_07_30_000000_create_revalidation_runs_table.php
    - database/migrations/2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php
    - tests/Feature/CensusUnifiedResolutionTest.php
    - tests/Feature/RevalidationCoverageTest.php
    - tests/Feature/RemediateMisrejectedCensusVotersTest.php
    - tests/Feature/RevalidationProgressWidgetTest.php
  modified:
    - app/Services/VoterValidationService.php
    - app/Jobs/DispatchCensusRevalidation.php
    - app/Jobs/ReconcileFallbackPollingPlaces.php
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - app/Filament/Resources/Voters/Pages/ListVoters.php
    - tests/Feature/VoterValidationServiceTest.php
    - tests/Feature/Services/VoterValidationServiceTest.php
    - tests/Feature/VoterCensusValidationTest.php
    - tests/Feature/Jobs/DispatchCensusRevalidationTest.php
    - tests/Feature/Leader/RegisterVoterCensusWarningTest.php
    - tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php
    - tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php

key-decisions:
  - "VoterValidationService::validateAgainstCensus() delegates entirely to PollingPlaceResolver::resolveAutomated() + a national_identity_records existence check; the orphaned per-campaign census_records table is no longer consulted anywhere in the validation path."
  - "REJECTED_CENSUS is no longer produced by updateVoterStatus() — unresolved voters land on CENSUS_NOT_FOUND so they re-enter the reconciliation cycle instead of dead-ending."
  - "No-downgrade guard added: VERIFIED_REGISTRADURIA/VERIFIED_CALL/CONFIRMED/VOTED/DID_NOT_VOTE are never touched by census validation, regardless of found/not-found."
  - "DispatchCensusRevalidation processes voters inline (no more per-voter ValidateVoterAgainstCensus job — deleted) and writes a RevalidationRun progress record; ReconcileFallbackPollingPlaces drops its whereNotNull('polling_place_source') guard so NULL-source voters get first-time resolution too."
  - "validation_histories.validated_by made nullable (cascadeOnDelete preserved, only nullability changed) so headless/scheduled jobs with no authenticated actor can write audit rows without crashing — this was a genuine latent bug the widened inline job now actually exercises."
  - "census:remediate-misrejected is a manual, campaign-scoped, non-scheduled operator command — NOT run against sigma-betha production as part of this task, per explicit constraint."

patterns-established:
  - "One cascade for both census-found and polling-place resolution: any future census/polling-place logic should go through PollingPlaceResolver::resolveAutomated(), not a parallel table lookup."

requirements-completed: []

duration: ~25min (coding/testing only — excludes one-time stale-worktree fast-forward + composer/npm install environment repair)
completed: 2026-07-30
---

# Quick Task 260730-cs3: Census Validation Cascade Fix + Remediation + Progress UI Summary

**Unified census validation onto `PollingPlaceResolver::resolveAutomated()` so one revalidation pass resolves both census status and polling-place source, replacing the orphaned per-campaign `census_records` table that caused 148/188 sigma-betha voters to be mass-`REJECTED_CENSUS`.**

## Performance

- **Duration:** ~25 min of coding/test iteration (4 task commits between 10:10 and 10:30 local time); additional one-time overhead fixing a stale git worktree (fast-forward merge, `composer install`, `.env` copy, `npm install && npm run build`) before any code could be written.
- **Tasks:** 4/4 completed
- **Files modified/created:** 22 (10 created, 12 modified)

## Accomplishments

- `VoterValidationService` now determines "found in census" via the same automated cascade the polling-place engine uses (permanent `registraduria_lookups` cache → live adapters → national census snapshot), plus a `national_identity_records` existence check — never the orphaned `census_records` table.
- A single `validateAndUpdate()` call now resolves BOTH `status` and `polling_place_source` for a voter in one pass.
- `REJECTED_CENSUS` is no longer produced by the validation path; unresolved voters land on the re-triable `CENSUS_NOT_FOUND` instead.
- Added a no-downgrade guard so census validation can never clobber `VERIFIED_REGISTRADURIA`, `VERIFIED_CALL`, `CONFIRMED`, `VOTED`, or `DID_NOT_VOTE`.
- `DispatchCensusRevalidation` widened its selection to PENDING_REVIEW/CENSUS_NOT_FOUND/REJECTED_CENSUS OR `polling_place_source IS NULL`, processes inline, and writes a `RevalidationRun` progress record (started_at/finished_at/total/processed/changed). Deleted the now-unused per-voter `ValidateVoterAgainstCensus` job.
- `ReconcileFallbackPollingPlaces` dropped its `whereNotNull('polling_place_source')` guard so the hourly job also performs first-time resolution for NULL-source voters, not just upgrades.
- New `census:remediate-misrejected {--campaign=1} {--dry-run}` command reverts a campaign's `rejected_census` voters to `pending_review` with a `ValidationHistory` audit row — campaign-scoped, idempotent, dry-run-capable. Not scheduled; a manual operator command.
- New `RevalidationProgressWidget` — a non-blocking, `wire:poll`-based banner above the Apoyos table showing in-progress/finished revalidation-run status, scoped to the current campaign.

## Task Commits

1. **Task 1: Unify VoterValidationService onto PollingPlaceResolver cascade** - `4e5b258` (feat)
2. **Task 2: Widen revalidation + reconciliation coverage, RevalidationRun tracking** - `cf078ea` (feat)
3. **Task 3: census:remediate-misrejected command** - `cce61e6` (feat)
4. **Task 4: Non-blocking progress indicator (RevalidationProgressWidget)** - `8db8278` (feat)

_All four tasks were type="auto"; Tasks 1–3 were tdd="true" (tests written/updated alongside implementation, verify command run to green before moving on)._

## Files Created/Modified

- `app/Services/VoterValidationService.php` - Rewritten to delegate census-found determination to `PollingPlaceResolver::resolveAutomated()` + `NationalIdentityRecord` existence check; no-downgrade guard; `documentExistsInCensus()` now checks `registraduria_lookups`/`national_census_records`/`national_identity_records`.
- `app/Jobs/DispatchCensusRevalidation.php` - Widened selection, inline processing via `VoterValidationService`, `RevalidationRun` bookkeeping, `campaignId` param added.
- `app/Jobs/ReconcileFallbackPollingPlaces.php` - Dropped the `whereNotNull('polling_place_source')` guard.
- `app/Jobs/ValidateVoterAgainstCensus.php` - Deleted (only caller was `DispatchCensusRevalidation`, which no longer dispatches it).
- `app/Models/RevalidationRun.php` + `database/factories/RevalidationRunFactory.php` + migration - New progress-tracking model.
- `app/Console/Commands/RemediateMisrejectedCensusVoters.php` - New remediation command.
- `app/Filament/Widgets/RevalidationProgressWidget.php` + its blade view - New progress banner widget.
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - `revalidateLeaderVoters` action now passes `campaignId`.
- `app/Filament/Resources/Voters/Pages/ListVoters.php` - Registers `RevalidationProgressWidget` as a header widget.
- `database/migrations/2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php` - Made `validated_by` nullable (cascadeOnDelete preserved).
- Test files: `CensusUnifiedResolutionTest.php`, `RevalidationCoverageTest.php`, `RemediateMisrejectedCensusVotersTest.php`, `RevalidationProgressWidgetTest.php` (new); `VoterValidationServiceTest.php` (both copies), `VoterCensusValidationTest.php`, `Jobs/DispatchCensusRevalidationTest.php`, plus three Volt-component test files whose `CensusRecord` fixtures silently stopped registering as "found" (reconciled — see Deviations).

## Decisions Made

See `key-decisions` in frontmatter above. Most significant: (1) census_records is fully retired from the validation path (kept on disk, unreferenced, per CONTEXT.md's discretion); (2) REJECTED_CENSUS is no longer a terminal/dead-end state; (3) `validated_by` had to become nullable for the headless job to function at all — a genuine correctness fix, not a nice-to-have.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Three pre-existing Volt-component tests silently broke because their `CensusRecord` fixtures stopped registering as "found"**
- **Found during:** Task 1
- **Issue:** `tests/Feature/Leader/RegisterVoterCensusWarningTest.php`, `tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php`, and `tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php` seeded `CensusRecord` rows expecting `documentExistsInCensus()` to return true; since it no longer queries `census_records`, these tests started failing (not silently in production, but the plan didn't enumerate these files).
- **Fix:** Re-seeded the "found but not Registraduría-verified" fixtures as `NationalIdentityRecord` rows (the correct new "found in census but no polling place" source), preserving each test's original intent.
- **Files modified:** the three files listed above.
- **Verification:** All three files pass individually and the specific tests no longer appear in the full-suite failure list.
- **Committed in:** `4e5b258`

**2. [Rule 3 - Blocking] `validation_histories.validated_by` was NOT NULL, crashing every real (non-`Bus::fake`) execution of the widened headless job**
- **Found during:** Task 2, while running `RevalidationCoverageTest.php` for the first time.
- **Issue:** `DispatchCensusRevalidation` now calls `VoterValidationService::validateAndUpdate()` inline with no authenticated actor (`Auth::id()` is `null`), but `validated_by` had a NOT NULL constraint — every insert fatal-errored with a SQLite/MySQL integrity-constraint violation.
- **Fix:** New migration making `validated_by` nullable, mirroring the existing `polling_place_resolutions.resolved_by` nullable precedent (Phase 7 D-05 / Phase 11 D-04).
- **Files modified:** `database/migrations/2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php`.
- **Verification:** `RevalidationCoverageTest.php` and `DispatchCensusRevalidationTest.php` pass.
- **Committed in:** `cf078ea` (then corrected in `8db8278` — see below).

**3. [Rule 1 - Bug] Self-caused regression: the Task 2 migration accidentally switched the FK's delete behavior from `cascadeOnDelete()` to `nullOnDelete()`**
- **Found during:** Task 4, running the full suite for a clean baseline.
- **Issue:** The migration in item 2 above copied `polling_place_resolutions`' `nullOnDelete()` pattern wholesale, silently breaking `tests/Feature/ValidationHistoryTest.php`'s "deleting validator user cascades delete validation histories" contract test.
- **Fix:** Nullability and delete-behavior are orthogonal — corrected the migration to keep `cascadeOnDelete()` and only change nullability (a NULL `validated_by` has nothing to cascade from, so this is fully compatible with both requirements).
- **Files modified:** `database/migrations/2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php`.
- **Verification:** `ValidationHistoryTest.php` passes again; full suite re-run confirmed no other regressions from this fix.
- **Committed in:** `8db8278`

---

**Total deviations:** 3 auto-fixed (2x Rule 1, 1x Rule 3).
**Impact on plan:** All three were necessary for correctness (items 1, 3) or to unblock the widened headless job from crashing in real use (item 2). No scope creep — no architectural changes, no new tables beyond the plan's own `revalidation_runs`.

## Issues Encountered

- **Stale git worktree.** The assigned worktree was checked out at a commit predating this quick task's own PLAN/CONTEXT docs, and was missing `vendor/`, `.env`, `node_modules/`, and `public/build/` entirely (same class of issue STATE.md already documents for prior phases). Fixed by confirming the worktree's HEAD was a fast-forward ancestor of `main`, `git merge --ff-only`, copying `.env` from the main checkout, then `composer install` and `npm install && npm run build`. This is why the full test suite initially showed ~41 unrelated failures (missing Vite manifest) before any of this task's code was touched.
- **Pre-existing, unrelated full-suite test-pollution flakiness.** A subset of Filament report-table tests (`DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `VoterResourceTest`) and occasionally `IsElectionDayMiddlewareTest`/this task's own new `RevalidationProgressWidgetTest` intermittently fail ONLY when run as part of the full suite (never alone, never paired with an arbitrary other file tried). Confirmed via `git stash` that these fail identically on the pre-Task-1 baseline — this is the `CampaignContext` static-override test-pollution issue already logged in `.planning/STATE.md`. Documented in `.planning/quick/260730-cs3-fix-root-cause-in-planning-debug-apoyos-/deferred-items.md` rather than fixed (out of scope — unrelated files, pre-existing).

## User Setup Required

None - no external service configuration required.

**Manual follow-up still owed (explicitly out of this quick task's scope per its constraints):** a human with production access must run `php artisan census:remediate-misrejected --campaign=1` (no `--dry-run`) against sigma-betha to actually revert the 148 already-misrejected voters. This task built, tested, and dry-run-verified the command locally only — it was never run against any live/production server, per explicit instruction.

## Next Phase Readiness

- Census validation, polling-place resolution, revalidation, reconciliation, remediation, and the progress UI are all in place and covered by Pest tests (46 tests across the 7 core files pass; full suite is 1098 passed / 9 pre-existing-and-unrelated failures out of 1107).
- Remaining manual step: run the real (non-dry-run) `census:remediate-misrejected --campaign=1` against sigma-betha production, and deploy this change so the scheduled `census:reconcile-validation`/`census:reconcile-live` jobs pick up the new unified cascade.
- No blockers for future work; the `CensusRecord`/`CensusImporter` code is now vestigial and marked as such in code comments, left in place per CONTEXT.md's explicit discretion.

---
*Phase: quick*
*Plan: 260730-cs3*
*Completed: 2026-07-30*

## Self-Check: PASSED

All 17 claimed created/modified files exist on disk; `app/Jobs/ValidateVoterAgainstCensus.php` confirmed deleted; all 4 task commit hashes (`4e5b258`, `cf078ea`, `cce61e6`, `8db8278`) confirmed present in git history.
