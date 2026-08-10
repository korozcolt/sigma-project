---
phase: 15-articulador-self-service-panel
plan: 02
subsystem: ui
tags: [livewire, volt, flux, routing, articulador, coordinador]

# Dependency graph
requires:
  - phase: 14-articulador-admin-resource-hierarchy-wiring
    provides: "area_coordinator role, User::coordinators()/areaCoordinator() relations, area_coordinator_user_id FK"
provides:
  - "/articulador route group (3 D-02-locked routes: coordinadores, coordinadores/create, coordinadores/{coordinator}/edit)"
  - "articulador.coordinators Volt list page — own-team scoped, searchable, paginated"
affects: [15-03-create-coordinator, 15-04-edit-coordinator]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Volt class-based component with #[Url(as: 'q')] search + WithPagination, mirroring coordinator/leaders.blade.php"
    - "Own-team scoping: hasRole(AREA_COORDINATOR) gates a where('area_coordinator_user_id', auth()->id()) filter; admin/super_admin fall through unfiltered (campaign-scoped only via CampaignMembershipScope)"

key-files:
  created:
    - resources/views/livewire/articulador/coordinators.blade.php
    - tests/Feature/Articulador/CoordinatorsListTest.php
  modified:
    - routes/web.php

key-decisions:
  - "routes/web.php owned exclusively by this plan for the whole phase — all 3 D-02 routes registered in one shot so wave-2 plans (15-03/15-04) never touch the file, avoiding merge conflicts"
  - "No dashboard route or root redirect added for /articulador — the Filament panel from Phase 15-01 is the sole dashboard surface (D-06)"
  - "Skipped the invitation-link button (D-07 deferred) and the coordinator/leaders.blade.php self-promote block (not applicable to articulador) per plan's explicit interface notes"

patterns-established:
  - "Spanish URL segment (coordinadores) under /articulador is a deliberate, reviewed exception to the project's usual English URL-segment convention"

requirements-completed: []

# Metrics
duration: 12min
completed: 2026-08-10
---

# Phase 15 Plan 02: Articulador Route Group + Coordinadores List Summary

**Registered the 3 D-02-locked `/articulador` routes and built the own-team-scoped `coordinadores` list Volt page, the entry point of the articulador self-service panel.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-08-10T22:27:00Z
- **Completed:** 2026-08-10T22:39:00Z
- **Tasks:** 2 completed
- **Files modified:** 3 (1 modified, 2 created)

## Accomplishments
- `/articulador` route group registered with exactly 3 routes (`coordinadores`, `coordinadores/create`, `coordinadores/{coordinator}/edit`), middleware-restricted to `area_coordinator,admin_campaign,super_admin`
- `articulador.coordinators` Volt list page built: search, pagination, stats (total coordinadores, total líderes), own-team scoping, and matching empty states
- Full TDD cycle for the list page: RED (4 failing tests against a temporarily-removed component) → GREEN (component restored, all 4 pass) → style pass (Pint)

## Task Commits

1. **Task 1: Register the /articulador route group (all 3 D-02 routes)** - `2567920` (feat)
2. **Task 2: Build the coordinadores list Volt page (TDD)**
   - `2945311` (test) — 4 failing tests (RED)
   - `6fdf804` (feat) — Volt component implementation (GREEN)
   - `eb810db` (style) — Pint formatting fix (unused `with()` import removed from group-use, brace style)

**Plan metadata:** (pending — this commit)

## Files Created/Modified
- `routes/web.php` - Added `// Articulador routes` group (3 Volt routes) between the `// Coordinator routes` and `// Leader routes` blocks
- `resources/views/livewire/articulador/coordinators.blade.php` - Class-based Volt list page: own-team-scoped `with()` query, search, pagination, stats, empty states, edit link
- `tests/Feature/Articulador/CoordinatorsListTest.php` - 4 tests covering own-team scoping, search, admin cross-team visibility, empty state

