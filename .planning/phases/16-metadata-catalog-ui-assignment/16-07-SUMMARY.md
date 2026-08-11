---
phase: 16-metadata-catalog-ui-assignment
plan: 07
subsystem: auth
tags: [filament, livewire, metadata, authorization, pest]

# Dependency graph
requires:
  - phase: 16-metadata-catalog-ui-assignment
    provides: MetadataAssignmentService (canAssignTo, subordinatesByIds, assign, assignMany) built in plan 16-01; Filament section/bulk-action builder (MetadataAssignment.php) built in plan 16-03/16-04
provides:
  - Actor-authorization gate on the Filament individual assignMetadata Action (visible() + abort_unless())
  - Actor-authorization re-filter on the Filament bulk assignMetadata BulkAction (subordinatesByIds() before any write)
  - Spanish danger notification when a bulk action resolves zero authorized targets (replaces the prior misleading "assigned to 0 usuario(s)" success notification)
  - Regression suite proving reviewer is blocked (individually + bulk) and admin_campaign remains fully unrestricted
affects: [17-filter-sort-export-surfaces]

# Tech tracking
tech-stack:
  added: []
  patterns: [server-side re-filter of Filament-resolved bulk action records against an authorization service before any write, mirroring the existing Volt pattern]

key-files:
  created:
    - tests/Feature/Metadata/FilamentMetadataAuthorizationTest.php
  modified:
    - app/Filament/Schemas/MetadataAssignment.php

key-decisions:
  - "Individual action gated with both a ->visible() hide (UX) and an abort_unless() write-time check (defense-in-depth) — mirrors MetadataAssignmentPanel::assign()'s Volt pattern exactly."
  - "Bulk action re-filters $records through subordinatesByIds() before any write, rather than trusting Filament's client-supplied record collection — mirrors leaders.blade.php's assignBulkMetadata() pattern exactly."
  - "A bulk action resolving zero authorized targets now returns early with a Spanish danger notification instead of falling through to a misleading 'Metadata asignada a 0 usuario(s)' success notification."

patterns-established:
  - "Filament bulk actions handling privileged writes must re-resolve their target-authorization scope server-side before writing, never trust the Collection<Model> Filament hands to the action closure as already-authorized."

requirements-completed: [META-03, META-04]

# Metrics
duration: 25min
completed: 2026-08-11
---

# Phase 16 Plan 07: Filament Metadata Assignment Authorization Gap Closure Summary

**Gated the Filament admin-panel metadata assignment actions (individual + bulk) on `MetadataAssignmentService::canAssignTo()`/`subordinatesByIds()`, closing a gap where a `reviewer` (who has `admin` panel access but zero resolved subordinates) could previously write audited `user_metadata_values` rows with no authorization check at all.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-08-11T00:12:00Z (approx, worktree fast-forward + composer install preceded task work)
- **Completed:** 2026-08-11
- **Tasks:** 2/2 completed
- **Files modified:** 2 (1 modified, 1 created)

## Accomplishments
- Individual `assignMetadata` Filament Action on `MetadataAssignment::section()` now hidden from (and write-blocked for) any actor who is not a direct superior of the record being edited.
- Bulk `assignMetadata` BulkAction on `MetadataAssignment::bulkAction()` now re-filters the Filament-resolved record collection through `subordinatesByIds()` before writing anything — a reviewer selecting records and submitting the modal directly writes zero rows.
- Fixed the accompanying notification anti-pattern flagged in `16-VERIFICATION.md`: a bulk action resolving zero authorized targets now shows a Spanish danger notification ("No tienes autorización para asignar metadata a los usuarios seleccionados.") instead of a misleading success notification.
- New `FilamentMetadataAuthorizationTest.php` (3 tests, 16 assertions) proves the gap is closed on both write paths for `reviewer`, while explicitly exercising `admin_campaign` (previously only `super_admin` was ever exercised as actor in the pre-existing Filament metadata test suites) to prove D-02's unrestricted-superior rule still holds.
- Full `tests/Feature/Metadata` suite (61 tests, 283 assertions) passes with zero regressions.

## Task Commits

Each task was committed atomically:

