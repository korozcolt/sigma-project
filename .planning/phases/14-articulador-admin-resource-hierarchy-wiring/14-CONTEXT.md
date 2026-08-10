# Phase 14: Articulador Admin Resource & Hierarchy Wiring - Context

**Gathered:** 2026-08-10
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase adds a new admin-panel Filament resource (`AreaCoordinatorResource`) so a superadmin/admin_campaign can create and manage articulador (`area_coordinator`) users — same panel already accessible to those roles, no new panel or `canAccessPanel()` wiring for `area_coordinator` itself (that's Phase 15's self-service panel). It also adds the hierarchy-wiring half: a Select field on the existing `CoordinatorForm` letting an admin assign/reassign which articulador a coordinador belongs to. Existing coordinador behavior (own panel, dashboards, exports) must be unaffected whether or not an articulador is assigned — Phase 13 already made the 3 audited call sites and the ownership policy correct for this; this phase is only about giving admins the UI to create articuladores and wire the relationship, not about re-touching authorization.

</domain>

<decisions>
## Implementation Decisions

### AreaCoordinatorResource Form
- **D-01:** `AreaCoordinatorResource`'s form mirrors `CoordinatorForm` exactly (Información personal, Contacto, Ubicación, Acceso sections) **minus** the "también será líder" toggle — an articulador is never also a líder, so that field is dropped entirely, not just hidden.

### Coordinador → Articulador Assignment UX
- **D-02:** Assignment happens via a single `Select` field added to `CoordinatorForm` (not a dedicated reassignment Action, not a bulk-action) — same UX pattern as the existing `municipality_id` Select. One place to set/change it: the coordinador's own create/edit form.
- **D-03:** The selector is **optional/nullable** — a coordinador can be saved with no articulador assigned. This directly matches ARTIC-03 ("a coordinador with no articulador assigned continues to function identically to today") and the `area_coordinator_user_id` FK is already nullable from Phase 12.

### Campaign Scoping of the Selector
- **D-04:** The articulador dropdown on `CoordinatorForm` is filtered to the active/current campaign, mirroring the exact pattern already used by the `municipality_id` Select's `relationship()` closure (`CampaignContext::currentCampaign()`-based filtering). An admin cannot pick a Campaign-B articulador while editing a Campaign-A coordinador — consistent with the project's strict campaign-isolation constraint and Phase 13's AUTHZ-03 precedent.

