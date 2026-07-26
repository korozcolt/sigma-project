---
phase: quick
plan: 260726-ifp
subsystem: voters
tags: [livewire, volt, filament, laravel-queue, laravel-schedule, pest]

requires:
  - phase: 11-scheduled-reconciliation-job
    provides: "Precedent scheduled-command pattern (census:reconcile-live) and the orphaned ValidateVoterAgainstCensus job this plan finally dispatches"
provides:
  - "VoterStatus::CENSUS_NOT_FOUND enum case, wired into the Filament Voters table badge and status filter"
  - "Non-blocking local (no live call) census cross-check on the Líder register-voter form, triggered on document-number blur"
  - "App\\Jobs\\DispatchCensusRevalidation (optionally leader-scoped) that queues the existing orphaned ValidateVoterAgainstCensus job"
  - "New census:reconcile-validation Artisan command, scheduled hourly in routes/console.php"
  - "Admin/reviewer-only Filament header action on the Voters table to revalidate one líder's apoyos on demand"
affects: [leader-register-voter, filament-voters-resource, census-reconciliation]

tech-stack:
  added: []
  patterns:
    - "Local-only census cross-check reuses VoterValidationService::documentExistsInCensus() both on blur (UX warning) and again fresh at save() time (authoritative status), never trusting client-side state alone"
    - "Two independent reconciliation surfaces (hourly schedule + on-demand admin action) both funnel through one shared App\\Jobs\\DispatchCensusRevalidation job, which itself just re-dispatches the pre-existing orphaned ValidateVoterAgainstCensus job per voter"
    - "Testing a Table-level ->headerActions() action requires assertTableActionVisible/Hidden + callTableAction (not the page-level assertActionVisible/callAction used for page ->headerActions())"

key-files:
  created:
    - app/Jobs/DispatchCensusRevalidation.php
    - app/Console/Commands/ReconcileCensusValidations.php
    - tests/Feature/Leader/RegisterVoterCensusWarningTest.php
    - tests/Feature/Jobs/DispatchCensusRevalidationTest.php
    - tests/Feature/Filament/RevalidateLeaderVotersActionTest.php
  modified:
    - app/Enums/VoterStatus.php
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - resources/views/livewire/leader/register-voter.blade.php
    - routes/console.php
    - tests/Feature/Filament/VoterResourceTest.php

key-decisions:
  - "CENSUS_NOT_FOUND colored 'warning' (not 'danger') to stay visually distinct from REJECTED_CENSUS — a soft, reviewable flag, not a hard rejection, matching CORRECTION_REQUIRED's urgency level per the plan's explicit rationale"
  - "save() recomputes documentExistsInCensus() fresh rather than trusting the blur-set censusNotFoundWarning property, so a paste-then-submit flow that never fires the blur hook still gets the correct status"
  - "DispatchCensusRevalidation queries both PENDING_REVIEW and CENSUS_NOT_FOUND statuses (not just the new one) so the hourly job also catches voters that were never re-checked before this plan existed"

requirements-completed: []

duration: 40min
completed: 2026-07-26
---

# Quick Task 260726-ifp: Cruce Local Contra Censo al Registrar Apoyo Summary

**Local (no live Registraduría call) census cross-check on the Líder's Registrar Apoyo form with a non-blocking blur warning, a new `CENSUS_NOT_FOUND` status, and two background reconciliation surfaces (hourly schedule + admin/reviewer on-demand per-líder action) that both reuse the previously-orphaned `ValidateVoterAgainstCensus` job.**

## Performance

- **Duration:** ~40 min
- **Started:** 2026-07-26T18:20:00Z (approx, per worktree fast-forward/setup)
- **Completed:** 2026-07-26
- **Tasks:** 4 (plus 1 auto-fixed Pint formatting deviation)
- **Files modified:** 10 (5 created, 5 modified)

## Accomplishments

