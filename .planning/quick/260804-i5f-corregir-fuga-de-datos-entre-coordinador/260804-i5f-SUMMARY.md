---
phase: 260804-i5f
plan: 01
subsystem: authorization
tags: [pest, spatie-permission, coordinator, leaders, excel-export, security]

# Dependency graph
requires: []
provides:
  - "LeadersExportController's export query filters by coordinator_user_id when the actor holds the coordinator role — admin_campaign/super_admin exports remain unrestricted"
  - "leader-add-voter.blade.php mount() requires leader->coordinator_user_id === coordinator->id (write-path fix)"
  - "leader-voters.blade.php mount() requires leader->coordinator_user_id === coordinator->id (read-path fix)"
  - "TopLeadersExport::query() filters by coordinator_user_id for coordinator-role requesters, matching TopLeadersTable's existing widget logic"
affects: [coordinator-leaders, coordinator-voters, dashboard-widgets, filament-exports]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Role-conditional query filter: ->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) => $q->where('coordinator_user_id', $user->id)) — applied identically across an HTTP export controller and an Excel export class, mirroring the existing TopLeadersTable widget precedent"
    - "abort_unless() ownership checks in Volt mount() now compare coordinator_user_id in addition to municipality_id and campaign membership — closing the gap where same-municipio/same-campaña coordinators could act on each other's leaders"

key-files:
  created:
    - tests/Feature/Coordinator/LeaderVotersAccessTest.php
    - tests/Feature/Coordinator/TopLeadersExportTest.php
  modified:
    - app/Http/Controllers/Coordinator/LeadersExportController.php
    - resources/views/livewire/coordinator/leader-add-voter.blade.php
    - resources/views/livewire/coordinator/leader-voters.blade.php
    - app/Exports/TopLeadersExport.php
    - tests/Feature/Coordinator/LeadersExportTest.php
    - tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php

key-decisions:
  - "LeadersExportController's fix is role-conditional (coordinator only), not unconditional, since its route middleware (role:coordinator,admin_campaign,super_admin) is also reachable by admin_campaign/super_admin, whose coordinator_user_id is always null"
  - "TopLeadersExport::query() exactly replicates TopLeadersTable's existing ->when(Auth::user()?->hasRole(...)) pattern rather than introducing a new filtering convention"
  - "leader-add-voter.blade.php and leader-voters.blade.php both keep the project's established abort_unless() inline pattern (no new Policy class introduced) — consistent with the rest of app/Policies/ having no UserPolicy for this relationship"

requirements-completed: []

# Metrics
duration: 25min
completed: 2026-08-04
---

# Quick Task 260804-i5f: Corregir fuga de datos entre coordinadores Summary

**Closed 4 confirmed cross-coordinator PII leaks (leaders Excel export, "Agregar Apoyo" write path, "Apoyos del líder" read path, and the dashboard's "Ranking de Líderes" Excel export) by adding the missing `coordinator_user_id` comparison to each authorization check — previously all 4 only compared role + municipio + campaña, letting any coordinator see/export/act on another coordinator's leaders and apoyos within the same municipio and campaña.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-08-04
- **Tasks:** 4/4 automated tasks complete; checkpoint:human-verify task pending (browser verification not performed by this agent per task constraints)
- **Files modified:** 6 (4 production files + 2 extended test files), 2 new test files created

## Accomplishments
- `LeadersExportController` now filters its export query by `coordinator_user_id` when the actor has the coordinator role, while admin_campaign/super_admin exports across multiple coordinators remain fully unrestricted (verified by a dedicated regression test).
- `leader-add-voter.blade.php`'s `mount()` (the "Agregar Apoyo" write path) now 403s for a leader belonging to a different coordinator in the same municipio/campaña — the exact gap copied from a prior fix in commit c09f89d.
- `leader-voters.blade.php`'s `mount()` (the "Apoyos del líder" read path) now has the same protection, plus its first dedicated access-control test file.
- `TopLeadersExport::query()` now replicates `TopLeadersTable`'s existing coordinator filter exactly, so the dashboard widget's "Exportar" Excel download can no longer leak another coordinator's leader into the ranking.
- Every one of the 4 fixes has a passing Pest test proving the real "same municipio + same campaña + different coordinator_user_id" gap is closed — not just the pre-existing "different municipio" case that gave false confidence before.

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix LeadersExportController — filter export query by coordinator_user_id (coordinator-role only)** - `e1e8bb8` (fix)
2. **Task 2: Fix leader-add-voter.blade.php mount() — require coordinator_user_id match** - `5c21f83` (fix)
3. **Task 3: Fix leader-voters.blade.php mount() — require coordinator_user_id match** - `68e80e3` (fix)
4. **Task 4: Fix TopLeadersExport::query() — replicate TopLeadersTable's coordinator_user_id filter** - `8c209b0` (fix)

_Note: each task's test + implementation were committed together (single commit per task) rather than separate RED/GREEN commits, since the exact fix shape (mirroring TopLeadersTable's already-proven pattern) was fully specified and confirmed by the plan-checker before execution — no exploratory implementation was needed between test and code changes._