### AreaCoordinatorResource List Table
- **D-05:** `AreaCoordinatorsTable` (mirroring `CoordinatorsTable`'s column set) adds one extra column: a count of assigned coordinadores per articulador (`withCount('coordinators')` or equivalent), giving immediate organizational visibility from the list view without opening each record.

### Claude's Discretion
- Exact resource/page/schema/table class names and directory layout for `AreaCoordinatorResource` — mirror `app/Filament/Resources/Coordinators/*`'s exact structure (`Pages/CreateAreaCoordinator.php`, `Schemas/AreaCoordinatorForm.php`, `Tables/AreaCoordinatorsTable.php`, etc.) unless codebase conventions suggest otherwise.
- Role/campaign-attachment logic for `CreateAreaCoordinator::afterCreate()` — follow `CreateCoordinator::afterCreate()`'s exact pattern (`assignRole()`, `attachActiveCampaign()` via `campaigns()->syncWithoutDetaching()`), adapted for `UserRole::AREA_COORDINATOR`.
- Whether the `CoordinatorForm` articulador Select uses `relationship()` (Filament-native) or a plain `Select::make('area_coordinator_user_id')->options(...)` — planner's call based on which composes better with the existing `municipality_id` closure-filtering pattern.
- Navigation label, icon, sort order for `AreaCoordinatorResource` in the admin panel's "Gestión" nav group — follow `CoordinatorResource`'s conventions (Spanish labels, `Heroicon::OutlinedUsers` family).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project-level
- `.planning/REQUIREMENTS.md` — ARTIC-01, ARTIC-03 (this phase's mapped requirements)
- `.planning/PROJECT.md` — Key Decisions table (`area_coordinator_user_id` dedicated FK rationale), Current Milestone section
- `.planning/phases/12-hierarchy-metadata-schema-foundation/12-CONTEXT.md` — schema this phase builds on (`area_coordinator_user_id` FK, `User::areaCoordinator()`/`coordinators()` relations, `AREA_COORDINATOR` enum case, `canAccessPanel()` deferral confirmed to extend through this phase too)
- `.planning/phases/13-hierarchy-authorization-call-site-audit/13-CONTEXT.md` and `13-VERIFICATION.md` — confirms the 3 audited call sites and `CoordinatorPolicy` are already correct for articulador visibility; this phase does not need to re-touch authorization, only add the admin UI

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Filament/Resources/Coordinators/CoordinatorResource.php` — exact resource shape to mirror (`getEloquentQuery()` scoped via `->role('coordinator')`, becomes `->role('area_coordinator')` for the new resource)
- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` — form to mirror (Información personal, Contacto, Ubicación with `municipality_id`/`neighborhood_id` Selects using `CampaignContext::currentCampaign()`-based filtering, Acceso with password + the toggle to drop)
- `app/Filament/Resources/Coordinators/Pages/CreateCoordinator.php` — `afterCreate()` pattern to mirror: `assignRole()`, `attachActiveCampaign()` via `campaigns()->syncWithoutDetaching()` with `role_id`/`assigned_at`/`assigned_by` pivot data
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` — table to mirror, extended with a coordinadores-count column
- `app/Models/User.php` — `areaCoordinator()` (`belongsTo`)/`coordinators()` (`hasMany`) relations already exist (Phase 12); `AREA_COORDINATOR` case already in `app/Enums/UserRole.php` with Spanish label "Articulador"

### Established Patterns
- `CoordinatorForm`'s `municipality_id` Select is the exact precedent for campaign-scoped relationship Selects: `->relationship('municipality', 'name', function (Builder $query) { ... CampaignContext::currentCampaign() ... })`, `->searchable()->preload()`. The new articulador Select should follow this same shape.
- `app/Models/User.php` already has `canAccessPanel()` at line ~247 — confirmed `area_coordinator` is NOT added to any panel's `match` arm yet, and per Phase 12/13 CONTEXT.md this stays deferred to Phase 15 (self-service panel). Phase 14's `AreaCoordinatorResource` lives in the `admin` panel, already accessible to `super_admin`/`admin_campaign`/`reviewer` — no `canAccessPanel()` change needed in this phase.

### Integration Points
- New `app/Filament/Resources/AreaCoordinators/` directory (Resource, Pages/Create+Edit+List, Schemas/Form, Tables/Table) — mirrors `Coordinators/` structure
- `app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php` — add the new articulador `Select` field
- `app/Filament/Resources/Coordinators/Pages/CreateCoordinator.php` / `EditCoordinator.php` — no changes expected if the Select is a plain model attribute (`area_coordinator_user_id`) rather than a pivot relation
- New/extended Pest tests — regression coverage for ARTIC-01 (create articulador, assign coordinador via selector) and ARTIC-03 (coordinador behavior identical with/without articulador assigned)

</code_context>

<specifics>
## Specific Ideas

No additional visual specifics beyond the 5 locked decisions above — the concrete shape is: a `CoordinatorResource`-mirrored admin resource for articuladores (minus "también será líder"), plus one new campaign-scoped, optional Select field on `CoordinatorForm`, plus a coordinadores-count column on the new resource's list table.

</specifics>

<deferred>
## Deferred Ideas

- `area_coordinator`'s own self-service panel access (`canAccessPanel()` wiring) — explicitly Phase 15's scope, not touched here.
- Bulk-reassignment action (reassign multiple coordinadores to one articulador at once) — considered during discussion, explicitly not chosen; single-record Select on `CoordinatorForm` is sufficient for this phase. Could be revisited later if operational need arises.
- Making `TopCoordinatorsTable`/`ApoyosLideresCoordinadoresTable`/`TerritorialOwnershipTable` display/label articulador rows — still deferred from Phase 13's D-02, remains deferred until Phase 15 makes the role's panel access real.

### Reviewed Todos (not folded)
None — no pending todos matched Phase 14 (`todo match-phase 14` returned 0 matches).

</deferred>

---

*Phase: 14-articulador-admin-resource-hierarchy-wiring*
*Context gathered: 2026-08-10*
