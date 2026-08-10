---
phase: 15-articulador-self-service-panel
verified: 2026-08-10T23:02:22Z
status: gaps_found
score: 9/10 must-haves verified
gaps:
  - truth: "An articulador manages their coordinadores from a dedicated self-service panel, mirroring the existing coordinador self-service experience"
    status: partial
    reason: "All three coordinador management pages exist, are substantive, are correctly scoped/authorized, and cross-link to each other — but nothing links INTO them. The articulador lands on the Filament panel dashboard after login (RedirectBasedOnRole), and that panel registers no navigation item for /articulador/coordinadores. The shared Volt sidebar has no area_coordinator branch either, so it falls through to the generic 'Platform' group whose only link is route('dashboard') — which bounces straight back to the Filament dashboard. The pages are reachable only by typing the URL manually. The coordinador experience this phase mirrors has a full sidebar group (Dashboard, Líderes, Día D)."
    artifacts:
      - path: app/Providers/Filament/AreaCoordinatorPanelProvider.php
        issue: "No navigationItems() entry pointing to route('articulador.coordinadores'); panel registers only Dashboard + DiaD pages"
      - path: resources/views/components/layouts/app/sidebar.blade.php
        issue: "flux:navlist has admin_campaign and coordinator branches but no area_coordinator branch; articulador falls into the generic @else 'Platform' group"
    missing:
      - "Add a navigation item in AreaCoordinatorPanelProvider linking to route('articulador.coordinadores') (e.g. ->navigationItems([NavigationItem::make('Coordinadores')->url(fn () => route('articulador.coordinadores'))->icon(...)]))"
      - "Add an area_coordinator branch to resources/views/components/layouts/app/sidebar.blade.php with Dashboard (Filament panel) + Coordinadores links, mirroring the existing coordinator branch"
      - "Optionally show the 'Articulador' role label in the sidebar campaign header, mirroring the admin_campaign/coordinator labels"
human_verification:
  - test: "Log in as an articulador and confirm the /articulador panel dashboard widgets (CampaignStatsOverview, TerritorialDistributionChart, TopLeadersTable) render campaign-appropriate numbers and do not leak another articulador's or another campaign's data"
    expected: "Widgets render without error and show only data the articulador is entitled to see"
    why_human: "Widget data scoping is inherited from shared widget classes and CampaignMembershipScope; visual/aggregate correctness cannot be confirmed by grep"
  - test: "On the create-coordinador form, type a cédula that exists in the national identity directory, blur the field, then click the unlock control and edit the name"
    expected: "Name autofills and locks on match, unlock control re-enables editing — identical feel to create-leader.blade.php"
    why_human: "Live blur/lock interaction and visual parity with the coordinador form require a real browser (project convention: browser-verify before prod)"
---

# Phase 15: Articulador Self-Service Panel Verification Report

**Phase Goal:** An articulador manages their own coordinadores from a dedicated self-service panel, mirroring the existing coordinador self-service experience.
**Verified:** 2026-08-10T23:02:22Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | ------- | ---------- | -------------- |
| 1 | Articulador reaches their own Filament panel at `/articulador` (Dashboard + Día D) without admin-panel access | ✓ VERIFIED | `AreaCoordinatorPanelProvider` id=`area_coordinator` path=`articulador`; registered in `bootstrap/providers.php`; `route:list` resolves `filament.area_coordinator.pages.dashboard` + `.dia-d`; test "articulador reaches the /articulador panel dashboard" passes |
| 2 | Non-articulador roles denied with 403, not a silent redirect | ✓ VERIFIED | `authMiddleware` includes `EnsureUserHasRole:area_coordinator`; `User::canAccessPanel()` has `'area_coordinator'` arm (User.php:253); test "coordinador is forbidden from the /articulador panel" passes |
| 3 | Articulador on generic `/dashboard` auto-redirects to their own panel | ✓ VERIFIED | `RedirectBasedOnRole` has AREA_COORDINATOR branch → `filament.area_coordinator.pages.dashboard`, plus `isCorrectDashboard()` map entry; 2 passing tests |
| 4 | `/articulador` route group exists with all 3 D-02-locked routes, no dashboard route (D-06) | ✓ VERIFIED | routes/web.php:99-103 — exactly 3 Volt routes, no `dashboard` route; all resolve in `route:list` |
| 5 | Articulador sees only coordinadores where `area_coordinator_user_id` = own id | ✓ VERIFIED | coordinators.blade.php:30-32 applies the filter behind a `hasRole(AREA_COORDINATOR)` guard; test "an articulador sees only their own coordinadores" passes |
| 6 | admin_campaign/super_admin see all coordinadores (campaign-scoped, no owner filter) | ✓ VERIFIED | Filter is role-gated so admins skip it; test "an admin_campaign sees coordinadores belonging to multiple different articuladores" passes |
| 7 | Created coordinador auto-linked via `area_coordinator_user_id`, field never shown/editable (D-03) | ✓ VERIFIED | create-coordinator.blade.php:123-125 derives id from `auth()`, passed at :138; no form field; tests "creates a coordinador linked…", "the rendered form does not contain an articulador field", "admin_campaign actor creates a coordinador with a null area_coordinator…" pass |
| 8 | Creating a coordinador requires no phone OTP (D-04) | ✓ VERIFIED | No OTP code path in the component; test "the rendered form does not contain OTP verification elements" passes |
| 9 | Articulador denied (403, clear reason) editing a coordinador they do not own, via CoordinatorPolicy | ✓ VERIFIED | edit-coordinator.blade.php:54 `abort_unless(auth()->user()->can('update', $coordinator), 403)`; `CoordinatorPolicy::update()` → `authorizeOwnership()` returns `Response::deny('Este coordinador no pertenece a tu equipo de articulador.')`; policy registered in AuthServiceProvider.php:26; test "denied editing a coordinador belonging to a different…" passes |
| 10 | Articulador manages coordinadores **entirely from their own panel**, mirroring the coordinador experience | ✗ FAILED | Pages exist and work, but no navigation path leads to them. `AreaCoordinatorPanelProvider` registers zero navigation items; shared sidebar has no `area_coordinator` branch. Reachable only by manually typing the URL. See Gaps Summary. |