## Decisions Made
- `routes/web.php` is this plan's exclusive claim for the entire phase (per plan frontmatter `files_modified`) — all 3 D-02 routes registered now even though only the list view exists yet, so the wave-2 plans (15-03 create, 15-04 edit) never need to touch this file.
- Followed the plan's explicit correction over the existing `coordinator/leaders.blade.php` outlier: used the unqualified `UserRole::AREA_COORDINATOR->value` (already `use`-imported) throughout the Blade markup instead of imitating `leaders.blade.php`'s inline fully-qualified `\App\Enums\UserRole::...` references (CLAUDE.md explicit-imports rule).
- Pint auto-fixed the group-use import (`use function Livewire\Volt\{layout, with};` → `use function Livewire\Volt\layout;`, since `with()` wasn't actually used as a Volt helper — the class defines its own `with()` method) and normalized the anonymous class brace style. Style-only, zero behavior change, committed separately as a follow-up style commit for traceability.

## Deviations from Plan

**None affecting scope or behavior.** One trivial auto-fix:

### Auto-fixed Issues

**1. [Rule 1 - Bug/Style] Pint reformatted the Volt component's import + brace style**
- **Found during:** Task 2, post-implementation Pint pass
- **Issue:** The plan's literal code snippet imported `{layout, with}` from `Livewire\Volt`, but `with` was never called as the Volt-provided helper (the class already defines its own `with(): array` method), and the anonymous class used same-line braces inconsistent with the project's Pint/PSR-12 config.
- **Fix:** Ran `vendor/bin/pint` on the new file; it removed the unused `with` import and moved the class's opening brace to its own line.
- **Files modified:** `resources/views/livewire/articulador/coordinators.blade.php`
- **Verification:** `php artisan test --filter=CoordinatorsListTest` — all 4 tests still pass after the fix.
- **Committed in:** `eb810db`

---

**Total deviations:** 1 auto-fixed (Rule 1, style-only)
**Impact on plan:** None — purely cosmetic Pint correction, no behavior or scope change.

## Issues Encountered
- Worktree (`agent-a19a2c2ddd313ecce`) was 53 commits behind `main` at session start — missing all of Phase 12/13/14's completed work, this phase's own PLAN.md, `vendor/`, `.env`, and `public/build`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), ran `git merge --ff-only main`, copied `.env` and `public/build` from the main checkout, ran `composer install`. Same recurring staleness class documented repeatedly in STATE.md's Blockers/Concerns section across prior phases.
- Full-suite regression check (`tests/Feature/RoleBasedRedirectTest`) initially failed with "Vite manifest not found" — not a code regression, just the missing `public/build` copy noted above; resolved by copying it from the main checkout before re-running (11/11 pass afterward).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `routes/web.php` is fully owned by this plan and will not need further edits by wave-2 plans (15-03 create-coordinator, 15-04 edit-coordinator) — both already have named routes (`articulador.coordinadores.create`, `articulador.coordinadores.edit`) pointing at Volt view names those plans will create.
- The list page's "Agregar Coordinador" button and empty-state CTA already link to `articulador.coordinadores.create`, and each row's "Editar" button links to `articulador.coordinadores.edit` — both routes resolve today (registered in Task 1) but will 404 with `ComponentNotFoundException` until 15-03/15-04 create their respective Volt views. This is expected and matches the plan's phased build-out.
- No blockers for wave-2 plans.

---
*Phase: 15-articulador-self-service-panel*
*Completed: 2026-08-10*

## Self-Check: PASSED

- FOUND: resources/views/livewire/articulador/coordinators.blade.php
- FOUND: tests/Feature/Articulador/CoordinatorsListTest.php
- FOUND: routes/web.php
- FOUND: .planning/phases/15-articulador-self-service-panel/15-02-SUMMARY.md
- FOUND commit: 2567920 (feat: register /articulador route group)
- FOUND commit: 2945311 (test: failing tests, RED)
- FOUND commit: 6fdf804 (feat: implement coordinadores list, GREEN)
- FOUND commit: eb810db (style: Pint formatting)
