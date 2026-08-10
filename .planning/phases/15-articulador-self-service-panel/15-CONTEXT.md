# Phase 15: Articulador Self-Service Panel - Context

**Gathered:** 2026-08-10
**Status:** Ready for planning

<domain>
## Phase Boundary

An articulador manages their own coordinadores from a dedicated self-service panel, mirroring the coordinador's existing self-service experience for managing leaders. Scope is ARTIC-02 only: viewing own coordinadores, creating a new coordinador (auto-linked via `area_coordinator_user_id`), and editing/managing existing ones — all without needing admin-panel access.

</domain>

<decisions>
## Implementation Decisions

### CRUD architecture
- **D-01:** The coordinador-management CRUD (list/create/edit) is built as **Volt pages**, not a Filament Resource — mirroring the real existing pattern (`resources/views/livewire/coordinator/{leaders,create-leader,edit-leader}.blade.php`), not the admin-style `AreaCoordinatorResource` from Phase 14. These are non-Filament routes rendered with the default `components.layouts::app` layout.
- **D-02:** New Volt views live under `resources/views/livewire/articulador/` (`coordinators.blade.php`, `create-coordinator.blade.php`, `edit-coordinator.blade.php`), routed under a new `/articulador` prefix group in `routes/web.php` (`Route::middleware(['auth','role:area_coordinator,...'])->prefix('articulador')->name('articulador.')`), mirroring the `/coordinator` prefix group structure exactly (`Volt::route('coordinadores', ...)`, `coordinadores/create`, `coordinadores/{coordinator}/edit`).
- **D-03:** `area_coordinator_user_id` is auto-set to the authenticated articulador on create (mirroring `create-leader.blade.php`'s `coordinator_user_id = auth()->id()` behavior when the actor is a coordinador) — not a user-facing field in the self-service create form.

### OTP verification
- **D-04:** Creating a coordinador from the articulador self-service panel does **not** require phone OTP verification. This diverges from the leader-creation flow (`create-leader.blade.php`, which requires `OtpVerificationService` send+verify before save) and instead mirrors the admin-side `AreaCoordinatorForm`/`CoordinatorForm` pattern (no OTP step). Existing `IdentityLookupService` document-number autofill (name lock/unlock) should still be reused for UX consistency, since that's independent of the OTP question.

### Panel scope
- **D-05:** A new `AreaCoordinatorPanelProvider` (Filament panel, `id('area_coordinator')`, `path('articulador')`) is created mirroring `CoordinatorPanelProvider` exactly: `Dashboard::class` + `DiaD::class` pages, and `CampaignStatsOverview`, `TerritorialDistributionChart`, `TopLeadersTable` widgets (these widgets already resolve an articulador's transitive team correctly per Phase 13/AUTHZ-01). Auth middleware restricts to `UserRole::AREA_COORDINATOR`.
- **D-06:** The Filament panel (Dashboard/Día D/widgets) and the Volt CRUD pages (D-01/D-02) are two separate, parallel surfaces sharing the `/articulador` URL prefix by convention — exactly how `/coordinator` works today (Filament panel for dashboard/DiaD, plain Volt routes for leader CRUD, no structural link between them).

### Invitation link
- **D-07:** No shareable self-registration invitation link for coordinadores (no equivalent of `generateLeaderInvitationLink` / public `register-leader` view). Only direct in-form creation, per ROADMAP.md's literal success criterion 2 wording ("creates a new coordinador via a form on their panel"). Deferred as a future idea if operational need arises (see Deferred Ideas).

### Field scope / reassignment lock
- **D-08:** The self-service edit-coordinator form does **not** expose the `area_coordinator_user_id` field at all (hidden/locked) — a coordinador stays permanently tied to the articulador who owns them from the self-service surface. Reassigning a coordinador to a different articulador (or removing the assignment) remains admin-only, via the existing `CoordinatorForm` Select in the admin panel (Phase 14). This prevents articuladores from reassigning coordinadores away from or into their own team.

### Claude's Discretion
- Exact form field set/layout for `articulador/create-coordinator` and `articulador/edit-coordinator` Volt views: mirror `create-leader.blade.php`/`edit-leader.blade.php` structure and styling (Flux components, stats summary, empty states, search) adapted to coordinador fields (name, email, document_number, birth_date, phone, secondary_phone, address, municipality_id, neighborhood_id, password/access) minus `area_coordinator_user_id` (per D-08) and minus OTP (per D-04).
- Whether to reuse `IdentityLookupService`-driven name-lock UX and municipality/neighborhood cascading selects exactly as the existing coordinador/leader forms do — yes, reuse as-is (established, working pattern).
- Query scoping for "my own coordinadores" on the list page: use `area_coordinator_user_id = auth()->id()` directly (mirrors `coordinator_user_id = $user->id` in `coordinator/leaders.blade.php`); the existing `CoordinatorPolicy` (Phase 13) already governs direct-record view/update authorization as a second layer.
- Whether the new Volt views need pagination/search matching `coordinator/leaders.blade.php` — yes, mirror exactly (search by name/email, paginated list).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

No ROADMAP.md/REQUIREMENTS.md/PROJECT.md canonical refs are listed for this phase (project does not use an explicit "Canonical refs:" convention in ROADMAP.md). The following existing code is the de facto reference — the entire phase is defined as a mirror of these files.

### Pattern to mirror (coordinador self-service — the template)
- `routes/web.php` (lines ~86-93) — the `/coordinator` prefix route group structure to replicate for `/articulador`
- `resources/views/livewire/coordinator/leaders.blade.php` — list page pattern (search, stats, empty states, self-promote block to exclude)
- `resources/views/livewire/coordinator/create-leader.blade.php` — create page pattern (minus OTP per D-04, minus IdentityLookup census-warning logic which is voter-specific)
- `resources/views/livewire/coordinator/edit-leader.blade.php` — edit page pattern
- `app/Providers/Filament/CoordinatorPanelProvider.php` — Filament panel to mirror for `AreaCoordinatorPanelProvider`

### Phase 14 artifacts (admin-side, reference only — NOT to be reused directly as the self-service surface)
- `app/Filament/Resources/AreaCoordinators/AreaCoordinatorResource.php`
- `app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php` — field set reference for the self-service create/edit forms
- `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php`
- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` — shows the `area_coordinator_user_id` Select (D-08: must NOT appear in the self-service form)

### Authorization (Phase 13)
- `CoordinatorPolicy` (view/update ownership check for `area_coordinator_user_id`) — should govern direct-record access on the new edit route
- `AUTHZ-01`-updated widgets (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`) already resolve an articulador's transitive team via `User::teamCoordinatorUserIds()`

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Services\IdentityLookupService` — document-number-based name autofill, already used identically in both `CoordinatorForm` and `create-leader.blade.php`
- `App\Services\CampaignContext` — active-campaign resolution, used for municipality/neighborhood scoping in every existing form
- `CoordinatorPolicy` (Phase 13) — ownership-aware view/update authorization, ready to reuse for direct coordinador-record access from the self-service edit route
- `User::teamCoordinatorUserIds()` (Phase 13) — transitive team resolution already proven correct for articulador-owned widgets

### Established Patterns
- Self-service CRUD in this codebase is Volt (`livewire/volt` class-based components with `layout('components.layouts::app', [...])`), NOT Filament Resources — confirmed by inspecting the actual coordinador experience, which contradicts a literal reading of ROADMAP.md's "AreaCoordinatorPanelProvider (mirroring CoordinatorPanelProvider)" phrase (that phrase only covers the Dashboard/DiaD Filament panel, not the CRUD).
- Scoping "my own X" queries by directly filtering the owning FK column (`coordinator_user_id = auth()->id()`) is the established self-service pattern, layered with a Policy for direct-record access.
- New user creation via self-service auto-attaches to the creator's own campaigns (`$leader->campaigns()->attach($coordinatorUser->campaigns()->pluck('campaigns.id'))`) — the equivalent for coordinador creation should attach to the articulador's own campaigns.

### Integration Points
- New route group in `routes/web.php` alongside the existing `/coordinator` group
- New `AreaCoordinatorPanelProvider` registered in `bootstrap/providers.php` (or wherever other panel providers are registered) alongside `CoordinatorPanelProvider`/`LeaderPanelProvider`
- New Volt view directory `resources/views/livewire/articulador/`

</code_context>

<specifics>
## Specific Ideas

No additional specific UI references beyond "mirror the coordinador self-service experience exactly" (user's own words, echoed in ARTIC-02's requirement text: "mirroring el panel de auto-gestión que ya tiene coordinador").

</specifics>

<deferred>
## Deferred Ideas

- Shareable self-registration invitation link for coordinadores (mirroring `generateLeaderInvitationLink` + public `register-leader` flow) — explicitly deferred per D-07, not in ROADMAP.md's literal success criteria for this phase. Revisit only if a future milestone/phase calls for it.
- Self-service reassignment of a coordinador's articulador (moving a coordinador between articuladores, or detaching one) — explicitly kept admin-only per D-08.

### Reviewed Todos (not folded)
None — `todo match-phase 15` returned zero matches.

</deferred>

---

*Phase: 15-articulador-self-service-panel*
*Context gathered: 2026-08-10*
