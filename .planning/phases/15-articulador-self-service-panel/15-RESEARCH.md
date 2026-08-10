# Phase 15: Articulador Self-Service Panel - Research

**Researched:** 2026-08-10
**Domain:** Laravel 12 / Filament 4 (PanelProvider) / Livewire 3 Volt / Spatie Permission — self-service CRUD mirroring an existing pattern
**Confidence:** HIGH (every claim below verified by reading the actual project source, not training-data assumptions)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**CRUD architecture**
- **D-01:** The coordinador-management CRUD (list/create/edit) is built as **Volt pages**, not a Filament Resource — mirroring the real existing pattern (`resources/views/livewire/coordinator/{leaders,create-leader,edit-leader}.blade.php`), not the admin-style `AreaCoordinatorResource` from Phase 14. These are non-Filament routes rendered with the default `components.layouts::app` layout.
- **D-02:** New Volt views live under `resources/views/livewire/articulador/` (`coordinators.blade.php`, `create-coordinator.blade.php`, `edit-coordinator.blade.php`), routed under a new `/articulador` prefix group in `routes/web.php` (`Route::middleware(['auth','role:area_coordinator,...'])->prefix('articulador')->name('articulador.')`), mirroring the `/coordinator` prefix group structure exactly (`Volt::route('coordinadores', ...)`, `coordinadores/create`, `coordinadores/{coordinator}/edit`).
- **D-03:** `area_coordinator_user_id` is auto-set to the authenticated articulador on create (mirroring `create-leader.blade.php`'s `coordinator_user_id = auth()->id()` behavior when the actor is a coordinador) — not a user-facing field in the self-service create form.

**OTP verification**
- **D-04:** Creating a coordinador from the articulador self-service panel does **not** require phone OTP verification. This diverges from the leader-creation flow (`create-leader.blade.php`, which requires `OtpVerificationService` send+verify before save) and instead mirrors the admin-side `AreaCoordinatorForm`/`CoordinatorForm` pattern (no OTP step). Existing `IdentityLookupService` document-number autofill (name lock/unlock) should still be reused for UX consistency, since that's independent of the OTP question.

**Panel scope**
- **D-05:** A new `AreaCoordinatorPanelProvider` (Filament panel, `id('area_coordinator')`, `path('articulador')`) is created mirroring `CoordinatorPanelProvider` exactly: `Dashboard::class` + `DiaD::class` pages, and `CampaignStatsOverview`, `TerritorialDistributionChart`, `TopLeadersTable` widgets (these widgets already resolve an articulador's transitive team correctly per Phase 13/AUTHZ-01). Auth middleware restricts to `UserRole::AREA_COORDINATOR`.
- **D-06:** The Filament panel (Dashboard/Día D/widgets) and the Volt CRUD pages (D-01/D-02) are two separate, parallel surfaces sharing the `/articulador` URL prefix by convention — exactly how `/coordinator` works today (Filament panel for dashboard/DiaD, plain Volt routes for leader CRUD, no structural link between them).

**Invitation link**
- **D-07:** No shareable self-registration invitation link for coordinadores (no equivalent of `generateLeaderInvitationLink` / public `register-leader` view). Only direct in-form creation, per ROADMAP.md's literal success criterion 2 wording ("creates a new coordinador via a form on their panel"). Deferred as a future idea if operational need arises (see Deferred Ideas).

**Field scope / reassignment lock**
- **D-08:** The self-service edit-coordinator form does **not** expose the `area_coordinator_user_id` field at all (hidden/locked) — a coordinador stays permanently tied to the articulador who owns them from the self-service surface. Reassigning a coordinador to a different articulador (or removing the assignment) remains admin-only, via the existing `CoordinatorForm` Select in the admin panel (Phase 14). This prevents articuladores from reassigning coordinadores away from or into their own team.

### Claude's Discretion
- Exact form field set/layout for `articulador/create-coordinator` and `articulador/edit-coordinator` Volt views: mirror `create-leader.blade.php`/`edit-leader.blade.php` structure and styling (Flux components, stats summary, empty states, search) adapted to coordinador fields (name, email, document_number, birth_date, phone, secondary_phone, address, municipality_id, neighborhood_id, password/access) minus `area_coordinator_user_id` (per D-08) and minus OTP (per D-04).
- Whether to reuse `IdentityLookupService`-driven name-lock UX and municipality/neighborhood cascading selects exactly as the existing coordinador/leader forms do — yes, reuse as-is (established, working pattern).
- Query scoping for "my own coordinadores" on the list page: use `area_coordinator_user_id = auth()->id()` directly (mirrors `coordinator_user_id = $user->id` in `coordinator/leaders.blade.php`); the existing `CoordinatorPolicy` (Phase 13) already governs direct-record view/update authorization as a second layer.
- Whether the new Volt views need pagination/search matching `coordinator/leaders.blade.php` — yes, mirror exactly (search by name/email, paginated list).

### Deferred Ideas (OUT OF SCOPE)
- Shareable self-registration invitation link for coordinadores (mirroring `generateLeaderInvitationLink` + public `register-leader` flow) — explicitly deferred per D-07, not in ROADMAP.md's literal success criteria for this phase. Revisit only if a future milestone/phase calls for it.
- Self-service reassignment of a coordinador's articulador (moving a coordinador between articuladores, or detaching one) — explicitly kept admin-only per D-08.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ARTIC-02 | Articulador crea y gestiona coordinadores desde su propio panel de auto-gestión (mirroring el panel de auto-gestión que ya tiene coordinador) | This document verifies the exact route registration pattern, panel-provider registration, role-middleware string, ownership scoping, and the reference files (`edit-leader.blade.php` fully documented below) needed to build `AreaCoordinatorPanelProvider` + the three `articulador/*` Volt pages. |

</phase_requirements>

## Project Constraints (from CLAUDE.md)

- Laravel Boost conventions apply: `php artisan make:*` for all new files (`--no-interaction`), explicit `use` imports only (never `Forms\Components\X` inline namespace refs, never `\App\Models\X::class` inline paths — **note:** the existing `leaders.blade.php`/`create-leader.blade.php`/`edit-leader.blade.php` reference files actually violate this in their Blade sections with `\App\Enums\UserRole::COORDINATOR->value` inline — new code should use `use App\Enums\UserRole;` at the top of the Volt `<?php` class block and reference `UserRole::AREA_COORDINATOR->value` unqualified, not copy the inline-namespace anti-pattern verbatim).
- Validation: Form Request classes only, never inline in controllers — **N/A here**, this phase's writes happen via Livewire Volt component `#[Validate]` attributes / `$this->validate()`, which is the established pattern for every existing Volt CRUD page in this codebase (not a Form Request). This is consistent with the "follow existing code conventions, check sibling files" Boost rule.
- Filament 4: static `make()` methods, `Filament\Schemas\Components` namespace for layout components, `Filament\Support\Icons\Heroicon` enum for icons — applies only to the new `AreaCoordinatorPanelProvider` (D-05), not to the Volt CRUD pages (which use Blade/Flux, no Filament Schema components).
- Pint (PSR-12) — run `vendor/bin/pint --dirty` before finalizing.
- Tests: Pest only, `php artisan make:test --pest <name>`. Every change must have a test (project's "Test Enforcement" rule). Existing Volt test pattern documented below must be followed for the three new Volt pages.
- GSD workflow enforcement: this phase is already being executed through `/gsd:execute-phase`, satisfying the project's workflow-entry-point requirement.
- User's global preference ("English backend, Spanish UI"): PHP identifiers, method names, variable names in English; anything rendered to the end user (labels, messages, flash text) in Spanish — matches every existing reference file inspected.

## Summary

Phase 15 is a pure mirror job: every architectural question was already answered by inspecting the real, working coordinador self-service surface (`resources/views/livewire/coordinator/{leaders,create-leader,edit-leader}.blade.php`, the `/coordinator` route group in `routes/web.php`, and `CoordinatorPanelProvider`). Nothing new needs to be invented — the phase's job is disciplined copying with four deliberate subtractions (no OTP, no `area_coordinator_user_id` field, no invitation link, no `also_leader` toggle) and one deliberate addition (`User::canAccessPanel()` needs a new `'area_coordinator'` case — this was NOT explicitly called out in CONTEXT.md and is a real gap, documented below).

All eight verification questions from the phase brief were answered directly from source:
1. Panels are registered in **`bootstrap/providers.php`** only (Laravel 12's `withProviders`-less convention) — no `config/app.php` involvement, no `AppServiceProvider` involvement. Confirmed by reading the file: it lists `AdminPanelProvider`, `CoordinatorPanelProvider`, `LeaderPanelProvider`, `ReportsPanelProvider` explicitly.
2. `UserRole::AREA_COORDINATOR->value` is the literal string `'area_coordinator'`. The route group middleware string must be `role:area_coordinator,admin_campaign,super_admin` — this exact 3-role list mirrors the existing `role:coordinator,admin_campaign,super_admin` pattern (admin/super_admin get "pass-through" access to the self-service surface, same as coordinador's does today, and the same `Gate::before` + `CoordinatorPolicy` layer still applies underneath for record-level scoping).
3. `edit-leader.blade.php` does **not** call `CoordinatorPolicy`/`Gate::authorize` at all — its "ownership" check is a hand-written `abort(403)` comparing `coordinator_user_id !== auth()->id()`. `CoordinatorPolicy` is only auto-invoked by Filament Resources (via `Gate::allows('update', $record)` in `EditRecord`'s built-in authorization), never by a plain Volt route. **This is a real design choice the planner must make explicitly** — see Open Questions below.
4. No coordinador-list export exists or is implied. `LeadersExportController` already exports **leaders** (not coordinadores) and already resolves an articulador's transitive team via `User::teamCoordinatorUserIds()` — that's Phase 13/AUTHZ-01 work, already done, and it is a *leaders* export usable when an articulador visits `coordinator.leaders.export` (through their `AREA_COORDINATOR` role check), not a *coordinadores* export. No file anywhere references a "coordinators export" route or button. Confirmed out of scope for ARTIC-02.
5. `edit-leader.blade.php` fully read and documented below (Code Examples section) — this is the direct structural template for `articulador/edit-coordinator.blade.php`.
6. Volt test pattern confirmed: `Volt::test('coordinator.create-leader')` / `Volt::test('coordinator.leaders')` style (dot-notation view name, matching the Volt route's registered component string). No Filament panel-provider-level tests exist for `CoordinatorPanelProvider`/`LeaderPanelProvider` — panel access is tested indirectly via `User::canAccessPanel()` behavior, not a dedicated PanelProviderTest.
7. `bootstrap/providers.php` syntax confirmed (see Code Examples).
8. No dedicated factory state exists for `area_coordinator` role users — every existing test (`CoordinatorPolicyTest`, `ArticuladorTeamResolutionTest`, `AreaCoordinatorResourceCampaignTest`) uses the bare pattern `User::factory()->create()` + `->assignRole(UserRole::AREA_COORDINATOR->value)` + `->campaigns()->attach($campaign->id)`, with `Role::firstOrCreate(...)` seeded in `beforeEach` for every `UserRole::values()`. No new factory work needed; this pattern is the established convention.

**Primary recommendation:** Build strictly as a file-for-file mirror of the three coordinador self-service Volt files plus `CoordinatorPanelProvider`, applying the documented subtractions/substitutions, add the missing `canAccessPanel()` case, and make an explicit decision (not a silent omission) about whether `edit-coordinator.blade.php`/`coordinators.blade.php` reuse `CoordinatorPolicy` via `$this->authorize()` or rely purely on manual `abort_unless` scoping like `edit-leader.blade.php` does.

## Standard Stack

No new packages. Everything needed already exists in the codebase.

### Core
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|---------------|
| `livewire/volt` | ^1 (installed) | Class-based Volt components for `articulador/{coordinators,create-coordinator,edit-coordinator}` | Exact pattern used by every existing self-service surface in this codebase (D-01) |
| `filament/filament` | v4 (installed) | `AreaCoordinatorPanelProvider` (Dashboard + DiaD + 3 widgets) | D-05 explicit requirement |
| `spatie/laravel-permission` | 6.22 (installed) | `hasRole()`/`assignRole()`/`User::role()` scope, already used by every reference file | No alternative considered — already the project's sole RBAC layer |
| `livewire/flux` (FREE) | v2 (installed) | All form/list UI components (`flux:input`, `flux:select`, `flux:button`, `flux:heading`, etc.) | Matches every existing self-service view exactly |

### Alternatives Considered
None — CONTEXT.md's D-01 through D-08 already foreclose every architectural alternative (Filament Resource vs Volt, OTP vs no-OTP, invitation link vs none). This research found zero cases where the locked decisions conflict with what the code actually supports.

**Installation:** None required — zero new dependencies.

## Architecture Patterns

### Recommended Project Structure
```
app/Providers/Filament/
└── AreaCoordinatorPanelProvider.php      # mirrors CoordinatorPanelProvider exactly

resources/views/livewire/articulador/
├── coordinators.blade.php                # list — mirrors coordinator/leaders.blade.php
├── create-coordinator.blade.php          # create — mirrors coordinator/create-leader.blade.php minus OTP
└── edit-coordinator.blade.php            # edit — mirrors coordinator/edit-leader.blade.php

routes/web.php                            # new prefix('articulador') group, alongside prefix('coordinator')

bootstrap/providers.php                   # add AreaCoordinatorPanelProvider::class

app/Models/User.php                       # canAccessPanel(): add 'area_coordinator' => ... case
```

### Pattern 1: Route group registration (exact syntax to add)
**What:** A new `Route::middleware([...])->prefix('articulador')->name('articulador.')->group(...)` block in `routes/web.php`, placed after the existing `// Coordinator routes` block (before `// Leader routes`).
**Source:** `routes/web.php` lines ~86-96 (existing coordinator group, verified verbatim)
```php
// Articulador routes
Route::middleware(['auth', 'role:area_coordinator,admin_campaign,super_admin'])->prefix('articulador')->name('articulador.')->group(function () {
    Volt::route('coordinadores', 'articulador.coordinators')->name('coordinadores');
    Volt::route('coordinadores/create', 'articulador.create-coordinator')->name('coordinadores.create');
    Volt::route('coordinadores/{coordinator}/edit', 'articulador.edit-coordinator')->name('coordinadores.edit');
});
```
**Resolved finding on the "dashboard" question (verified this session, not left open):** the `/coordinator` group's `Route::redirect('/', '/coordinator/dashboard')` + `Volt::route('dashboard', 'coordinator.dashboard')` register a **real, distinct** Blade/Livewire dashboard (`resources/views/livewire/coordinator/dashboard.blade.php` — 40+ lines of its own leader/apoyo stats query, independently confirmed by reading the file), completely separate from the Filament `CoordinatorPanelProvider`'s own `Dashboard::class` page (which lives at the Filament panel's own root route, a different URI under a different routing subsystem — no collision exists today between the two, confirmed). **However**, D-02's locked decision literally enumerates only three routes for the `/articulador` group (`coordinadores`, `coordinadores/create`, `coordinadores/{coordinator}/edit`) — it does **not** include a `dashboard` route or a root redirect, and D-06 explains why: for articulador, the Filament panel (D-05: `Dashboard::class` + `DiaD::class` + 3 widgets) is intended to be the *sole* dashboard experience — there is no requirement to duplicate `coordinator/dashboard.blade.php`'s custom stats page for articulador. **Recommendation: do not add a `dashboard` Volt route or root redirect to the `/articulador` group — the three-route enumeration above is complete and matches D-02 verbatim.** (This resolves what would otherwise be an open question — see Pitfall 2 for the reasoning trail.)

### Pattern 2: Filament panel provider (exact mirror)
**What:** `AreaCoordinatorPanelProvider`, byte-for-byte structural copy of `CoordinatorPanelProvider.php` with `id`/`path` swapped and the auth-role swapped.
**Source:** `app/Providers/Filament/CoordinatorPanelProvider.php` (full file read, reproduced below)
```php
// Source: app/Providers/Filament/CoordinatorPanelProvider.php (verbatim reference — swap 3 lines for AreaCoordinatorPanelProvider)
->id('area_coordinator')          // was: ->id('coordinator')
->path('articulador')             // was: ->path('coordinator')
// ->pages([Dashboard::class, DiaD::class]) — unchanged
// ->widgets([CampaignStatsOverview::class, TerritorialDistributionChart::class, TopLeadersTable::class]) — unchanged
->authMiddleware([
    Authenticate::class,
    \App\Http\Middleware\EnsureUserHasRole::class.':'.UserRole::AREA_COORDINATOR->value,  // was: UserRole::COORDINATOR->value
]);
```
Register in `bootstrap/providers.php` (exact syntax, alphabetical-ish grouping already established):
```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\AreaCoordinatorPanelProvider::class,  // NEW — add here
    App\Providers\Filament\CoordinatorPanelProvider::class,
    App\Providers\Filament\LeaderPanelProvider::class,
    App\Providers\Filament\ReportsPanelProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\VoltServiceProvider::class,
];
```

### Pattern 3: `User::canAccessPanel()` — REQUIRED, not in CONTEXT.md, real gap
**What:** `app/Models/User.php`'s `canAccessPanel()` match expression has one arm per existing panel id (`admin`, `leader`, `coordinator`, `reports`) and a `default => false`. Without an `'area_coordinator'` arm, an articulador who successfully passes the panel's own `authMiddleware` role gate will still be denied by Filament's separate `FilamentUser::canAccessPanel()` contract check (Filament calls both). **This must be added or the panel is unreachable regardless of D-05's correct middleware.**
```php
// Source: app/Models/User.php canAccessPanel() — add this arm
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'admin' => $this->hasAnyRole(['super_admin', 'admin_campaign', 'reviewer']),
        'leader' => $this->hasAnyRole(['leader', 'admin_campaign', 'super_admin']),
        'coordinator' => $this->hasAnyRole(['coordinator', 'admin_campaign', 'super_admin']),
        'area_coordinator' => $this->hasAnyRole(['area_coordinator', 'admin_campaign', 'super_admin']), // NEW
        'reports' => $this->hasRole('reports_viewer'),
        default => false,
    };
}
```

### Pattern 4: Ownership scoping on the list page (D-08 discretion)
**What:** Filter by the owning FK directly, exactly like `coordinator/leaders.blade.php`'s `coordinator_user_id = $user->id` branch.
**Source:** `resources/views/livewire/coordinator/leaders.blade.php` `with()` method
```php
// Adapted from coordinator/leaders.blade.php's with() method
$query = User::role(UserRole::COORDINATOR->value)->withCount(['leaders as leaders_count']); // adapt aggregate per discretion
if ($user->hasRole(UserRole::AREA_COORDINATOR->value)) {
    $query->where('area_coordinator_user_id', $user->id);
} else {
    // admin_campaign/super_admin pass-through, same as today's "else" branch for coordinador list
}
```

### Anti-Patterns to Avoid
- **Copying the inline-namespace Blade references verbatim** (`\App\Enums\UserRole::COORDINATOR->value` appears directly in the `.blade.php` markup of every reference file). This violates this project's explicit CLAUDE.md rule ("NEVER namespace aliases, full inline paths, or inline namespace references"). New Blade markup should reference `UserRole::AREA_COORDINATOR->value` via the `use App\Enums\UserRole;` already imported at the top of the Volt `<?php` class script (Blade markup in a single-file Volt component shares the same PHP `use` imports as the class script above it — confirmed working precedent: `create-leader.blade.php`'s top-level `use App\Enums\UserRole;` is referenced unqualified as `UserRole::COORDINATOR->value` inside `mount()`, `getCoordinatorsProperty()`, etc., proving the import is visible; the Blade-markup section's `\App\Enums\UserRole::...` fully-qualified calls are the *inconsistent* outlier already present in the codebase, not something to imitate).
- **Adding a `Volt::route('dashboard', ...)` under `prefix('articulador')`** — resolved above (Pattern 1): don't add one, D-02's route enumeration is already complete without it.
- **Forgetting `canAccessPanel()`** — the middleware-only mirror of D-05 is necessary but not sufficient; Filament checks both the auth middleware AND `FilamentUser::canAccessPanel()`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Document-number → name autofill | A new lookup service | `App\Services\IdentityLookupService::findByDocumentNumber()` | Already shared across `CoordinatorForm`, `AreaCoordinatorForm`, `create-leader.blade.php` — single source of truth (D-04 explicitly requires reuse) |
| "My team" role-scoped queries | New scope logic on `User` | `User::teamCoordinatorUserIds()` (already exists, Phase 13/AUTHZ-01) | Already proven correct for `TopLeadersTable`/`LeadersExportController` — but note this resolves **coordinador** IDs for an articulador's leaders visibility, it does NOT scope "my own coordinadores" (that's the new `area_coordinator_user_id = auth()->id()` filter, a different, simpler relation: `User::coordinators()` HasMany already exists on `User` model, `area_coordinator_user_id` FK) |
| Coordinador→articulador ownership authorization | A new Policy | `App\Policies\CoordinatorPolicy` (Phase 13, registered on `User::class` in `AuthServiceProvider`) | Already implements exactly this ownership rule (`view`/`update`), but is NOT auto-invoked outside Filament Resources — decide explicitly whether to call it (see Open Questions) |
| Campaign attach on create | Manual campaign resolution | `$coordinatorUser... campaigns()->pluck('campaigns.id')` then `->attach()`, exactly as `create-leader.blade.php`'s `save()` does for the new leader, adapted to attach the *creating articulador's own* campaigns to the new coordinador | Established pattern for every self-service create flow in the codebase |

**Key insight:** This phase has essentially zero net-new logic surface area. Every "don't hand-roll" item already has a canonical, working implementation elsewhere in this exact codebase — the risk is not missing functionality, it's introducing *inconsistency* (e.g., a subtly different ownership check, a forgotten `canAccessPanel()` arm, or an unnecessary duplicate dashboard) by not mirroring closely enough, or by over-mirroring beyond D-02's literal route enumeration.

## Common Pitfalls

### Pitfall 1: `canAccessPanel()` silently blocks the new panel even with correct middleware
**What goes wrong:** `AreaCoordinatorPanelProvider`'s `authMiddleware` correctly restricts to `UserRole::AREA_COORDINATOR`, but Filament separately calls `$user->canAccessPanel($panel)` (the `FilamentUser` contract) on every panel request. `User::canAccessPanel()` has no `'area_coordinator'` arm, so it falls to `default => false` and denies access even for a correctly-role-assigned articulador.
**Why it happens:** Filament v4 requires both layers (route middleware AND `FilamentUser::canAccessPanel()`) — this is easy to miss when only mirroring `CoordinatorPanelProvider.php` itself, since `canAccessPanel()` lives in `User.php`, a file the mirror doesn't touch by default.
**How to avoid:** Explicitly add the `'area_coordinator' => $this->hasAnyRole(['area_coordinator', 'admin_campaign', 'super_admin'])` arm as part of this phase's task list — it is a REQUIRED file, even though CONTEXT.md's canonical_refs section doesn't list `User.php`.
**Warning signs:** Articulador logs in successfully, navigates to `/articulador`, and gets a 403 (or redirected) despite having the correct role and passing route middleware.

### Pitfall 2: Over-mirroring the `/coordinator` group's `dashboard` route onto `/articulador` (resolved — do not do this)
**What goes wrong:** `coordinator/dashboard.blade.php` is a real, independent Volt page (own leader/apoyo stats query) at `/coordinator/dashboard`, separate from the Filament `CoordinatorPanelProvider`'s own `Dashboard::class` page. A naive "mirror everything" pass over the `/coordinator` group would add an equivalent `Volt::route('dashboard', 'articulador.dashboard')` (plus a matching Blade view) to the `/articulador` group.
**Why it happens:** D-02's canonical_refs section names the `/coordinator` route group as the structural template "exactly," which superficially suggests copying every route in it, including `dashboard`.
**How to avoid:** D-02's own locked route enumeration only lists three routes (`coordinadores`, `coordinadores/create`, `coordinadores/{coordinator}/edit}`) — no `dashboard`. D-06 confirms this is intentional: the Filament panel (D-05) is the sole dashboard surface for articulador; there is no fourth Volt view to build. Verified no actual URI collision would occur either way (Filament panel root vs. a `prefix('articulador')/dashboard` plain route are different URIs), but the correct action is still to NOT add it, per the literal locked decision.
**Warning signs:** A plan or task list that includes a `dashboard.blade.php` file under `resources/views/livewire/articulador/` — this is scope creep beyond D-02, not required by ARTIC-02's 3 success criteria either.

### Pitfall 3: `RedirectBasedOnRole` middleware has no `AREA_COORDINATOR` case
**What goes wrong:** The generic authenticated `/dashboard` route (`Route::view('dashboard', 'dashboard')->middleware(['auth', 'verified', 'redirect.role'])`) uses `App\Http\Middleware\RedirectBasedOnRole`, which has explicit `if ($user->hasRole(...))` branches for `SUPER_ADMIN`, `ADMIN_CAMPAIGN`, `COORDINATOR`, `LEADER` — but **no branch for `AREA_COORDINATOR`**. An articulador who lands on the generic `/dashboard` route (e.g., a stale bookmark, or any link that points there instead of directly to `/articulador`) will silently fall through to "no specific role, continue to general dashboard" instead of being redirected to their own panel.
**Why it happens:** This file is not in CONTEXT.md's canonical_refs list (the phase brief only names `routes/web.php`, the three Blade files, and `CoordinatorPanelProvider`), so it's easy to miss entirely.
**How to avoid:** This is NOT required by ARTIC-02's literal 3 success criteria (they only require reachability of the articulador's own panel, not auto-redirect-from-generic-dashboard), so it is discretionary — but flagging it here because omitting it creates a real UX gap parity issue with coordinador (who DOES get auto-redirected). Recommend adding `if ($request->user()->hasRole(UserRole::AREA_COORDINATOR->value)) { return redirect()->route('filament.area_coordinator.pages.dashboard'); }` (and the matching `isCorrectDashboard()` map entry) as an optional but low-cost polish task.
**Warning signs:** Articulador ends up on the generic fallback `dashboard.blade.php` view instead of their panel, human-verification catches "wrong landing page."

### Pitfall 4: Locked route path literally uses Spanish ("coordinadores") while the project's established URL-segment convention is English
**What goes wrong:** CONTEXT.md's D-02 locks in `Volt::route('coordinadores', ...)`, `coordinadores/create`, `coordinadores/{coordinator}/edit` — Spanish URL segments. Every existing analogous URL segment in this codebase is English (`leaders`, `leaders/create`, `coordinator`, `campaign-admin`), matching the project's "English backend, Spanish UI" convention (URL segments are arguably backend/routing, not UI text).
**Why it happens:** Not a code bug — this is a literal, explicit CONTEXT.md decision (user-reviewed), not researcher error. Flagging per this agent's "verify against current code and flag any drift" instruction.
**How to avoid:** Not a code fix — the planner should honor D-02 verbatim as written (it is a locked User Constraint), but may want to surface this specific drift to the user/orchestrator once, since it's the one place this phase's locked decisions diverge from the codebase's own established URL-naming convention. Not blocking.
**Warning signs:** N/A — purely a consistency note, not a functional risk.

## Code Examples

### `edit-leader.blade.php` — full reference structure for `articulador/edit-coordinator.blade.php`
Full file read this session (220 lines). Key structural elements to carry over, annotated with what changes for the coordinador/articulador version:

| Element in `edit-leader.blade.php` | Adaptation for `edit-coordinator.blade.php` |
|---|---|
| `public User $leader;` + `mount(User $leader)` with `abort(404)` if not `hasRole(LEADER)` | `public User $coordinator;` + `mount(User $coordinator)` with `abort(404)` if not `hasRole(COORDINATOR)` |
| `if ($user->hasRole(COORDINATOR) && $leader->coordinator_user_id !== $user->id) abort(403);` | `if ($user->hasRole(AREA_COORDINATOR) && $coordinator->area_coordinator_user_id !== $user->id) abort(403);` — mirrors the exact manual-ownership-check style (see Open Questions re: whether to also call `CoordinatorPolicy`) |
| `#[Validate('required\|exists:users,id')] public int $coordinator_user_id;` (visible Select in the Blade, admin-only conditional) | **Do NOT include** — per D-08, `area_coordinator_user_id` must not appear at all, not even admin-conditionally. This is the one structural field to drop entirely, not just hide. |
| `getCoordinatorsProperty()` / `getCoordinatorProperty()` / `getNeighborhoodsProperty()` computed properties | Rename to direct fields since coordinador's municipality is its own column (`municipality_id`), not derived from a parent — coordinador form fields per CONTEXT.md discretion: name, email, document_number, birth_date, phone, secondary_phone, address, municipality_id, neighborhood_id, password |
| `save()`: re-derives `coordinator_user_id` from `auth()->id()` if actor is a coordinador, validates, updates | `save()`: since `area_coordinator_user_id` is immutable from this surface (D-08), no re-derivation needed on update — just validate + `$this->coordinator->update([...])` with the coordinador's own fields, no FK reassignment logic at all |
| Password field: `nullable\|string\|min:8`, only updates if filled | Same pattern, reuse verbatim |
| Blade template: two-section layout (`Información Personal`, `Ubicación`), `flux:heading`/`flux:input`/`flux:select` | Reuse verbatim, add `Contacto` section (phone/secondary_phone/address) and `birth_date` DatePicker-equivalent (Flux doesn't have a native date component used elsewhere in Volt — check `AreaCoordinatorForm`'s Filament `DatePicker` for field expectations; Volt/Blade forms in this codebase don't currently have a birth_date input anywhere, so this will need a plain `<input type="date">` or `flux:input type="date"` — **no existing Volt precedent for a date field**, flag as a genuinely new UI element, not a mirror) |

### Route registration (verbatim addition point)
```php
// Source: routes/web.php, insert after the existing "// Coordinator routes" block (~line 96)
// Articulador routes
Route::middleware(['auth', 'role:area_coordinator,admin_campaign,super_admin'])->prefix('articulador')->name('articulador.')->group(function () {
    Volt::route('coordinadores', 'articulador.coordinators')->name('coordinadores');
    Volt::route('coordinadores/create', 'articulador.create-coordinator')->name('coordinadores.create');
    Volt::route('coordinadores/{coordinator}/edit', 'articulador.edit-coordinator')->name('coordinadores.edit');
});
```

### Test pattern (verbatim template)
```php
// Source: tests/Feature/Coordinator/CreateLeaderIdentityLookupTest.php — direct template for
// tests/Feature/Articulador/CreateCoordinatorIdentityLookupTest.php (or wherever the plan places it)
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->municipality = Municipality::factory()->create();
    $this->areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->areaCoordinator->campaigns()->attach($this->campaign->id);

    actingAs($this->areaCoordinator);
});

test('an articulador only sees their own coordinadores', function () {
    $mine = User::factory()->create(['area_coordinator_user_id' => $this->areaCoordinator->id]);
    $mine->assignRole(UserRole::COORDINATOR->value);

    $notMine = User::factory()->create();
    $notMine->assignRole(UserRole::COORDINATOR->value);

    Volt::test('articulador.coordinators')
        ->assertSee($mine->name)
        ->assertDontSee($notMine->name);
});
```

## Open Questions

1. **Should `edit-coordinator.blade.php`/`coordinators.blade.php` explicitly invoke `CoordinatorPolicy` (via `$this->authorize('update', $coordinator)` / `Gate::authorize()`), or rely purely on manual `abort_unless` scoping the same way `edit-leader.blade.php` does today?**
   - What we know: `CoordinatorPolicy::view()`/`update()` already implements exactly the right ownership rule and is registered globally on `User::class`. It is auto-enforced on Filament Resource pages (`EditAreaCoordinator`, `EditCoordinator`) via Filament's built-in `Gate::allows()` call, but Volt routes get zero automatic enforcement — `edit-leader.blade.php` proves this: it does NOT call the Policy at all, using a hand-written `abort(403)` comparing FK columns directly instead.
   - What's unclear: CONTEXT.md's discretion note says the Policy "already governs direct-record view/update authorization as a second layer" — this phrasing implies the Policy is *already* protecting this new route without new code, which is only true if the plan adds an explicit `$this->authorize(...)` call somewhere (mount() is the natural spot). If the plan just copies `edit-leader.blade.php`'s manual-`abort` pattern (the literal, proven-working precedent), the Policy contributes nothing extra on this specific surface.
   - Recommendation: Add an explicit `abort_unless(auth()->user()->can('update', $coordinator), 403);` call in `mount()` (which invokes `CoordinatorPolicy` via the Gate) as the primary authorization check — this both matches CONTEXT.md's stated intent ("Policy governs as a second layer") AND is arguably cleaner than duplicating the FK-comparison logic inline a third time (leader edit + admin Filament Resource + this). Still add the `hasRole(COORDINATOR)`/404 guard for non-coordinador records, matching `edit-leader.blade.php`'s `abort(404)` pattern.

2. **Birth date field — no existing Volt/Blade UI precedent.**
   - What we know: `AreaCoordinatorForm`/`CoordinatorForm` (Filament) use `DatePicker::make('birth_date')`. No Volt/Blade self-service form in this codebase has ever rendered a date input.
   - What's unclear: whether Flux Free's `flux:input type="date"` renders acceptably, or whether a plain native `<input type="date">` with `wire:model` is expected. CONTEXT.md's discretion list does include `birth_date` in the adapted field set.
   - Recommendation: Use `flux:input label="Fecha de nacimiento" type="date"` (Flux `input` component supports arbitrary HTML5 input types per Flux docs convention already used for `type="tel"`/`type="email"` in this codebase) — low risk, verify visually during execution's browser-verification step (per project's standing "browser-verify before prod" preference).

## Sources

### Primary (HIGH confidence — direct source read, this session)
- `routes/web.php` — full coordinator/leader/campaign-admin route groups
- `bootstrap/providers.php` — panel provider registration list
- `app/Providers/Filament/CoordinatorPanelProvider.php` — full file
- `app/Providers/Filament/LeaderPanelProvider.php` — partial (id/path/middleware confirmation)
- `app/Enums/UserRole.php` — full enum, confirms `AREA_COORDINATOR = 'area_coordinator'`
- `app/Http/Middleware/EnsureUserHasRole.php` — full file, confirms `role:` middleware alias behavior
- `bootstrap/app.php` — confirms `'role' => EnsureUserHasRole::class` alias registration
- `app/Policies/CoordinatorPolicy.php` — full file
- `app/Providers/AuthServiceProvider.php` — full file, confirms `User::class => CoordinatorPolicy::class` + `Gate::before` campaign-isolation layer
- `resources/views/livewire/coordinator/edit-leader.blade.php` — full file (220 lines)
- `resources/views/livewire/coordinator/leaders.blade.php` — full file (306 lines)
- `resources/views/livewire/coordinator/create-leader.blade.php` — full file (429 lines)
- `resources/views/livewire/coordinator/dashboard.blade.php` — partial (top ~50 lines), confirms this is a real, distinct page separate from the Filament panel dashboard
- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` — full file
- `app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php` — full file
- `app/Http/Controllers/Coordinator/LeadersExportController.php` — full file
- `app/Models/User.php` — full file, confirms `canAccessPanel()` gap, `coordinators()`/`areaCoordinator()`/`teamCoordinatorUserIds()` relations
- `app/Http/Middleware/RedirectBasedOnRole.php` — full file, confirms missing `AREA_COORDINATOR` case
- `app/Services/IdentityLookupService.php` — full file
- `app/Filament/Resources/AreaCoordinators/Pages/EditAreaCoordinator.php` — full file
- `tests/Feature/Policies/CoordinatorPolicyTest.php` — full file, confirms Policy invocation pattern via `Gate::forUser()->inspect()`
- `tests/Feature/ArticuladorTeamResolutionTest.php` — partial, confirms factory/test-setup conventions for `area_coordinator`
- `tests/Feature/Coordinator/CreateLeaderIdentityLookupTest.php` — full file, confirms `Volt::test('coordinator.create-leader')` pattern
- `.planning/phases/15-articulador-self-service-panel/15-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/STATE.md` — upstream inputs
- `.planning/config.json` — confirms `workflow.nyquist_validation: false` (Validation Architecture section correctly omitted below)
- `composer.json` / `php artisan --version` — confirms PHP 8.4.23 (local), Laravel 12.36.1, Pest ^4.1 installed

### Secondary / Tertiary
None used — every claim in this document is grounded in a direct read of this repository's own source in this session (no WebSearch/Context7 needed; this is a pure internal-mirroring phase with zero external-library research surface).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies, everything verified installed via `composer.json`
- Architecture: HIGH — every pattern verified against the actual working reference files, not assumed
- Pitfalls: HIGH — `canAccessPanel()` gap, `RedirectBasedOnRole` gap, and the dashboard-route non-requirement all found/resolved by direct code inspection, not speculation
- Open Questions: two genuine judgment calls remain for the planner (policy invocation style, date-input choice), not gaps in research — flagged honestly rather than guessed

**Research date:** 2026-08-10
**Valid until:** Effectively indefinite for this phase (internal-mirror research, not dependent on external library versions) — re-verify only if Phase 14's files (`CoordinatorForm`, `AreaCoordinatorForm`, `CoordinatorPolicy`) change before Phase 15 executes.
