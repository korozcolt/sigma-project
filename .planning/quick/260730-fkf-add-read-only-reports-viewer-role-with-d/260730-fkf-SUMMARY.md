---
phase: quick
plan: 260730-fkf
subsystem: auth
tags: [filament-panel, spatie-permission, policy, authorization, rbac]

# Dependency graph
requires: []
provides:
  - New `reports_viewer` UserRole (Spanish label "Analista de Reportes") with full HasLabel/HasColor/HasIcon/HasDescription enum contract
  - Read-only `VoterPolicy` denying all mutating abilities (create/update/delete/deleteAny/restore/restoreAny/forceDelete/forceDeleteAny/replicate/reorder) for reports_viewer only, registered in AuthServiceProvider
  - Dedicated `/reports` Filament panel (ReportsPanelProvider) with VoterResource + all 16 included report widgets, gated to reports_viewer
  - VoterResource::shouldRegisterNavigation() panel-aware nav hiding
  - Export/validateCensus/exportCurrent/duplicatesReport action buttons gated off for reports_viewer across 9 files
  - Seeded reports_viewer test user (ing.korozco+analista@gmail.com / password)
affects: [voters, reporting, rbac]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dedicated single-purpose Filament panel per read-only role (mirrors Coordinator/Leader panel pattern) rather than bolting a restricted view onto an existing panel"
    - "Policy-level denial (VoterPolicy) for CRUD abilities, combined with ->visible() closures for non-policy-gated action buttons (export/validateCensus/etc.) mirroring the existing hasAnyRole() precedent"

key-files:
  created:
    - app/Policies/VoterPolicy.php
    - app/Providers/Filament/ReportsPanelProvider.php
    - tests/Feature/Policies/VoterPolicyTest.php
    - tests/Feature/Filament/ReportsPanelTest.php
  modified:
    - app/Enums/UserRole.php
    - app/Providers/AuthServiceProvider.php
    - bootstrap/providers.php
    - app/Filament/Resources/Voters/VoterResource.php
    - app/Models/User.php
    - database/seeders/RoleUsersSeeder.php
    - app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php
    - app/Filament/Widgets/DuplicatesReportTable.php
    - app/Filament/Widgets/JurisdictionReportTable.php
    - app/Filament/Widgets/RejectionsReportTable.php
    - app/Filament/Widgets/TopCoordinatorsTable.php
    - app/Filament/Widgets/TopLeadersTable.php
    - app/Filament/Widgets/TopPollingPlacesTable.php
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - app/Filament/Resources/Voters/Pages/ListVoters.php
    - tests/Feature/RolePermissionTest.php

key-decisions:
  - "VoterResource is registered on the new 'reports' panel too (not a separate read-only resource) so the 3 existing drill-through widgets (FollowUpBacklogOverview, FallbackSourceOverview, TopLeadersTable) work unmodified — Filament's getRouteBaseName() resolves via Filament::getCurrentOrDefaultPanel() automatically."
  - "VoterPolicy handles Create/Edit/Delete/bulk-delete (both hidden buttons and 403 on direct URL); a handful of non-policy-gated action buttons (7x export headerAction, validateCensus, exportCurrent, export, duplicatesReport) needed explicit ->visible() gates mirroring the existing hasAnyRole() precedent (actualizar_registraduria)."
  - "User::canAccessPanel() required a new 'reports' arm (not called out in the plan) — without it every authenticated user, including reports_viewer, got a 403 from the panel regardless of role or the EnsureUserHasRole middleware."

requirements-completed: []

# Metrics
duration: 55min
completed: 2026-07-30
---

# Quick Task 260730-fkf: Read-only reports-viewer role Summary

**New `reports_viewer` role with a dedicated `/reports` Filament panel exposing all 16 report widgets read-only, enforced via a new VoterPolicy plus explicit action-button gating on 9 files.**

## Performance

- **Duration:** ~55 min
- **Started:** 2026-07-30T16:03:00Z (approx, includes worktree re-provisioning: composer install, npm install/build)
- **Completed:** 2026-07-30T16:58:00Z
- **Tasks:** 5
- **Files modified:** 20 (4 created, 16 modified)

## Accomplishments
- Added `REPORTS_VIEWER` UserRole case ("Analista de Reportes") with all 4 match() arms populated.
- Created `VoterPolicy` making Create/Edit/Delete/bulk-delete/restore/forceDelete/replicate/reorder impossible for `reports_viewer` (both hidden buttons and 403 on direct URL), zero behavior change for every other role — verified by a parametrized regression test over all other `UserRole::cases()`.
- Built a dedicated `/reports` Filament panel (mirroring Coordinator/Leader panel structure) registering `VoterResource` + exactly the 16 included report widgets from the plan's authoritative inventory, gated to `reports_viewer` via `EnsureUserHasRole`.
- The 3 existing drill-through widgets (FollowUpBacklogOverview, FallbackSourceOverview, TopLeadersTable) work inside the new panel with zero code changes, confirmed by the existing `WidgetDrillThroughTest` still passing.
- Gated the export headerAction on all 7 widgets that have one, plus `validateCensus`/`exportCurrent`/`export`/`duplicatesReport` on the Voters list/table, using the same `hasAnyRole()`-style precedent already used for `actualizar_registraduria`.
- Seeded a 6th test user (`ing.korozco+analista@gmail.com` / `password`) via `RoleUsersSeeder`.

## Task Commits

Each task was committed atomically:

