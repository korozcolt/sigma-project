---
phase: 16-metadata-catalog-ui-assignment
plan: 08
subsystem: testing
tags: [pest, campaign-context, factories, test-stability, metadata]

# Dependency graph
requires:
  - phase: 16-metadata-catalog-ui-assignment
    provides: "MetadataAssignmentService, MetadataKey catalog CRUD, Filament bulk metadata action, MetadataKeyFactory (16-01 through 16-06)"
provides:
  - "FilamentMetadataBulkActionTest pinned to an explicit CampaignContext per test, immune to a static-override leak from earlier test files in the same process"
  - "MetadataKeyFactory that can never construct an internally-invalid select-typed row (type=select with options=null)"
  - "Proof that the full tests/Feature/Metadata suite (58 tests) is stable both in isolation and inside a full tests/Feature run"
affects: [16-07, phase-16-verification]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Explicit CampaignContext::setCampaignId() pin per test file (beforeEach/afterEach) instead of relying on Session::put alone, to avoid cross-file static-state leakage in the same PHP test process"]

key-files:
  created: []
  modified:
    - tests/Feature/Metadata/FilamentMetadataBulkActionTest.php
    - database/factories/MetadataKeyFactory.php

key-decisions:
  - "Reworded the CampaignContext-pin explanatory comment to avoid literally containing the string 'Session::put' (the plan's own suggested comment text included it), since the plan's own acceptance criteria required grep -c \"Session::put\" to return 0"
  - "Did not mark META-04 complete despite it being listed in this plan's own frontmatter requirements field — 16-VERIFICATION.md explicitly flags META-04 as 'not safe to close yet' pending Gap 1 (unauthorized reviewer access on the Filament bulk/individual assignment actions), which is 16-07's scope, not this plan's. This plan only closes Gap 2 (test-suite flakiness)."
  - "Marked META-06 complete (atomic per-key writes) — 16-VERIFICATION.md already assessed it as satisfied/safe to close independent of Gap 1, and this plan's full-suite stability proof directly reinforces that closure claim by proving the append-only/atomicity regression tests no longer flake"

requirements-completed: [META-06]

# Metrics
duration: 25min
completed: 2026-08-11
---

# Phase 16 Plan 08: Metadata Test-Suite Flakiness Gap Closure Summary

