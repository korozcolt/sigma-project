---
phase: 15-articulador-self-service-panel
plan: 01
subsystem: auth
tags: [filament, spatie-permission, panel-provider, middleware, rbac]

# Dependency graph
requires:
  - phase: 14-articulador-admin-resource-hierarchy-wiring
    provides: area_coordinator_user_id hierarchy column, UserRole::AREA_COORDINATOR enum case
provides:
  - Working, role-gated /articulador Filament panel (Dashboard + Día D + 3 widgets)
  - User::canAccessPanel() 'area_coordinator' arm
  - RedirectBasedOnRole UX-parity redirect for articulador role
affects: [15-02, 15-03, 15-04 (Volt CRUD half of ARTIC-02, shares the /articulador prefix by convention, zero file overlap)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "New role-specific Filament panel = byte-for-byte structural copy of an existing PanelProvider (CoordinatorPanelProvider) with only id/path/authMiddleware role changed"
    - "Every new panel id requires both a bootstrap/providers.php registration AND a User::canAccessPanel() match arm — the FilamentUser contract is checked independently of authMiddleware"

key-files:
  created:
    - app/Providers/Filament/AreaCoordinatorPanelProvider.php
    - tests/Feature/Filament/AreaCoordinatorPanelAccessTest.php
  modified:
    - bootstrap/providers.php
    - app/Models/User.php
    - app/Http/Middleware/RedirectBasedOnRole.php
    - tests/Feature/Middleware/RoleMiddlewareTest.php

key-decisions:
  - "AreaCoordinatorPanelProvider is an exact structural mirror of CoordinatorPanelProvider (same pages, same 3 widgets, same middleware/colors/font/logo) with only id('area_coordinator'), path('articulador'), and the authMiddleware role argument changed to AREA_COORDINATOR — per plan's explicit interfaces block"
  - "authMiddleware restricts to AREA_COORDINATOR only (matching CoordinatorPanelProvider's existing pattern of restricting to a single role in authMiddleware even though canAccessPanel() is broader) — admin_campaign/super_admin pass canAccessPanel() but would still need the EnsureUserHasRole role list widened if direct HTTP access for those roles is ever required; not in this plan's scope (mirrors the pre-existing coordinator panel's identical asymmetry)"

requirements-completed: [ARTIC-02]

# Metrics
duration: 25min
completed: 2026-08-10
---

# Phase 15 Plan 01: Articulador Filament Panel Registration Summary

**AreaCoordinatorPanelProvider registered at `/articulador` with Dashboard/Día D pages, gated by a new `canAccessPanel()` arm and a `RedirectBasedOnRole` UX-parity branch — the pure-Filament half of ARTIC-02.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-08-10
- **Tasks:** 2/2 completed
- **Files modified:** 6 (2 created, 4 modified)

## Accomplishments
- An articulador can now log in and reach `/articulador` (Filament Dashboard + Día D pages, with `CampaignStatsOverview`/`TerritorialDistributionChart`/`TopLeadersTable` widgets) without any admin-panel access.
- Non-articulador roles (e.g. coordinador) get a real 403 when hitting `/articulador`, not a silent redirect.
- An articulador landing on the generic `/dashboard` route is now auto-redirected to their own panel, matching every other role's existing UX.

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AreaCoordinatorPanelProvider, register it, add the required canAccessPanel() arm** - `15d9c46` (feat)
2. **Task 2: Add AREA_COORDINATOR branch to RedirectBasedOnRole middleware** - `c138302` (feat)

_Note: worktree provisioning (see Deviations) happened before Task 1, not committed as a plan task._

## Files Created/Modified
- `app/Providers/Filament/AreaCoordinatorPanelProvider.php` - New Filament panel (id=area_coordinator, path=articulador), byte-for-byte structural mirror of `CoordinatorPanelProvider`
- `bootstrap/providers.php` - Registered `AreaCoordinatorPanelProvider::class` after `AdminPanelProvider::class`
- `app/Models/User.php` - Added `'area_coordinator' => $this->hasAnyRole(['area_coordinator', 'admin_campaign', 'super_admin'])` arm to `canAccessPanel()`
- `app/Http/Middleware/RedirectBasedOnRole.php` - Added `AREA_COORDINATOR` redirect branch (to `filament.area_coordinator.pages.dashboard`) and matching `isCorrectDashboard()` map entry
- `tests/Feature/Filament/AreaCoordinatorPanelAccessTest.php` - 6 tests: 4 `canAccessPanel()` unit cases (area_coordinator/coordinator/admin_campaign/super_admin) + 2 HTTP cases (200 for articulador, 403 for coordinador)
- `tests/Feature/Middleware/RoleMiddlewareTest.php` - 2 new tests for the `AREA_COORDINATOR` redirect branch, mirroring the existing coordinator test's exact structure

## Decisions Made
- `AreaCoordinatorPanelProvider` built as an exact structural copy of `CoordinatorPanelProvider` per the plan's verbatim interfaces block — zero deviation in pages/widgets/middleware/colors.
- `canAccessPanel()`'s new `'area_coordinator'` arm mirrors the existing `'coordinator'` arm's `admin_campaign`/`super_admin` pass-through convention exactly.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree was stale — fast-forwarded to main's HEAD and reprovisioned**
- **Found during:** Session start, before Task 1
- **Issue:** This worktree (`agent-a2c656a9bad08aa6c`) was checked out at commit `760a354`, 53 commits behind `main` — missing Phases 13/14/15 entirely (including this plan's own `15-01-PLAN.md`), plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. This is the same recurring worktree-staleness class documented repeatedly in STATE.md's Blockers/Concerns section.
- **Fix:** Confirmed `760a354` is a fast-forward ancestor of `main` (`git merge-base --is-ancestor`), ran `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, `npm install`, and `npm run build` (Vite manifest was missing, causing an initial `assertOk()` test failure resolved once the manifest existed).
- **Files modified:** None tracked in git (environment-only: `.env`, `vendor/`, `node_modules/`, `public/build/`). `package-lock.json` was incidentally touched by `npm install` (cosmetic `name` field only, reverted with `git checkout -- package-lock.json` before committing).
- **Verification:** `git log --oneline -5` after fast-forward showed the expected Phase 15 plan-creation commit (`5cf77d0`) at HEAD; `php artisan test --filter=AreaCoordinatorPanelAccessTest` passed 6/6 after the manifest was built.
- **Committed in:** Not committed (environment provisioning, no source changes) — recorded here per the standing STATE.md precedent for this recurring issue class.

---

**Total deviations:** 1 auto-fixed (blocking, environment provisioning only — zero source-code deviation from the plan).
**Impact on plan:** None on scope; both tasks executed exactly as specified in the plan's `<action>` steps.

## Issues Encountered
- Same recurring `gsd-tools.cjs` worktree-staleness class documented 6+ times in STATE.md's Blockers/Concerns — not re-litigated here beyond the Deviations entry above, since STATE.md already tracks it as an open, carried-forward blocker.

## Next Phase Readiness
- Plans 15-02/15-03/15-04 (the Volt CRUD half of ARTIC-02) have zero file overlap with this plan and can proceed independently — they share the `/articulador` URL prefix only by convention (D-06), not by any structural dependency on `AreaCoordinatorPanelProvider`.
- `ARTIC-02` is NOT marked fully complete by this plan alone — this plan only covers the Filament panel half (D-05/D-06); the Volt CRUD half (list/create/edit coordinadores) is built in 15-02/15-03/15-04. Deferring full requirement sign-off to phase completion, matching the project's established split-requirement precedent (Phase 10, Phase 11).

## Self-Check: PASSED

All 6 files confirmed present on disk (`app/Providers/Filament/AreaCoordinatorPanelProvider.php`, `tests/Feature/Filament/AreaCoordinatorPanelAccessTest.php`, `bootstrap/providers.php`, `app/Models/User.php`, `app/Http/Middleware/RedirectBasedOnRole.php`, `tests/Feature/Middleware/RoleMiddlewareTest.php`). Both task commits confirmed in `git log --oneline --all` (`15d9c46`, `c138302`).

---
*Phase: 15-articulador-self-service-panel*
*Plan: 01*
*Completed: 2026-08-10*