1. **Task 1: Gate the Filament individual and bulk assignment actions on actor authorization** - `bb4209d` (fix)
2. **Task 2: Regression test proving a reviewer is blocked and super_admin/admin_campaign remain unrestricted** - `a1a7dce` (test)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified
- `app/Filament/Schemas/MetadataAssignment.php` - Added `->visible()` gate + `abort_unless()` write-time check to the individual `assignMetadata` Action; replaced the bulk `assignMetadata` action closure to re-filter records through `subordinatesByIds()` before writing, with a danger notification on zero authorized targets.
- `tests/Feature/Metadata/FilamentMetadataAuthorizationTest.php` (new) - 3 Pest tests: reviewer denied individually (hidden action + write-time block via `ActionNotResolvableException`), reviewer denied in bulk (zero rows written), admin_campaign unrestricted individually and in bulk.

## Decisions Made
- Individual action authorization enforced with two layers (visibility hide + write-time `abort_unless()`), matching the plan's explicit defense-in-depth requirement and the already-correct Volt `MetadataAssignmentPanel::assign()` reference pattern.
- Bulk action authorization enforced by re-resolving the actor's actual subordinate scope server-side (`subordinatesByIds()`) rather than trusting the `Collection<User>` Filament hands to the action closure — the client-supplied record IDs backing that collection are not trustworthy on their own, same reasoning already applied in `leaders.blade.php`'s `assignBulkMetadata()`.
- Test 1 proves the hidden individual action is unresolvable when invoked directly by asserting the `callAction()` call throws (Filament's actual enforcement mechanism for an invisible action, per the plan's empirically-confirmed note about `ActionNotResolvableException`), rather than asserting a specific exception class match, since Filament wraps action-resolution failures in different ways across contexts.

## Deviations from Plan

None — plan executed exactly as written. One infrastructure fix was required before any task work could begin (see Issues Encountered), which is standard worktree-sync overhead, not a plan deviation.

## Issues Encountered

**Worktree was stale at session start** — this worktree (`agent-af5d3dd48c7dd00c1`) had no `.planning/phases/16-metadata-catalog-ui-assignment/` directory at all (missing Phase 16 entirely) and had no `.env`, `vendor/`, `node_modules/`, or `public/build/`. Confirmed via `git merge-base --is-ancestor HEAD main` that the worktree's `HEAD` was a fast-forward ancestor of `main` (which had already merged plans 16-01 through 16-06 plus a gap-closure planning commit). Resolved with the established repeated workaround documented throughout `.planning/STATE.md`:
1. `git merge --ff-only main`
2. Copied `.env` from the main checkout
3. `composer install --no-interaction`
4. `npm install && npm run build` (required — `MetadataKeyResourceTest.php`'s full-page `assertOk()` test failed on a missing Vite manifest until built; this is the same recurring class of pre-existing infra gap documented in prior phase SUMMARYs, not a regression introduced by this plan)

This is the same recurring `findProjectRoot()`/worktree-staleness class documented repeatedly across Phases 12-15's SUMMARY files. `gsd-tools` state-update commands were not invoked directly for this reason (see State Updates note below); STATE.md/ROADMAP.md/REQUIREMENTS.md were updated by editing this worktree's copies directly, per the established precedent.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Both META-03 (superior-scoped individual assignment) and META-04 (superior-scoped bulk assignment) are now enforced consistently across every UI surface — the two Volt panels (already correct pre-plan) and the Filament admin-panel section/bulk-action (fixed by this plan). `16-VERIFICATION.md`'s Gap 1 is closed. No blockers for Phase 17 (Filter/Sort/Export Surfaces), which depends on Phase 16's metadata schema and assignment mechanics, both of which are now hardened.

---
*Phase: 16-metadata-catalog-ui-assignment*
*Completed: 2026-08-11*

## Self-Check: PASSED

- FOUND: app/Filament/Schemas/MetadataAssignment.php
- FOUND: tests/Feature/Metadata/FilamentMetadataAuthorizationTest.php
- FOUND: .planning/phases/16-metadata-catalog-ui-assignment/16-07-SUMMARY.md
- FOUND commit: bb4209d
- FOUND commit: a1a7dce
