# Phase 13: Hierarchy Authorization & Call-Site Audit - Context

**Gathered:** 2026-08-10
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase is authorization/backend-only — no new UI screens (Phase 14/15 build the panels). It updates the existing self-scoped widgets/exports/controller that assume `coordinador` is the top of the hierarchy so they correctly resolve an articulador's full transitive team, and adds an explicit ownership policy that denies an articulador from viewing/editing a coordinador that isn't theirs. `area_coordinator`'s `canAccessPanel()` remains unwired (deferred to Phase 14/15, per Phase 12's CONTEXT.md) — this phase is verified via Pest/tinker, not a real logged-in articulador session in a browser.

</domain>

<decisions>
## Implementation Decisions

### Call-Site Audit Scope
- **D-01:** Codebase audit (via grep for `coordinator_user_id` combined with `Auth::id()`/`Auth::user()` self-scoping) confirmed only **3 files** actually narrow query results to "the logged-in coordinador's own team": `app/Filament/Widgets/TopLeadersTable.php`, `app/Exports/TopLeadersExport.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php`. These exactly match AUTHZ-01's named examples — there are no other hidden self-scoping call sites for this phase to find.
- **D-02:** `TopCoordinatorsTable`, `ApoyosLideresCoordinadoresTable`, and `TerritorialOwnershipTable` (and similar campaign-wide dashboards) are explicitly **out of scope** for this phase. They are campaign-wide rollups (visible to admin/coordinador roles, scoped by `CampaignMembershipScope` only, not by owner) — there's no "sees another team's data" bug to fix in them today. Whether/how an articulador's rows should appear or be labeled in these dashboards is deferred to Phase 14/15, when `canAccessPanel()` actually exposes articuladores to any panel — revisit then, not now.

### Ownership Policy Shape
- **D-03:** A new, dedicated `App\Policies\CoordinatorPolicy` is created — purely additive. It implements `view()`/`update()` comparing `$coordinator->area_coordinator_user_id === $user->id` for an articulador actor. It does **not** touch or formalize the existing (currently implicit, query-scattered) coordinador→líder ownership pattern — that stays exactly as it is today, out of scope.
- **D-04:** The policy protects **direct record actions only** (viewing/editing one specific coordinador — e.g. navigating straight to that coordinador's edit URL). List-level visibility (`CoordinatorsTable` and similar) stays a plain query filter (`whereHas`/`where` scoped to the articulador's own coordinadores) — a non-owned coordinador simply doesn't appear in the list; the Policy is not invoked for list filtering.

### Denial Behavior
- **D-05:** Direct access to a coordinador that doesn't belong to the articulador returns **403 with an explicit reason** (not a 404). This matches the already-validated Phase 05.1 precedent (PERM-02: "authorization denials name the specific reason instead of a generic 403") and is the literal ask in AUTHZ-02 ("a clear denial reason rather than a silent empty result").

### Claude's Discretion
- Exact `CoordinatorPolicy` method signatures/registration (Filament resource policy wiring, `AuthServiceProvider`/auto-discovery convention) — follow whatever pattern `VoterPolicy`/`InvitationPolicy` already use in this codebase.
- Exact query shape for "transitive team" resolution in the 3 audited files (e.g. `whereHas('coordinator', fn ($q) => $q->where('area_coordinator_user_id', $user->id))` vs. a reusable helper method on `User`) — planner's call, informed by the project's "thin controllers, logic in Actions/Services" convention (`CLAUDE.md`) and the existing `CampaignContext` service as precedent for a shared helper if 3 call sites duplicating the same query shape is judged worth extracting.
- Test structure/naming — follow existing Pest conventions (mirrors `CoordinatorLeaderRelationshipTest`, `OwnershipScopedWidgetsTest` shape).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (2026-08-10) — use with caution, partially superseded
- `.planning/research/ARCHITECTURE.md` — confirms the coordinador-scoping pattern in `TopLeadersExport`/`TopLeadersTable`/`LeadersExportController`; **note:** this file's line labeling these 3 files "Not in the milestone's explicit scope" is stale/superseded — it predates the finalized ROADMAP.md/REQUIREMENTS.md, which explicitly name these exact 3 files as AUTHZ-01's target. Trust ROADMAP.md/REQUIREMENTS.md over this research file where they conflict.
- `.planning/research/PITFALLS.md` — general pitfalls list from milestone research, still applicable for hierarchy-relation pitfalls (e.g. never let query logic imply nesting beyond one level).