**Score:** 9/10 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| -------- | ----------- | ------ | ------- |
| `app/Providers/Filament/AreaCoordinatorPanelProvider.php` | Panel registration, pages, widgets, role-locked authMiddleware | ⚠️ PARTIAL | 79 lines, fully substantive and wired — but missing navigation to the coordinadores pages |
| `app/Models/User.php` | `canAccessPanel()` `area_coordinator` arm | ✓ VERIFIED | Line 253; without it the panel would 403 (RESEARCH Pitfall 1) |
| `app/Http/Middleware/RedirectBasedOnRole.php` | AREA_COORDINATOR redirect branch | ✓ VERIFIED | Redirect branch + `isCorrectDashboard()` map entry both present |
| `routes/web.php` | `/articulador` group, 3 named routes | ✓ VERIFIED | Lines 99-103, role middleware `area_coordinator,admin_campaign,super_admin` |
| `resources/views/livewire/articulador/coordinators.blade.php` | List — search, pagination, stats, empty state, own-team scoping | ✓ VERIFIED | 175 lines; real paginated query, all elements present |
| `resources/views/livewire/articulador/create-coordinator.blade.php` | Create — full field set, no OTP, no articulador field | ✓ VERIFIED | 321 lines; real `User::create()`, role assign, campaign attach |
| `resources/views/livewire/articulador/edit-coordinator.blade.php` | Edit — policy-gated, no `area_coordinator_user_id`, optional password | ✓ VERIFIED | 256 lines; real `update()`, password preserved when blank (:114) |
| `resources/views/components/layouts/app/sidebar.blade.php` | (implied by "mirroring") articulador nav group | ✗ MISSING | No `area_coordinator` branch — articulador falls into generic `@else` |

### Key Link Verification

| From | To | Via | Status | Details |
| ---- | --- | --- | ------ | ------- |
| `bootstrap/providers.php` | `AreaCoordinatorPanelProvider` | provider array | ✓ WIRED | Present in providers list |
| `User::canAccessPanel()` | panel id `area_coordinator` | match arm `hasAnyRole` | ✓ WIRED | Allows area_coordinator, admin_campaign, super_admin |
| `RedirectBasedOnRole` | `filament.area_coordinator.pages.dashboard` | `hasRole(AREA_COORDINATOR)` branch | ✓ WIRED | Both redirect and no-op-when-already-there paths |
| `routes/web.php` | `articulador.coordinators` Volt view | `Volt::route('coordinadores', ...)` | ✓ WIRED | Route resolves; view file exists |
| `coordinators.blade.php with()` | `users.area_coordinator_user_id` | role-gated `where()` filter | ✓ WIRED | Line 31 |
| `create-coordinator.blade.php save()` | `users.area_coordinator_user_id` | auto-set from `auth()->id()` | ✓ WIRED | Lines 123-125, 138 |
| `create-coordinator.blade.php updatedDocumentNumber()` | `IdentityLookupService::findByDocumentNumber()` | blur autofill | ✓ WIRED | Line 77, name lock at :81, `unlockName()` at :85 |
| `edit-coordinator.blade.php mount()` | `CoordinatorPolicy::update()` | `abort_unless(...can('update'...), 403)` | ✓ WIRED | Line 54; policy mapped `User::class => CoordinatorPolicy::class` |
| Filament panel dashboard | `articulador.coordinadores` | navigation item | ✗ NOT_WIRED | No `navigationItems()` / `NavigationItem` anywhere in `app/` |
| Shared Volt sidebar | `articulador.coordinadores` | `flux:navlist` role branch | ✗ NOT_WIRED | No `area_coordinator` branch in any layout file |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| -------- | ------------- | ------ | ------------------ | ------ |
| `coordinators.blade.php` | `$coordinators`, `$totalCoordinators`, `$totalLeaders` | `User::role(COORDINATOR)->withCount('leaders')` paginated | Yes — real Eloquent query, no static fallback | ✓ FLOWING |
| `create-coordinator.blade.php` | `$this->municipalities`, `$this->neighborhoods` | `Municipality`/`Neighborhood` queries scoped by `CampaignContext` | Yes | ✓ FLOWING |
| `create-coordinator.blade.php` | `$this->name` (autofill) | `IdentityLookupService::findByDocumentNumber()` | Yes — service call, name set from result | ✓ FLOWING |
| `edit-coordinator.blade.php` | form fields | hydrated from bound `User $coordinator` in `mount()` | Yes — real model, lines 57-65 | ✓ FLOWING |