**Pinned `CampaignContext` explicitly per test in `FilamentMetadataBulkActionTest` and made `MetadataKeyFactory` unable to construct an internally-invalid select-typed row, eliminating both known causes of full-suite Metadata test flakiness identified in `16-VERIFICATION.md`'s Gap 2.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-08-11 (worktree fast-forwarded to main first)
- **Completed:** 2026-08-11
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `FilamentMetadataBulkActionTest` no longer relies on being the first (or only) Metadata test file to run in a process — it now pins its own `CampaignContext` via `CampaignContext::setCampaignId()` in `beforeEach` and resets it in `afterEach`, exactly matching `FilamentMetadataSectionTest`'s already-correct pattern. Both fixture helper functions (`bulkMetadataSuperAdmin()`, `bulkMetadataUser()`) now explicitly attach their user to the pinned campaign.
- `MetadataKeyFactory::definition()` no longer randomizes `type` into `select` (which always paired with `options => null`, an internally-invalid combination the `MetadataKeyForm`'s `Repeater::make('options')->minItems(1)` rule would itself reject). A new `select()` state supplies `type` and non-empty `options` together.
- Verified the fix end to end: `tests/Feature/Metadata` (58 tests, 267 assertions) passes both in isolation and inside a full `tests/Feature` run; the previously-flaky "keeps assignment history when a key is deactivated" test passed 10/10 consecutive isolated runs; a full-suite run shows zero failures in the `Tests\Feature\Metadata` namespace (the 19 failures present are all pre-existing, out-of-scope, non-Metadata files: `DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`, `RejectionsCountersOverviewTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `UserResourceTest`, `VoterResourceTest`, `PollingPlaceResolverPriorityTest`, `PollingPlaceResolverTest`).

## Task Commits

Each task was committed atomically:

1. **Task 1: Pin CampaignContext explicitly in FilamentMetadataBulkActionTest** - `10f3f68` (test)
2. **Task 2: Make MetadataKeyFactory internally consistent for select-typed rows** - `76a061d` (test)

**Plan metadata:** (this commit) — `docs(16-08): complete metadata test-suite flakiness gap closure plan`

## Files Created/Modified
- `tests/Feature/Metadata/FilamentMetadataBulkActionTest.php` - Pins `CampaignContext::setCampaignId()` per test (beforeEach/afterEach), attaches both super-admin and regular-user fixtures to the pinned campaign, removes the now-unused `Session` import/call
- `database/factories/MetadataKeyFactory.php` - Excludes `select` from the randomized `type` pool; adds a dedicated `select()` state pairing `type => 'select'` with non-empty `options`

## Decisions Made
- Reworded the plan's own suggested explanatory comment in `FilamentMetadataBulkActionTest` to avoid the literal substring `Session::put`, since the plan's acceptance criteria required `grep -c "Session::put"` to return `0` and the plan's suggested comment text itself would have failed that check as written.
- Did **not** mark META-04 complete in `REQUIREMENTS.md`, despite it being listed in this plan's own frontmatter `requirements` field — `16-VERIFICATION.md` explicitly states META-04 is "not safe to close yet" pending Gap 1 (the Filament bulk/individual assignment actions performing no actor-authorization check, letting a `reviewer` write metadata rows), which is `16-07`'s scope, not this plan's. Deferred to phase completion, matching this project's established split-requirement precedent.
- Marked META-06 (atomic per-key writes) complete — `16-VERIFICATION.md` already assessed it as satisfied and safe to close independent of Gap 1 (pure-INSERT write path, no `updateOrCreate`/`upsert` anywhere in the metadata write path, `orderByDesc('assigned_at')->orderByDesc('id')` tiebreak), and this plan's full-suite stability proof directly reinforces that closure by proving the relevant regression tests no longer flake.
- META-01 was already marked `Done` in `REQUIREMENTS.md` prior to this plan (by `16-02`); no action needed for it here despite being listed in this plan's frontmatter.

## Deviations from Plan

None — plan executed exactly as written, with one wording-only adjustment (see Decisions Made above) required to satisfy the plan's own literal acceptance-criteria grep pattern.

## Issues Encountered
- This worktree (`agent-aad182f121e039b3d`) was 1 commit behind `main` at session start — missing this phase's own `16-07`/`16-08` PLAN.md files plus `.env`, `vendor/`, `node_modules/`, and `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install`, and `public/build/` copy from the main checkout (Vite manifest was missing, causing 3 spurious `MetadataKeyResourceTest` failures until copied — confirmed not a regression, all 58 Metadata tests passed afterward). `gsd-tools init execute-phase 16` again resolved `project_root` to the main checkout, not this worktree, reconfirming the recurring `findProjectRoot()` bug — STATE.md/ROADMAP.md/REQUIREMENTS.md updated by hand-editing the worktree copies directly.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The phase's "test protection holds end to end" observable truth is restored for the test-stability dimension (Gap 2). Gap 1 (Filament actor-authorization on the bulk/individual assignment actions, blocking META-03/META-04) remains open and is `16-07`'s scope — phase 16 is not yet fully complete until `16-07` lands and both `16-VERIFICATION.md` gaps are closed.
- No blockers for future phases from this plan's changes; `app/` was untouched, matching the plan's explicit constraint.

---
*Phase: 16-metadata-catalog-ui-assignment*
*Completed: 2026-08-11*

## Self-Check: PASSED

- FOUND: tests/Feature/Metadata/FilamentMetadataBulkActionTest.php
- FOUND: database/factories/MetadataKeyFactory.php
- FOUND: .planning/phases/16-metadata-catalog-ui-assignment/16-08-SUMMARY.md
- FOUND: commit 10f3f68
- FOUND: commit 76a061d
