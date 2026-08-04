---
phase: 260804-jbc
plan: 01
subsystem: auth
tags: [filament, livewire, volt, spatie-permission, coordinator-scoping, pii]

# Dependency graph
requires:
  - phase: 260804-i5f
    provides: "Round-1 cross-coordinator PII leak fixes (LeadersExportController, leader-add-voter.blade.php, leader-voters.blade.php, TopLeadersExport) establishing the coordinator_user_id comparison pattern"
provides:
  - "Coordinator dashboard (resources/views/livewire/coordinator/dashboard.blade.php) scopes $leaders by coordinator_user_id when actor has the coordinator role"
  - "DiaDStatsOverview::getStats() routes all 4 stat queries through a new scopedVoterQuery() private method"
  - "DiaDTerritorialProgressTable::table() routes whereHas/withCount closures through a new applyVoterScope() private helper"
  - "app/Filament/Pages/DiaD.php's dead, unscoped $stats property and refreshStats() method removed entirely"
affects: [coordinator-dashboard, dia-d, filament-widgets, authorization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "scopedVoterQuery(Campaign): Builder / applyVoterScope(Builder): Builder — role-conditional private helper (leader -> own registered_by, coordinator -> whereIn leaders()->pluck('id'), else unrestricted), replicated from CampaignStatsOverview/TerritorialDistributionChart into 2 more Día D surfaces"
    - "->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) => $q->where('coordinator_user_id', $user->id)) — role-conditional single-query filter, replicated from round-1 LeadersExportController into the coordinator dashboard's with()"

key-files:
  created:
    - tests/Feature/Coordinator/DashboardLeadersScopeTest.php
    - tests/Feature/DiaDStatsOverviewScopeTest.php
    - tests/Feature/DiaDTerritorialProgressScopeTest.php
  modified:
    - resources/views/livewire/coordinator/dashboard.blade.php
    - app/Filament/Widgets/DiaDStatsOverview.php
    - app/Filament/Widgets/DiaDTerritorialProgressTable.php
    - app/Filament/Pages/DiaD.php

key-decisions:
  - "app/Filament/Pages/DiaD.php's searchVoter()/markVoted()/markDidNotVote() core search/mark logic left untouched (confirmed intentional Día D exception) — only their now-dead refreshStats() call sites were removed"
  - "DiaDTerritorialProgressScopeTest's coordinator-scoped assertion uses assertSeeText/assertDontSeeText, not assertSee/assertDontSee, to avoid a false-positive digit match inside the wire:snapshot checksum hash (same precedent documented in OwnershipScopedWidgetsTest.php)"

requirements-completed: []

# Metrics
duration: 20min
completed: 2026-08-04
---

# Phase 260804-jbc: Corregir fuga de datos entre coordinadores (ronda 2) Summary

**Closed the second wave of cross-coordinator PII leakage — coordinator dashboard and both Día D widgets now scope leader/apoyo aggregates by `coordinator_user_id`/`registered_by`, and a dead unscoped-totals property on DiaD.php was deleted entirely — mirroring the already-correct `CampaignStatsOverview`/`TerritorialDistributionChart` patterns.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-08-04T14:05:53-05:00
- **Completed:** 2026-08-04T14:25:44-05:00
- **Tasks:** 4 automated (completed) + 1 checkpoint:human-verify (pending)
- **Files modified:** 4 production files, 3 new test files

## Accomplishments
- Coordinator's own dashboard (`/coordinator/dashboard`) — the exact bug the user reproduced live (22 leaders/188 apoyos belonging to another coordinator) — now filters `$leaders` by `coordinator_user_id` when the actor has the coordinator role, while `admin_campaign`/`super_admin` remain unrestricted.
- `DiaDStatsOverview` (Día D header stats: Total Apoyos/Confirmados/Votaron/No Votaron) now scoped via a new `scopedVoterQuery()`, identical in shape to `CampaignStatsOverview`'s established pattern.
- `DiaDTerritorialProgressTable` (per-municipality progress table) now scoped via a new `applyVoterScope()` helper applied inside `whereHas('voters', ...)` and all 3 `withCount()` closures, identical in shape to `TerritorialDistributionChart`'s established pattern.
- `app/Filament/Pages/DiaD.php`'s dead `$stats` property and `refreshStats()` method — zero rendered consumers, but still serialized into every page load's `wire:snapshot` HTML — removed entirely, along with its 3 call sites in `mount()`/`markVoted()`/`markDidNotVote()`. The intentional Día D "any coordinator can mark any apoyo's vote" exception (`searchVoter()`/`markVoted()`/`markDidNotVote()` core logic) was left completely untouched otherwise.
- 3 new Pest test files (8 tests total) each proving the real "same municipio + same campaña, different coordinator" gap is closed, while `admin_campaign`/`super_admin` visibility is unchanged.

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix coordinator dashboard's with() — scope $leaders by coordinator_user_id** - `29d1a52` (fix)
2. **Task 2: Fix DiaDStatsOverview — scope all 4 stats via scopedVoterQuery()** - `414fbc2` (fix)
3. **Task 3: Fix DiaDTerritorialProgressTable — scope whereHas/withCount closures via applyVoterScope()** - `91a73f3` (fix)
4. **Task 4: Remove dead $stats/refreshStats() from DiaD.php** - `8331349` (fix)

_All 4 tasks were TDD (RED confirmed the real production leak reproduced in a test before the fix, GREEN confirmed the fix closed it), except Task 4 which was a pure deletion with no new test file (per plan, since `$stats` has zero consumers)._

## Files Created/Modified
- `resources/views/livewire/coordinator/dashboard.blade.php` - Added role-conditional `->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) => $q->where('coordinator_user_id', $user->id))` filter to the `$leaders` query in `with()`
- `app/Filament/Widgets/DiaDStatsOverview.php` - Added private `scopedVoterQuery(Campaign $campaign): Builder`; all 4 stat queries (`$total`, `$confirmed`, `$voted`, `$didNotVote`) now go through it instead of bare `Voter::forCampaign()`
- `app/Filament/Widgets/DiaDTerritorialProgressTable.php` - Added private `applyVoterScope(Builder $query): Builder`; wrapped inside `whereHas('voters', ...)` and all 3 `withCount([...])` closures
- `app/Filament/Pages/DiaD.php` - Removed `public array $stats` property, `refreshStats(): void` method, and its 3 call sites in `mount()`/`markVoted()`/`markDidNotVote()`; no other logic changed
- `tests/Feature/Coordinator/DashboardLeadersScopeTest.php` - New: 3 tests proving coordinator dashboard scoping + admin_campaign unrestricted view
- `tests/Feature/DiaDStatsOverviewScopeTest.php` - New: 3 tests proving leader/coordinator/super_admin scoping on the Día D stats widget
- `tests/Feature/DiaDTerritorialProgressScopeTest.php` - New: 2 tests proving coordinator/super_admin scoping on the Día D territorial table

## Decisions Made
- `app/Filament/Pages/DiaD.php`'s `searchVoter()`/`markVoted()`/`markDidNotVote()` core search/mark logic was left untouched beyond removing the now-dead `refreshStats()` calls — this is the user-confirmed, intentional Día D exception (any coordinator/leader may search for and mark the vote of ANY apoyo in the active campaign on election day), not a scope gap.
- `DiaDTerritorialProgressScopeTest`'s coordinator-only assertion uses `assertSeeText`/`assertDontSeeText` rather than `assertSee`/`assertDontSee` for the numeric total check — a raw `assertDontSee('6')` produced a false-positive match against a `6` digit embedded in the Livewire `wire:snapshot` checksum hash, not real page content. Fixed inline before committing (same precedent already documented in `OwnershipScopedWidgetsTest.php`'s inline comment). Rule 1 (bug in newly-authored test, not the plan's production code).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed false-positive digit assertion in DiaDTerritorialProgressScopeTest**
- **Found during:** Task 3 (writing the RED test for DiaDTerritorialProgressTable scoping)
- **Issue:** The plan's suggested test used `assertSee('2')`/`assertDontSee('6')` for the coordinator-scoping assertion. `assertDontSee('6')` failed even after the production fix was correctly applied, because Livewire's `wire:snapshot` checksum attribute contains hex digits and coincidentally always includes a `6` somewhere in its hash — an unrelated substring match, not a real leak.
- **Fix:** Switched both numeric assertions in that test to `assertSeeText`/`assertDontSeeText`, which strip all tags/attributes (including `wire:snapshot`) before comparing — same approach already established and commented in `tests/Feature/OwnershipScopedWidgetsTest.php`.
- **Files modified:** `tests/Feature/DiaDTerritorialProgressScopeTest.php`
- **Verification:** Test passes after the fix; production `DiaDTerritorialProgressTable.php` code was not touched by this fix.
- **Committed in:** `91a73f3` (part of Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 test-authoring bug)
**Impact on plan:** No scope creep — the production scoping fix itself matched the plan's interfaces exactly (`scopedVoterQuery`/`applyVoterScope` signatures identical to spec). Only the plan's suggested test assertion needed a one-line correction to avoid a false-positive against unrelated markup.

## Issues Encountered
None beyond the deviation documented above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Task 5 (checkpoint:human-verify) is PENDING.** All 4 automated fixes are complete, committed, and covered by passing Pest tests (each proving the real "same municipio + same campaña, different coordinator" gap is closed), but the plan's required real-browser confirmation across all scenarios has NOT been performed:

1. Coordinador A's `/coordinator/dashboard` — Líderes Activos/Total Apoyos/Confirmados/Pendientes/Líderes Más Productivos/Actividad de la Última Semana reflect only A's own team.
2. Coordinador B's `/coordinator/dashboard` — repeat, confirm B sees only B's own numbers.
3. Either coordinator's Día D page (`/coordinator/dia-d` or `/leader/dia-d`) — "Estado del Día D" stats widget and "Participación por Municipio" table reflect only the acting coordinator's own team.
4. Día D "buscar apoyo" + "Marcar VOTÓ"/"Marcar NO VOTÓ" flow — confirm searching/marking another coordinator's leader's apoyo still works exactly as before (the untouched, intentional exception).
5. `admin_campaign`/`super_admin` — repeat steps 1 and 3, confirm they still see the FULL, unrestricted campaign-wide totals.
6. `/coordinator/dia-d` view-source (Ctrl+U) — confirm no `stats`/`refreshStats` key remains in the `wire:snapshot` payload.

This quick task is intentionally NOT yet considered fully complete — matching the precedent set by 260804-i5f and 260801-hvd's still-pending checkpoints. Per project preference (Pest/Livewire tests alone are not sufficient sign-off for UI-facing security fixes), this requires manual browser verification before production sign-off.

---
*Phase: 260804-jbc*
*Completed: 2026-08-04*

## Self-Check: PASSED

All 7 created/modified source files and the SUMMARY.md itself were confirmed present on disk. All 4 task commit hashes (`29d1a52`, `414fbc2`, `91a73f3`, `8331349`) were confirmed present in `git log`.