### Project-level
- `.planning/REQUIREMENTS.md` — AUTHZ-01, AUTHZ-02, AUTHZ-03 (this phase's mapped requirements)
- `.planning/PROJECT.md` — Key Decisions table entry on Phase 05.1 PERM-02 ("authorization denials name the specific reason") — the precedent D-05 follows
- `.planning/phases/12-hierarchy-metadata-schema-foundation/12-CONTEXT.md` — prior-phase decisions this phase builds on (`area_coordinator_user_id` FK shape, `User::areaCoordinator()`/`coordinators()` relations, `canAccessPanel()` deferral)
- `.planning/phases/12-hierarchy-metadata-schema-foundation/12-VERIFICATION.md` — confirms the schema/relations this phase's queries and policy will read from are in place and tested

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Models/User.php` — `areaCoordinator()` (`belongsTo`) / `coordinators()` (`hasMany`) relations from Phase 12, ready to traverse for "my coordinadores" and their leaders
- `app/Policies/VoterPolicy.php` — existing Policy shape/method-signature convention to mirror for `CoordinatorPolicy` (note: `VoterPolicy` is role-only, no per-record ownership comparison — `CoordinatorPolicy` will be the first ownership-aware Policy in this codebase)
- `app/Filament/Widgets/TopLeadersTable.php`, `app/Exports/TopLeadersExport.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php` — the 3 exact files to modify; each currently has `->when($user?->hasRole(UserRole::COORDINATOR->value), fn ($query) => $query->where('coordinator_user_id', $user->id))` or equivalent, needs an added branch/OR-condition for `UserRole::AREA_COORDINATOR`

### Established Patterns
- `app/Models/Scopes/CampaignMembershipScope.php` — confirmed orthogonal to hierarchy/ownership concerns (isolates by `campaign_user` pivot, not by `coordinator_user_id`/`area_coordinator_user_id`) — AUTHZ-03's campaign-isolation requirement should already hold automatically once the new role/relations exist, but needs an explicit regression test to prove it (mirroring existing `CampaignMembershipScope` test patterns)
- Existing coordinador ownership checks are all inline `->when($user->hasRole(...), fn ($q) => $q->where('coordinator_user_id', $user->id))` closures duplicated per call site — no shared helper exists yet; this phase adds the articulador branch to each of the 3 sites using the same inline style unless the planner decides extraction is warranted (Claude's Discretion above)

### Integration Points
- `app/Filament/Widgets/TopLeadersTable.php` — extend the `->when()` chain to also resolve an articulador's transitive team
- `app/Exports/TopLeadersExport.php` — same extension, query-builder context
- `app/Http/Controllers/Coordinator/LeadersExportController.php` — same extension, controller context
- New `app/Policies/CoordinatorPolicy.php` — `view()`/`update()` methods, registered via Filament resource policy or `AuthServiceProvider` (follow existing `VoterPolicy` registration convention)
- New/extended Pest test(s) — regression coverage for AUTHZ-01 (transitive team resolution), AUTHZ-02 (403 with reason on non-owned coordinador), AUTHZ-03 (cross-campaign isolation for the new role)

</code_context>

<specifics>
## Specific Ideas

No visual/UI specifics — this is a backend authorization phase. The concrete specifics are the 3 locked scope/shape decisions above: exactly which 3 files get touched, the dedicated (non-generalizing) `CoordinatorPolicy`, and 403-with-reason for direct non-owned-record access.

</specifics>

<deferred>
## Deferred Ideas

- Making `TopCoordinatorsTable`, `ApoyosLideresCoordinadoresTable`, `TerritorialOwnershipTable`, and similar campaign-wide dashboards correctly display/label articulador rows — deferred to Phase 14/15, when `canAccessPanel()` actually exposes articuladores to any panel and these widgets become reachable/relevant to that role.
- Formalizing the existing implicit coordinador→líder ownership pattern into a Policy — explicitly rejected as in-scope for this phase (D-03); stays as-is unless a future phase decides otherwise.

### Reviewed Todos (not folded)
None — no pending todos matched Phase 13 (`todo match-phase 13` returned 0 matches).

</deferred>

---

*Phase: 13-hierarchy-authorization-call-site-audit*
*Context gathered: 2026-08-10*