- `VoterStatus::CENSUS_NOT_FOUND` added with all four required match arms (label/color/icon/description), rendering and filtering correctly in the Filament Voters table without any `UnhandledMatchError`.
- `register-voter.blade.php`'s `updatedDocumentNumber()` blur hook runs a strictly local `VoterValidationService::documentExistsInCensus()` check (no network call) and shows/hides a non-blocking amber banner; "Guardar Apoyo" is never disabled.
- `save()` independently re-checks the local census at submit time and persists `CENSUS_NOT_FOUND` (warning ignored) vs the unchanged default `PENDING_REVIEW` (found locally).
- New `App\Jobs\DispatchCensusRevalidation` (optional `leaderId`) re-dispatches the existing orphaned `ValidateVoterAgainstCensus` job for every `PENDING_REVIEW`/`CENSUS_NOT_FOUND` voter, optionally scoped to one líder's `registered_by`.
- New `census:reconcile-validation` Artisan command (`DispatchCensusRevalidation::dispatchSync()`) scheduled hourly in `routes/console.php` with the same `withoutOverlapping(10)` guard as the `census:reconcile-live` precedent.
- New role-gated `revalidateLeaderVoters` header action on the Filament Voters table lets super_admin/admin_campaign/reviewer trigger a specific líder's revalidation on demand, with a success notification; hidden from leader.
- 13 new Pest tests across 4 new test files plus 2 tests added to the existing `VoterResourceTest.php`, all passing; full targeted regression suite (286 tests across Filament/Leader/Jobs/VoterTest/ApoyoDuplicateSequenceTest/Services) passes with zero regressions.

## Task Commits

Each task was committed atomically:

1. **Task 1: New VoterStatus::CENSUS_NOT_FOUND case, wired into the Filament Voters table** - `fe4148f` (feat)
2. **Task 2: Local census cross-check on the Líder's register-voter form** - `8ddc7ee` (feat)
3. **Task 3: Background reconciliation — DispatchCensusRevalidation job + scheduled command** - `384cb70` (feat)
4. **Task 4: Admin/reviewer manual "revalidate one líder's apoyos" action** - `cc74fd2` (feat)
5. **Auto-fix: Pint formatting on register-voter.blade.php** - `0952232` (style)

## Files Created/Modified

- `app/Enums/VoterStatus.php` - New `CENSUS_NOT_FOUND` case with label/color/icon/description
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - Status column color match handles the new case; new `revalidateLeaderVoters` role-gated headerAction
- `tests/Feature/Filament/VoterResourceTest.php` - 2 new tests: renders + filters `CENSUS_NOT_FOUND`
- `resources/views/livewire/leader/register-voter.blade.php` - `updatedDocumentNumber()` blur hook, `censusNotFoundWarning` property, inline banner, `save()` conditional status
- `tests/Feature/Leader/RegisterVoterCensusWarningTest.php` - 5 new tests covering blur warning show/hide, incomplete-input no-op, both save-time outcomes
- `app/Jobs/DispatchCensusRevalidation.php` - New ShouldQueue job, optional `leaderId`, re-dispatches `ValidateVoterAgainstCensus` per matching voter
- `app/Console/Commands/ReconcileCensusValidations.php` - New `census:reconcile-validation` command
- `routes/console.php` - Hourly schedule line for `census:reconcile-validation` with `withoutOverlapping(10)`
- `tests/Feature/Jobs/DispatchCensusRevalidationTest.php` - 4 new tests covering status filtering, leader scoping, the schedule line, and command-to-job wiring
- `tests/Feature/Filament/RevalidateLeaderVotersActionTest.php` - 2 new tests (dataset-driven across 3 visible roles + 1 hidden-role case)

## Decisions Made

