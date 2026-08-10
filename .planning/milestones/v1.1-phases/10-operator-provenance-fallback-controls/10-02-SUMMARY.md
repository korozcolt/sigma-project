---
phase: 10-operator-provenance-fallback-controls
plan: 02
subsystem: ui
tags: [filament, livewire, rbac, spatie-permission, voter-form]

# Dependency graph
requires:
  - phase: 08-resilient-pollingplaceresolver-service
    provides: "polling_place_source / polling_place_resolved_at columns populated by PollingPlaceResolver"
provides:
  - "Read-only polling-place source badge + resolved-at timestamp visible inside the Voter edit form (Ubicación section)"
  - "Role gate (admin_campaign/coordinator/super_admin only) on the paid actualizar_registraduria force-refresh suffixAction"
  - "Confirmation that consultar_registraduria (free lookup) stays unrestricted for every role, satisfying SRC-04 with no new UI"
affects: [10-04-checkpoint, 11-scheduled-reconciliation-job]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Role-gated Filament Action ->visible() using auth()->user()?->hasAnyRole([...]) ?? false, matching EditVoter.php's reassignDuplicateOwner action style"
    - "Read-only Placeholder rendering a Blade component (<x-filament::badge>) via HtmlString for enum-backed display data"

key-files:
  created: []
  modified:
    - app/Filament/Resources/Voters/Schemas/VoterForm.php
    - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php

key-decisions:
  - "SRC-01's third/last surface (edit form) is a Placeholder in the Ubicación section, not a new dedicated section — keeps it next to the polling-place fields it describes"
  - "Role gate reuses the exact hasAnyRole() pattern already established in EditVoter.php rather than introducing a new authorization mechanism"
  - "consultar_registraduria intentionally left untouched (D-01) — SRC-04's 'manual re-check at any time' is satisfied by the pre-existing, unrestricted lookup action"

patterns-established:
  - "Cost/role-gated actions on a suffixAction reuse hasAnyRole() inline in ->visible(), consistent across VoterForm.php and EditVoter.php"

requirements-completed: [SRC-01, SRC-04]

# Metrics
duration: 10min
completed: 2026-07-25
---

# Phase 10 Plan 02: VoterForm Source Badge + Force-Refresh Role Gate Summary

**Read-only polling-place source badge in the Voter edit form's Ubicación section, plus a three-role gate (admin_campaign/coordinator/super_admin) on the paid "Actualizar datos desde Registraduría" force-refresh action, leaving the free "Consultar Registraduría" lookup unrestricted for every role.**

## Performance

- **Duration:** ~10 min
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Operators now see the voter's current polling-place source (live/db_reconstruction/snapshot/manual) and resolved-at timestamp directly while editing the record, completing SRC-01's third and final surface (table and view pages were already covered elsewhere in Phase 10).
- The paid, cache-bypassing force-refresh action is now restricted to admin_campaign, coordinator, and super_admin — leader and reviewer can no longer trigger it, even on a voter whose polling place is already resolved.
- Verified via real Livewire component tests (not just code inspection) that leader/reviewer still retain the free "Consultar Registraduría" action, confirming SRC-04 needs no new UI (D-01).

## Task Commits

Each task was committed atomically:

1. **Task 1: Read-only source badge display + role gate on the force-refresh action** - `c729a5a` (feat)
2. **Task 2: Pest coverage for the role gate and the untouched original lookup action** - `efbbea5` (test)

_Note: Task 2 was tagged `tdd="true"` in the plan, but since it added coverage for behavior Task 1 already implemented (not new production code driven by a red/green cycle), it was delivered as a single `test` commit appending dataset-driven assertions against the already-working implementation._

## Files Created/Modified
- `app/Filament/Resources/Voters/Schemas/VoterForm.php` - Added `polling_place_source_display` Placeholder (badge + resolved-at) in the Ubicación section; added `hasAnyRole()` gate to `actualizar_registraduria`'s `->visible()`; `consultar_registraduria` left untouched.
- `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` - Added 3 dataset-driven `it()` blocks (7 total executions) proving the role gate's allow/deny sets and the untouched lookup action's continued visibility.

## Decisions Made
- Placed the source badge inside the existing "Ubicación" section (next to `polling_table_number`) rather than creating a new section, since it directly describes the polling-place fields already there.
- Reused `EditVoter.php`'s exact `hasAnyRole()` call style for the new gate instead of introducing a policy/gate class, keeping consistency with the codebase's existing authorization pattern for this resource.

## Deviations from Plan

None - plan executed exactly as written. All acceptance criteria (grep patterns, test counts, Pint) matched on first pass.

## Issues Encountered

**Stale worktree (same class of issue documented in STATE.md Blockers/Concerns for Phases 06/07):** This execution's worktree (`agent-adb71e389d63536c1`) was checked out at commit `78c1f69` — pre-dating Phases 6 through 10 entirely, missing `vendor/`, `.env`, and all `.planning/phases/06-10` directories. Confirmed `78c1f69` is a fast-forward ancestor of main's `8de7b48`, so resolved identically to the documented precedent: `git stash` (to preserve the in-progress Task 1 edit), `git merge --ff-only 8de7b48`, `git stash pop` (clean re-apply), then `composer install --no-interaction` and copying `.env` from the main checkout. No data loss; the edit was verified intact via grep after the stash pop, before continuing.

**`gsd-tools.cjs` worktree root-resolution bug reconfirmed:** Running `gsd-tools state show` from this worktree returned the **main checkout's** STATE.md content (`status: Executing Phase 10`, `Plan: 1 of 4`) instead of this worktree's own (`status: Ready to plan`, `Plan: Not started`) — the same `findProjectRoot()` bug already documented in STATE.md's Blockers/Concerns for Phases 06 and 07. Per the documented workaround, all STATE.md/ROADMAP.md/REQUIREMENTS.md updates for this plan were made by hand-editing this worktree's own copies directly, not via `gsd-tools` CLI commands.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- SRC-01 and SRC-04 are both satisfied from this plan's perspective (edit-form surface + confirmation that the existing lookup action covers "re-check at any time"). Full phase-level SRC-01 completion also depends on sibling parallel plan 10-01 (table/view surfaces), which was not visible to this worktree at execution time — the orchestrator should verify all three parallel Phase 10 plans (01/02/03) land together before considering SRC-01/SRC-05 fully closed at the phase level.
- No blockers for 10-04 (human checkpoint) or Phase 11 (scheduled reconciliation) introduced by this plan.

---
*Phase: 10-operator-provenance-fallback-controls*
*Completed: 2026-07-25*

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Voters/Schemas/VoterForm.php
- FOUND: tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
- FOUND: .planning/phases/10-operator-provenance-fallback-controls/10-02-SUMMARY.md
- FOUND commit: c729a5a
- FOUND commit: efbbea5