1. **Task 1: REPORTS_VIEWER enum case + VoterPolicy** - `5d3c589` (feat)
2. **Task 2: ReportsPanelProvider - dedicated panel, VoterResource registration, widget list, nav hide, seeded user** - `b90dcf0` (feat)
3. **Task 3: Gate the export headerAction on the 7 report widgets that have one** - `172251a` (feat)
4. **Task 4: Gate the remaining non-policy-gated action buttons on the VoterResource drill target** - `0ea6968` (feat)
5. **Task 5: Feature tests - panel access, direct-URL 403s, and read-only table enforcement** - `7cd3069` (test, includes the `User::canAccessPanel()` Rule 3 auto-fix)

**Plan metadata:** (pending — final commit below)

## Files Created/Modified
- `app/Enums/UserRole.php` - Added REPORTS_VIEWER case with 4 match() arms
- `app/Policies/VoterPolicy.php` - New policy, view/viewAny true, all mutating abilities false for reports_viewer only
- `app/Providers/AuthServiceProvider.php` - Registered `Voter::class => VoterPolicy::class`
- `app/Providers/Filament/ReportsPanelProvider.php` - New dedicated panel at /reports
- `bootstrap/providers.php` - Registered ReportsPanelProvider
- `app/Filament/Resources/Voters/VoterResource.php` - `shouldRegisterNavigation()` hides Apoyos nav item only inside the reports panel
- `app/Models/User.php` - Added `'reports' => $this->hasRole('reports_viewer')` arm to `canAccessPanel()` (deviation, see below)
- `database/seeders/RoleUsersSeeder.php` - Seeds a 6th "Analista de Reportes" test user
- `app/Filament/Widgets/{ApoyosLideresCoordinadoresTable,DuplicatesReportTable,JurisdictionReportTable,RejectionsReportTable,TopCoordinatorsTable,TopLeadersTable,TopPollingPlacesTable}.php` - `->visible()` gate on the export Action
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - `validateCensus` action's `->visible()` extended with the role check
- `app/Filament/Resources/Voters/Pages/ListVoters.php` - `exportCurrent`/`export`/`duplicatesReport` actions gated
- `tests/Feature/RolePermissionTest.php` - Role-count assertion updated 5 -> 6
- `tests/Feature/Policies/VoterPolicyTest.php` - New: deny/allow matrix for VoterPolicy
- `tests/Feature/Filament/ReportsPanelTest.php` - New: panel access, direct-URL 403s, table/bulk-action visibility regression checks

## Decisions Made
- VoterResource registered directly on the reports panel (not duplicated as a separate read-only resource) so the 3 existing drill-through widgets need zero code changes — relies on Filament's `Filament::getCurrentOrDefaultPanel()` route resolution.
- Non-policy-gated action buttons (7x widget export actions, validateCensus, exportCurrent, export, duplicatesReport) gated with inline `->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))` closures, matching the codebase's existing `hasAnyRole()` gating precedent rather than introducing a new mechanism.
- `User::canAccessPanel()` needed a `'reports'` arm not called out in the plan (see Deviations).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `User::canAccessPanel()` had no arm for the new `reports` panel id**
- **Found during:** Task 5 (writing/running ReportsPanelTest)
- **Issue:** `canAccessPanel()` is a `match($panel->getId())` with an explicit arm per panel and `default => false`. The plan's action steps for Task 2 only covered `ReportsPanelProvider`'s `authMiddleware` (`EnsureUserHasRole`) but never mentioned this second, independent Filament authorization gate. Without an arm for `'reports'`, every authenticated user — including a correctly role-assigned `reports_viewer` — got a hard 403 from the panel itself, regardless of role or middleware.
- **Fix:** Added `'reports' => $this->hasRole('reports_viewer'),` alongside the existing per-panel arms in `app/Models/User.php`.
- **Files modified:** `app/Models/User.php`
- **Verification:** `ReportsPanelTest`'s "lets a reports_viewer user reach the reports panel dashboard" test failed with a 403 before the fix, passed after.
- **Committed in:** `7cd3069` (Task 5 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Essential for the panel to function at all for the target role. No scope creep — same file already implements this per-panel match pattern for every other panel.

## Issues Encountered
- **Stale worktree:** This worktree was missing `vendor/`, `.env`, `node_modules/`, and `public/build/` entirely (same class of issue documented repeatedly in STATE.md's Blockers/Concerns for prior quick tasks/phases in this repo). Resolved with the established workaround: confirmed HEAD is main's own commit (no merge needed), copied `.env` from the main checkout, ran `composer install`, `npm install`, `npm run build`. The `npm install`-generated `package-lock.json` name-field diff (worktree dir name vs `sigma-project`) was discarded via `git checkout --` rather than committed.
- **Full-suite run showed 11 pre-existing failures** (`DuplicatesReportTableTest`, `JurisdictionReportTableTest` x3, `RejectionsReportTableTest`, `TopCoordinatorsTableTest` x2, `TopPollingPlacesTableTest`, `UserResourceTest`, `VoterResourceTest`, `IsElectionDayMiddlewareTest`) — all confirmed to pass when run in isolation, matching the already-documented `CampaignContext` static-override test-pollution issue in STATE.md's Blockers/Concerns (unrelated to this task's changes). No new failures introduced.

## User Setup Required
None - no external service configuration required. A test account for manual verification was seeded: `ing.korozco+analista@gmail.com` / `password` (role: Analista de Reportes), reachable at `/reports` after running `php artisan db:seed --class=RoleUsersSeeder`.

## Next Phase Readiness
- `reports_viewer` role and `/reports` panel are fully functional and test-covered; ready for a human to browser-verify the panel visually (dashboard widget rendering, drill-through clicks) before considering this closed end-to-end.
- No blockers for other in-flight work.

---
*Phase: quick*
*Completed: 2026-07-30*

## Self-Check: PASSED

All created files confirmed present on disk; all 5 task commit hashes (5d3c589, b90dcf0, 172251a, 0ea6968, 7cd3069) confirmed present in git log. No missing items.
