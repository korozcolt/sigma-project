# Phase 13: Hierarchy Authorization & Call-Site Audit - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-10
**Phase:** 13-hierarchy-authorization-call-site-audit
**Areas discussed:** Call-site audit scope, Ownership policy shape, Denial granularity

---

## Call-Site Audit Scope

**Codebase findings presented before the question:** grep for `coordinator_user_id` combined with `Auth::id()`/`Auth::user()` self-scoping across `app/` returned exactly 2 files (`TopLeadersExport.php`, `TopLeadersTable.php`); `LeadersExportController.php` uses the equivalent `$user->id` pattern (3rd match, same semantics). No other file in the codebase does this self-scoping.

| Option | Description | Selected |
|--------|-------------|----------|
| Estricto — solo los 3 nombrados | TopLeadersTable, TopLeadersExport, LeadersExportController — the only 3 that self-scope today | |
| Blindaje proactivo | Also proactively audit TopCoordinatorsTable, TerritorialOwnershipTable, ApoyosLideresCoordinadoresTable for future articulador-readiness | ✓ (initial pick) |
| Otro alcance | Free text | |

**User's initial choice:** Blindaje proactivo.

**Follow-up:** Which specific widgets to include in the proactive shielding?

| Option | Selected |
|--------|----------|
| TopCoordinatorsTable / Export | ✓ |
| ApoyosLideresCoordinadoresTable | ✓ |
| TerritorialOwnershipTable / dashboards territoriales | ✓ |
| Ninguno — solo los 3 confirmados | |

**Scope-check follow-up (Claude flagged a nuance):** These 3 widgets are campaign-wide rollups (visible to admin/coordinador roles, scoped only by `CampaignMembershipScope`, not by owner) — there's no "sees another team's data" bug in them today, unlike the original 3. The real work there would be label/branch correctness for articulador rows whenever those widgets eventually become reachable by that role (Phase 14/15, once `canAccessPanel()` is wired). Asked whether to keep this in Phase 13 or move to Phase 14/15.

| Option | Description | Selected |
|--------|-------------|----------|
| Mover a Phase 14/15 | Phase 13 stays strictly on the 3 self-scoping files + the ownership policy; revisit the 3 campaign-wide dashboards once articuladores can actually reach them | ✓ |
| Mantener en Fase 13 | Audit/adjust the 3 dashboards' role-branching now, even though no one with the role can view them yet | |

**Final decision (D-01, D-02):** Strict scope — TopLeadersTable, TopLeadersExport, LeadersExportController only. TopCoordinatorsTable/ApoyosLideresCoordinadoresTable/TerritorialOwnershipTable explicitly deferred to Phase 14/15.
**Notes:** The initial "proactive" pick was walked back after Claude surfaced that the 3 additional widgets don't have the same self-scoping bug class — avoids speculative work on UI that isn't reachable yet.

---

## Ownership Policy Shape

| Option | Description | Selected |
|--------|-------------|----------|
| CoordinatorPolicy dedicado, solo articulador→coordinador | New `App\Policies\CoordinatorPolicy`, `view()`/`update()` comparing `area_coordinator_user_id === Auth::id()`. Purely additive — does not touch the existing implicit coordinador→líder ownership pattern. | ✓ |
| CoordinatorPolicy que también formaliza coordinador→líder | Same new Policy, but also codifies the currently-implicit coordinador→líder ownership as an explicit Policy rule | |
| Otro enfoque | Free text (e.g. a Gate instead of a Policy) | |

**User's choice:** CoordinatorPolicy dedicado, solo articulador→coordinador.
**Notes:** VoterPolicy (existing) was checked as a reference — it's role-only, no per-record ownership comparison anywhere in the codebase today. CoordinatorPolicy will be the first ownership-aware Policy in SIGMA.

---

## Denial Granularity

**Question 1: Where does the policy apply?**

| Option | Description | Selected |
|--------|-------------|----------|
| Solo acciones directas sobre un registro | Policy protects view()/update() on one specific coordinador (e.g. direct edit-URL navigation). Lists stay plain query filters — a non-owned coordinador just doesn't appear. | ✓ |
| También filtrado de listas vía Policy | Same Policy also drives list-level filtering (viewAny + scoping), single source of truth for both | |

**Question 2: 403 or 404 for direct non-owned access?**

| Option | Description | Selected |
|--------|-------------|----------|
| 403 con razón explícita | Matches the already-validated Phase 05.1 PERM-02 precedent ("denials name the specific reason") and AUTHZ-02's literal wording | ✓ |
| 404 (no revela existencia) | More conservative on information leakage, but contradicts AUTHZ-02's "clear reason, not silent" intent and the PERM-02 precedent | |

**User's choices:** Direct-record-only enforcement; 403 with explicit reason.
**Notes:** 403 choice directly cites the already-validated PERM-02 precedent from Phase 05.1 (`.planning/PROJECT.md` Key Decisions table) — no new precedent being set, just extending an existing one to a new resource type.

---

## Claude's Discretion

- Exact `CoordinatorPolicy` method signatures / Filament registration convention — mirror `VoterPolicy`/`InvitationPolicy`.
- Exact query shape for transitive-team resolution (inline `whereHas` per call site vs. a shared helper) — planner's call, informed by `CLAUDE.md`'s "thin controllers, logic in Actions/Services" convention.
- Test structure/naming — follow existing Pest conventions (`CoordinatorLeaderRelationshipTest`, `OwnershipScopedWidgetsTest` shape).

## Deferred Ideas

- Articulador-row correctness (labeling/branching) in `TopCoordinatorsTable`, `ApoyosLideresCoordinadoresTable`, `TerritorialOwnershipTable` — deferred to Phase 14/15, once `canAccessPanel()` exposes articuladores to any panel.
- Formalizing the implicit coordinador→líder ownership pattern as a Policy — explicitly rejected as in-scope for this phase.
