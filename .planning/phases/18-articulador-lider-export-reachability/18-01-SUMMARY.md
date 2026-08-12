---
phase: 18-articulador-lider-export-reachability
plan: 01
subsystem: auth
tags: [laravel, filament, routing, authorization, articulador, export]

# Dependency graph
requires:
  - phase: 13
    provides: "User::teamCoordinatorUserIds() transitive team resolution, already correctly wired into LeadersExportController's query"
provides:
  - "coordinator.leaders.export route reachable by area_coordinator (in addition to coordinator/admin_campaign/super_admin), scoped to only this one route"
  - "TopLeadersTable widget second header action ('exportTeam') giving an articulador a UI trigger for their full transitive team download"
  - "Route-level regression test proving reachability, team isolation (cross-articulador and cross-campaign), and no regression for leader/coordinador roles"
affects: [requirements, v1.2-milestone-close]

# Tech tracking
tech-stack:
  added: []
  patterns: ["single-route Route::middleware() block split out of a shared role group, to grant one extra role access without broadening the whole group"]

key-files:
  created:
    - tests/Feature/Coordinator/ArticuladorLeadersExportReachabilityTest.php
  modified:
    - routes/web.php
    - app/Filament/Widgets/TopLeadersTable.php

key-decisions:
  - "leaders/export pulled out of the shared coordinator role group into its own Route::middleware() block (role:coordinator,area_coordinator,admin_campaign,super_admin) rather than adding area_coordinator to the shared group, to avoid opening the rest of the coordinador panel (dashboard, leaders CRUD, voter pages) to articuladores"
  - "TopLeadersTable gets a second, distinctly-labeled header action ('Exportar Equipo Completo') rather than reusing/extending the existing 'export' action, since the two exports have different row counts/column sets (top-10 ranking vs. full flat team)"

requirements-completed: [AUTHZ-01]

# Metrics
duration: ~20min
completed: 2026-08-12
---

# Phase 18 Plan 01: Articulador Leaders Export Reachability Summary

**Closed the AUTHZ-01 reachability gap by splitting `coordinator.leaders.export` into its own route group (adding `area_coordinator`) and adding a second, clearly-labeled export action to `TopLeadersTable`, proven by a 5-test regression suite covering reachability, cross-articulador isolation, and cross-campaign isolation.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-08-12T03:59:56Z
- **Tasks:** 3/3 completed
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments
- An articulador (`area_coordinator` role) can now `GET /coordinator/leaders/export` and receive their own transitive líder team as a downloadable xlsx, without any change to the query logic already built in Phase 13.
- The rest of the `coordinator.*` route group (dashboard, leaders CRUD, voter pages) remains restricted to `coordinator`/`admin_campaign`/`super_admin` only — no broadening beyond the single export route.
- `TopLeadersTable` (registered on both `CoordinatorPanelProvider` and `AreaCoordinatorPanelProvider`) now exposes a second header action, "Exportar Equipo Completo", giving the articulador a discoverable UI path to the download.
- New regression test proves both cross-articulador leakage (a different articulador's team) and cross-campaign leakage (a team member attached to a campaign the articulador doesn't belong to) are impossible through this route.

## Task Commits

Each task was committed atomically:

1. **Task 1: Make coordinator.leaders.export reachable by area_coordinator** - `bb89297` (feat)
2. **Task 2: Add exportTeam action to TopLeadersTable** - `c722681` (feat)
3. **Task 3: Regression test for reachability, isolation, no regression** - `64ace54` (test)

**Plan metadata:** (this commit)

## Files Created/Modified
- `routes/web.php` - `leaders/export` split out of the `coordinator` role group into its own `Route::middleware(['auth', 'role:coordinator,area_coordinator,admin_campaign,super_admin'])` block; added explicit `LeadersExportController` import, removed the prior inline `\App\Http\Controllers\Coordinator\LeadersExportController::class` reference
- `app/Filament/Widgets/TopLeadersTable.php` - added `Action::make('exportTeam')` header action, labeled "Exportar Equipo Completo", linking to `route('coordinator.leaders.export')`
- `tests/Feature/Coordinator/ArticuladorLeadersExportReachabilityTest.php` - new file, 5 tests covering route reachability, team-isolation (cross-articulador and cross-campaign), no-regression for leader (403) and coordinador (200), and the widget's UI trigger

## Decisions Made
- Route split (not shared-group broadening) — see key-decisions above; keeps the blast radius of this change to exactly one route.
- Second distinct header action on `TopLeadersTable` rather than reusing the existing `export` action — the two exports (top-10 ranking vs. full flat team) have materially different output, and conflating them would either silently cap the full-team export at 10 rows or drop the ranking export's own distinct behavior.

## Deviations from Plan

None - plan executed exactly as written. All 3 tasks' acceptance criteria passed on first implementation; the plan's literal test code (Task 3) ran green with zero fixture/assertion adjustments needed.

## Verification

- `php artisan test --filter=ArticuladorLeadersExportReachabilityTest` — 5/5 passed (9 assertions)
- `php artisan test --filter=LeadersExportTest` — 4/4 passed (no regression)
- `php artisan test --filter=ArticuladorTeamResolutionTest` — 5/5 passed (no regression)
- `php artisan test --filter=TopLeadersExportTest` — 2/2 passed (no regression)
- `vendor/bin/pint --dirty --test` — passed, no style issues
- `php artisan route:list --name=coordinator.leaders.export` — shows exactly one route: `GET coordinator/leaders/export`

## Known Stubs

None.

## Self-Check: PASSED