Note: `$totalLeaders` uses `$query->clone()->get()->sum('leaders_count')`, which loads all matching rows into memory rather than aggregating in SQL. Correct, but will degrade for articuladores with very large teams. Informational only — not a goal blocker.

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| -------- | ------- | ------ | ------ |
| Routes register and resolve | `php artisan route:list \| grep articulador` | 3 Volt routes + panel dashboard + dia-d + logout | ✓ PASS |
| Phase test suites pass | `php artisan test tests/Feature/Articulador/ tests/Feature/Filament/AreaCoordinatorPanelAccessTest.php tests/Feature/Middleware/RoleMiddlewareTest.php` | 49 passed (87 assertions), 3.29s | ✓ PASS |
| Code style clean | `vendor/bin/pint --test` on phase files | PASS, 3 files | ✓ PASS |
| Navigation entry point exists | `grep -rn "articulador.coordinadores" app/ resources/ routes/` | Only self-references inside the 3 Volt pages | ✗ FAIL |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| ----------- | ---------- | ----------- | ------ | -------- |
| ARTIC-02 | 15-01, 15-02, 15-03, 15-04 | Articulador crea y gestiona coordinadores desde su propio panel de auto-gestión (mirroring el panel de auto-gestión que ya tiene coordinador) | ⚠️ PARTIAL | All CRUD logic, scoping, and authorization implemented and tested (49 passing tests). The "mirroring el panel de auto-gestión que ya tiene coordinador" clause is not fully satisfied: the coordinador mirror has a sidebar nav group, the articulador has none. |

No orphaned requirements — ARTIC-02 is the only ID mapped to Phase 15 in REQUIREMENTS.md, and all 4 plans declare it.

**REQUIREMENTS.md current state:** ARTIC-02 is `[ ]` (line 13) and marked **Pending** in the mapping table (line 64).

**Recommendation:** do NOT close ARTIC-02 yet. The requirement text explicitly invokes mirroring the coordinador self-service panel, and navigation is part of that mirror. Closing it after the navigation gap is addressed is safe — no other part of the requirement is outstanding.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| — | — | No TODO/FIXME/XXX/HACK/PLACEHOLDER in any phase file | — | Clean |
| — | — | No empty handlers, `console.log`, or stub returns | — | Clean |
| `AreaCoordinatorPanelProvider.php` | 76 | Inline namespace `\App\Http\Middleware\EnsureUserHasRole::class` violates the CLAUDE.md explicit-`use` rule | ℹ️ Info | Pre-existing repo pattern — `CoordinatorPanelProvider:76` and `LeaderPanelProvider:78` are identical. Faithful mirroring; fixing here alone would create inconsistency. |
| `coordinators.blade.php` | 44 | `->get()->sum()` instead of SQL aggregate | ℹ️ Info | Memory cost at large team sizes; correct output |

`layout('components.layouts::app', ...)` was checked and is the established repo-wide convention (13 Volt pages use the `::` form) — not a defect.

### Human Verification Required

See `human_verification` in frontmatter. Two items: articulador panel widget data scoping, and the cédula autofill lock/unlock interaction in a real browser (per the project's browser-verify-before-prod convention).

### Gaps Summary

The phase is functionally complete and well-tested. Every piece of business logic the goal requires — panel access gating, 403 denial for non-articuladores, post-login redirect, own-team list scoping, auto-linking on create, policy-gated ownership on edit, and deliberate omission of `area_coordinator_user_id` from both forms — exists in real code, is correctly wired, moves real data, and is covered by 49 passing tests.

The single gap is reachability. After login, `RedirectBasedOnRole` sends the articulador to the Filament panel dashboard. That panel registers only `Dashboard` and `DiaD` pages and zero navigation items, so nothing there points to `/articulador/coordinadores`. The three Volt pages render inside `components.layouts::app`, whose sidebar branches on `admin_campaign` and `coordinator` but not `area_coordinator` — the articulador falls into the generic `@else` "Platform" group whose only link is `route('dashboard')`, which redirects right back to the Filament dashboard. The result is a closed loop: the coordinador management surface is reachable only by typing the URL by hand.

This matters specifically because the goal is defined as mirroring the coordinador experience, and that experience does have a nav group (Dashboard, Líderes, Día D). The fix is small and additive — a navigation item on the panel plus an `area_coordinator` sidebar branch — and touches no logic verified above, so it carries low regression risk.

---

_Verified: 2026-08-10T23:02:22Z_
_Verifier: Claude (gsd-verifier)_