## Files Created/Modified
- `app/Http/Controllers/Coordinator/LeadersExportController.php` - Added role-conditional `coordinator_user_id` filter to the export query, gated behind `hasRole(UserRole::COORDINATOR->value)`
- `tests/Feature/Coordinator/LeadersExportTest.php` - Linked existing happy-path leaders to the acting coordinator; added 2 new tests (cross-coordinator exclusion, admin_campaign multi-coordinator regression)
- `resources/views/livewire/coordinator/leader-add-voter.blade.php` - `mount()`'s `abort_unless` now also requires `$leader->coordinator_user_id === $coordinator->id`
- `tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php` - Added a new test proving 403 for a same-municipio/same-campaña leader belonging to a different coordinator
- `resources/views/livewire/coordinator/leader-voters.blade.php` - Same `mount()` fix as Task 2, applied to the read-only apoyos list
- `tests/Feature/Coordinator/LeaderVotersAccessTest.php` - New file: happy path (200 for own leader) + cross-coordinator 403 test
- `app/Exports/TopLeadersExport.php` - `query()` extended with the same coordinator-conditional filter used by `TopLeadersTable`
- `tests/Feature/Coordinator/TopLeadersExportTest.php` - New file: cross-coordinator exclusion for a coordinator actor + unrestricted view for a super_admin actor

## Decisions Made
- All decisions were pre-locked by the plan-checker-verified plan; no new architectural decisions were made during execution. See `key-decisions` in frontmatter above for the specific implementation choices carried through (role-conditional filtering, exact replication of `TopLeadersTable`'s pattern, no new Policy layer).

## Deviations from Plan

**1. [CLAUDE.md enforcement] Used explicit `use` imports instead of inline FQCN in test snippets**

The plan's action blocks for Task 1 specified test code using inline fully-qualified class references (e.g. `function (\App\Exports\LeadersExport $export)`, `\App\Enums\UserRole::ADMIN_CAMPAIGN->value`) — flagged by the plan-checker as violating CLAUDE.md's explicit "no inline FQCN" rule. Implemented with proper `use App\Enums\UserRole;` and `use App\Exports\LeadersExport;` imports and bare class references throughout `tests/Feature/Coordinator/LeadersExportTest.php` instead. No behavior change; purely a code-style correction per this repo's hard CLAUDE.md constraint.

- **Found during:** Task 1
- **Files modified:** `tests/Feature/Coordinator/LeadersExportTest.php`
- **Committed in:** `e1e8bb8`

---

**Total deviations:** 1 (CLAUDE.md style enforcement, explicitly anticipated by this quick task's constraints)
**Impact on plan:** None on behavior/scope — purely a style correction required by the project's hard import-statement rule.

## Issues Encountered

`vendor/bin/pint --dirty` also reformatted one pre-existing spacing style issue in `LeadersExportController.php` (a double-space inside a closure's `fn` arrow before the `->when` call) unrelated to this task's added line — left as pint auto-fixed it, since it's a formatting-only change in a file this task was already touching.

## User Setup Required

None - no external service configuration required. All 4 fixes are backend authorization/query logic, fully exercised by Pest (HTTP feature tests + direct `Excel::fake()`/`query()` assertions). No new environment variables, migrations, or dependencies.

## Next Phase Readiness

All 4 automated fixes are implemented, tested, committed, and `vendor/bin/pint --dirty` clean. Per this project's standing preference (Pest/Livewire tests alone are not sufficient sign-off for UI-facing security fixes), **Task 5 (checkpoint:human-verify) is still pending** — a human must confirm in a real browser, using two coordinator accounts in the same municipio/campaña:
1. Coordinador A's "Exportar Líderes" Excel only contains their own leader(s).
2. Coordinador A gets 403 navigating directly to Coordinador B's leader's `/voters` and `/voters/create` URLs.
3. The dashboard's "Ranking de Líderes" "Exportar" action only includes the acting coordinator's own leaders (and still includes both leaders for an admin/super_admin view).
4. An `admin_campaign` user's "Líderes" export still includes leaders from multiple different coordinators.

No blockers found. This quick task is intentionally NOT yet listed as fully complete in STATE.md's Quick Tasks Completed table until the checkpoint is resolved.

---
*Phase: 260804-i5f*
*Completed: 2026-08-04*

## Self-Check: PASSED

- FOUND: app/Http/Controllers/Coordinator/LeadersExportController.php
- FOUND: resources/views/livewire/coordinator/leader-add-voter.blade.php
- FOUND: resources/views/livewire/coordinator/leader-voters.blade.php
- FOUND: app/Exports/TopLeadersExport.php
- FOUND: tests/Feature/Coordinator/LeadersExportTest.php
- FOUND: tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php
- FOUND: tests/Feature/Coordinator/LeaderVotersAccessTest.php
- FOUND: tests/Feature/Coordinator/TopLeadersExportTest.php
- FOUND: .planning/quick/260804-i5f-corregir-fuga-de-datos-entre-coordinador/260804-i5f-SUMMARY.md
- FOUND commit: e1e8bb8
- FOUND commit: 5c21f83
- FOUND commit: 68e80e3
- FOUND commit: 8c209b0