- Followed the plan's interfaces exactly for `VoterValidationService::documentExistsInCensus()` and `ValidateVoterAgainstCensus` reuse — no modifications to either, both treated as fixed contracts per the plan's explicit "DO NOT MODIFY" annotations.
- Used `assertTableActionVisible`/`assertTableActionHidden`/`callTableAction` (not the page-level `assertActionVisible`/`callAction` the plan's example referenced from `ApoyoDuplicateSequenceTest`) for `revalidateLeaderVoters`, since it's registered via the `Table`'s own `->headerActions()`, not `ListVoters` page's `->headerActions()`. `ApoyoDuplicateSequenceTest`'s `reassignDuplicateOwner` action lives on `EditVoter`'s page-level actions, a different registration point than this table-level action — confirmed via Filament's `TestsActions` trait source before adjusting the test helper calls.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Filament table-header-action test helpers needed table-scoped assertions, not page-scoped ones**
- **Found during:** Task 4 (writing `RevalidateLeaderVotersActionTest`)
- **Issue:** The plan's action snippet mirrored `ApoyoDuplicateSequenceTest`'s `assertActionVisible`/`callAction`/`assertActionHidden` pattern, but those methods only resolve page-level `->headerActions()` (e.g. `ListVoters`'s own `Action::make('import')`), not a `Table`'s own `->headerActions([...])` — calling them against `revalidateLeaderVoters` failed with "null is not an instance of Action" even for the hidden-role assertion.
- **Fix:** Switched to `assertTableActionVisible('revalidateLeaderVoters')`, `callTableAction('revalidateLeaderVoters', data: [...])`, and `assertTableActionHidden('revalidateLeaderVoters')` — the table-scoped equivalents from Filament's `TestsActions` trait (`vendor/filament/tables/src/Testing/TestsActions.php`).
- **Files modified:** `tests/Feature/Filament/RevalidateLeaderVotersActionTest.php`
- **Verification:** `php artisan test --filter=RevalidateLeaderVotersActionTest` — 4/4 pass (dataset-driven across super_admin/admin_campaign/reviewer + 1 hidden-leader case)
- **Committed in:** `cc74fd2` (Task 4 commit)

---

**2. [Rule 3 - Blocking] Missing Vite build manifest in the freshly fast-forwarded worktree**
- **Found during:** Pre-Task-1 environment setup / first `LeaderAppTest` regression run
- **Issue:** `public/build/manifest.json` didn't exist in this worktree (never built here), causing every full-page HTTP test (`get(route(...))`) to fail with a Vite manifest exception unrelated to this plan's code changes.
- **Fix:** Copied the already-built `public/build/` directory from the main checkout (`public/build` is gitignored, not a plan deliverable, and the main checkout's build was already current).
- **Files modified:** none (gitignored build artifacts only, not committed)
- **Verification:** `php artisan test --filter=LeaderAppTest` — 30/30 pass after the copy
- **Committed in:** N/A (gitignored, not part of any task commit)

---

**3. [Rule 1 - Bug] Pre-existing PSR-12 formatting gaps in register-voter.blade.php's PHP block**
- **Found during:** Post-Task-4 thorough Pint pass across all files touched by this plan (broader than the `--dirty`-only check, which reported 0 issues since everything was already committed)
- **Issue:** `vendor/bin/pint --test` against the full diff range surfaced pre-existing style gaps in the same file this plan already modified (property declarations without blank lines, `use function` statement ordering, missing blank line before `return`) — not introduced by this plan's edits, but living in the same file.
- **Fix:** Ran `vendor/bin/pint` (auto-fix) on the touched-files list; re-verified all Task 2 tests (`RegisterVoterCensusWarningTest`, `LeaderAppTest`) still pass after the reformat.
- **Files modified:** `resources/views/livewire/leader/register-voter.blade.php`
- **Verification:** `php artisan test --filter='RegisterVoterCensusWarningTest|LeaderAppTest'` — 35/35 pass; `vendor/bin/pint --test` on all 10 touched PHP files — clean
- **Committed in:** `0952232` (separate style commit, per CLAUDE.md's mandatory `vendor/bin/pint --dirty` rule)

---

**Total deviations:** 3 auto-fixed (1 test-helper bug fix, 1 blocking environment gap, 1 pre-existing style gap). None were scope creep — all were necessary to get the plan's own deliverables correctly tested/verified.
**Impact on plan:** No functional changes to the plan's shipped behavior; all three deviations were test-infrastructure or code-style corrections.

## Issues Encountered

- **Stale worktree at session start:** checked out at `403e0f0`, missing `vendor/`, `.env`, and Phase 11's plans/migrations already merged into main. Confirmed `403e0f0` is a fast-forward ancestor of main's `a7c5e7d` HEAD; resolved via `git merge --ff-only`, `composer install --no-interaction`, and copying `.env` from the main checkout. DB was already migrated (shared DB across worktrees per STATE.md precedent), so no `migrate` was needed. Same class of worktree-staleness issue documented repeatedly in STATE.md's Blockers/Concerns.

## User Setup Required

None - no external service configuration required. No new migrations (status stays a plain string column, per the plan's explicit constraint); no dependency changes.

## Next Phase Readiness

- Local census cross-check is fully wired end-to-end: blur warning -> save-time status -> Filament badge/filter -> hourly + on-demand background reconciliation, all reusing existing infrastructure with zero new schema.
- `census:reconcile-validation` will start firing hourly automatically the next time the scheduler runs (`schedule:run`), no further activation needed.
- The `revalidateLeaderVoters` admin action is immediately usable in production for any líder whose apoyos need an out-of-band recheck.
- Full targeted regression suite (`tests/Feature/Filament`, `tests/Feature/Leader`, `tests/Feature/Jobs`, `tests/Feature/VoterTest.php`, `tests/Feature/ApoyoDuplicateSequenceTest.php`, `tests/Feature/Services`) — 286 tests, 908 assertions, all passing.

---
*Quick task: 260726-ifp*
*Completed: 2026-07-26*

## Self-Check: PASSED

All 10 created/modified files confirmed present on disk. All 5 commit hashes (`fe4148f`, `8ddc7ee`, `384cb70`, `cc74fd2`, `0952232`) confirmed present in git log.
