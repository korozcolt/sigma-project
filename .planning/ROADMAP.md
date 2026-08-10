# Roadmap: SIGMA - Sistema Integral de Gestion y Analisis Electoral

## Milestones

- ✅ **v1.0 MVP Hardening** — Phases 1-5.1 (shipped 2026-07-24)
- ✅ **v1.1 Consulta de Puesto de Votación Resiliente** — Phases 6-11 (shipped 2026-08-10)
- 🚧 **v1.2 Articuladores + Metadata de Usuario** — Phases 12-17 (in progress)

## Phases

<details>
<summary>✅ v1.0 MVP Hardening (Phases 1-5.1) — SHIPPED 2026-07-24</summary>

- [x] Phase 1: Campaign Safety & Role Boundaries — completed 2026-07-24 (via 02.1 + incidental work + 05.1 gap closure)
- [x] Phase 2: Voter Spine Hardening — completed 2026-07-24 (via 02.1 + incidental work + 05.1 gap closure)
- [x] Phase 02.1: Apoyos - Reglas Core y Segmentacion (INSERTED) — 11/11 plans — completed 2026-07-24
- [x] Phase 3: Outreach & Follow-up Reliability — completed 2026-07-24 (via incidental work + 05.1 gap closure)
- [x] Phase 4: Trusted Reporting & Control Surfaces — completed 2026-07-24 (via 04.1 + 05.1 gap closure)
- [x] Phase 04.1: Reportes Avanzados de Apoyos (INSERTED) — 5/5 plans — completed 2026-07-24
- [x] Phase 5: Day D Readiness & Trust Safeguards — completed 2026-07-24 (via incidental work + 05.1 gap closure)
- [x] Phase 05.1: Cross-Phase Hardening & Trust Safeguards Closure (INSERTED) — 9/9 plans — completed 2026-07-24

Full phase details, success criteria, and per-plan history: `.planning/milestones/v1.0-ROADMAP.md`
Requirements traceability: `.planning/milestones/v1.0-REQUIREMENTS.md`
Shipped summary: `.planning/MILESTONES.md`

</details>

<details>
<summary>✅ v1.1 Consulta de Puesto de Votación Resiliente (Phases 6-11) — SHIPPED 2026-08-10</summary>

- [x] Phase 6: National Census Snapshot Import — completed 2026-07-24
- [x] Phase 7: Source-Flag Schema & Resolution Audit Trail — completed 2026-07-24
- [x] Phase 8: Resilient PollingPlaceResolver Service — completed 2026-07-25
- [x] Phase 9: Live-Source Feasibility Spike — completed 2026-07-25 (Verdict: GO)
- [x] Phase 10: Operator Provenance & Fallback Controls — completed 2026-07-26
- [x] Phase 11: Scheduled Reconciliation Job — completed 2026-07-26

Full phase details, success criteria, and per-plan history: `.planning/milestones/v1.1-ROADMAP.md`
Requirements traceability: `.planning/milestones/v1.1-REQUIREMENTS.md`
Shipped summary: `.planning/MILESTONES.md`

</details>

### 🚧 v1.2 Articuladores + Metadata de Usuario (In Progress)

**Milestone Goal:** Articuladores organize a set of coordinadores (creating and managing them, one extra hierarchy level, no further nesting), and any superior (líder/coordinador/articulador/superadmin) can assign values from a superadmin-predefined key catalog (e.g. `biaticos`, `almuerzo`, `incentivo`) to their subordinates — filterable and sortable in Filament listings.

This milestone adds two related but separable capabilities on top of SIGMA's existing hierarchy: a new `articulador` (`area_coordinator`) role above `coordinador`, mirroring the existing `coordinator_user_id` self-referencing pattern, and a superadmin-managed, typed metadata-key catalog with per-subordinate value assignment, auditability, and Filament filter/sort/export support. Schema and authorization land first so the UI phases build on a correct, non-leaking foundation.

- [ ] **Phase 12: Hierarchy & Metadata Schema Foundation** - Additive schema/model layer for the articulador tier and the metadata catalog, with no UI yet
- [ ] **Phase 13: Hierarchy Authorization & Call-Site Audit** - Existing coordinator-scoped surfaces resolve an articulador's full team, with an explicit ownership policy
- [ ] **Phase 14: Articulador Admin Resource & Hierarchy Wiring** - Superadmin/admin_campaign manages articuladores from the admin panel, coordinador behavior unchanged
- [ ] **Phase 15: Articulador Self-Service Panel** - Articulador manages their own coordinadores from a dedicated self-service panel
- [ ] **Phase 16: Metadata Catalog UI & Assignment** - Superadmin manages the metadata catalog; superiors assign auditable, atomic values to subordinates
- [ ] **Phase 17: Filter/Sort/Export Surfaces** - Filament tables filter/sort by metadata with correct numeric ordering, exports include metadata columns

## Phase Details

### Phase 12: Hierarchy & Metadata Schema Foundation
**Goal**: The database/model layer supports the new articulador tier (flat, uncapped, one level above coordinador) and a typed, catalog-backed metadata system, ready for authorization and UI work to build on without further schema changes.
**Depends on**: Nothing (first phase of v1.2)
**Requirements**: ARTIC-04, ARTIC-05
**Success Criteria** (what must be TRUE):
  1. The `area_coordinator` Spatie role and a dedicated `area_coordinator_user_id` self-referencing FK exist on `users`, entirely separate from `coordinator_user_id`; the full existing test suite (coordinator/leader relations, panel access) passes unchanged.
  2. The schema and model relations structurally allow only one extra hierarchy level — an articulador has coordinadores, and coordinadores keep their existing leader relation — with no relation or migration that would let an articulador have another articulador, or a coordinador have sub-coordinadores (ARTIC-04).
  3. No cap, counter column, or validation rule limits how many coordinadores an articulador can have — assigning any number of coordinadores to one articulador succeeds with no backend-enforced limit, verified via tinker (ARTIC-05).
  4. `metadata_keys` (with a `type` column: numeric/text/date/select) and `user_metadata_values` (id, user_id, metadata_key_id, value, assigned_by, assigned_at) tables exist with correct FK and uniqueness constraints, verified via tinker — no JSON column is added to `users`.
**Plans**: 2 plans

Plans:
- [ ] 12-01-PLAN.md — area_coordinator role + area_coordinator_user_id hierarchy FK/relations (ARTIC-04, ARTIC-05)
- [ ] 12-02-PLAN.md — metadata_keys + user_metadata_values catalog schema (append-only, D-02)

### Phase 13: Hierarchy Authorization & Call-Site Audit
**Goal**: Existing hierarchy-scoped surfaces correctly resolve an articulador's transitive team, and an explicit ownership policy prevents cross-boundary access, before any new UI is built on top of the new role.
**Depends on**: Phase 12
**Requirements**: AUTHZ-01, AUTHZ-02, AUTHZ-03
**Success Criteria** (what must be TRUE):
  1. `TopLeadersTable`, `TopLeadersExport`, `LeadersExportController` (and equivalent widgets/exports/dashboards that assume coordinador is the top of the hierarchy) correctly include an articulador's full transitive team instead of returning empty or incomplete results (AUTHZ-01).
  2. An explicit policy denies an articulador from viewing or editing a coordinador that does not belong to them, with a clear denial reason rather than a silent empty result (AUTHZ-02).
  3. Campaign-scoped queries for the new role continue to respect `CampaignMembershipScope` — an articulador in Campaign A cannot see coordinadores or data belonging to Campaign B (AUTHZ-03).
**Plans**: TBD
**UI hint**: yes

### Phase 14: Articulador Admin Resource & Hierarchy Wiring
**Goal**: A superadmin/admin_campaign can create and manage articulador users from the admin panel and wire coordinadores to them, while existing coordinador behavior is fully preserved whether or not an articulador is assigned.
**Depends on**: Phase 13
**Requirements**: ARTIC-01, ARTIC-03
**Success Criteria** (what must be TRUE):
  1. Superadmin/admin_campaign can create a user with the Articulador role from the admin panel (`AreaCoordinatorResource`), and assign or reassign coordinadores to that articulador via a new selector on `CoordinatorForm` (ARTIC-01).
  2. A coordinador with no articulador assigned continues to function identically to today — own panel, own leaders/apoyos, dashboards, exports — no regression (ARTIC-03).
  3. A coordinador with an articulador assigned also continues to function identically day to day — assigning an articulador is purely organizational and changes nothing about the coordinador's own experience (ARTIC-03).
**Plans**: TBD
**UI hint**: yes

### Phase 15: Articulador Self-Service Panel
**Goal**: An articulador manages their own coordinadores from a dedicated self-service panel, mirroring the existing coordinador self-service experience.
**Depends on**: Phase 14
**Requirements**: ARTIC-02
**Success Criteria** (what must be TRUE):
  1. An articulador logs into their own panel (`AreaCoordinatorPanelProvider`, mirroring `CoordinatorPanelProvider`) and sees only their own coordinadores, never another articulador's.
  2. An articulador creates a new coordinador via a form on their panel, and the new coordinador is automatically linked to them via `area_coordinator_user_id`.
  3. An articulador edits and manages their existing coordinadores entirely from their own panel, with no need for admin-panel access.
**Plans**: TBD
**UI hint**: yes

### Phase 16: Metadata Catalog UI & Assignment
**Goal**: A superadmin manages a predefined metadata-key catalog, and any superior assigns auditable, atomic metadata values to their direct subordinates, individually or in bulk.
**Depends on**: Phase 12 (schema), Phase 13 (ownership resolution needed to know "my subordinates")
**Requirements**: META-01, META-02, META-03, META-04, META-05, META-06
**Success Criteria** (what must be TRUE):
  1. Superadmin creates, edits, and deactivates metadata keys (name + type: numeric/text/date/select-with-options) from a dedicated Filament resource; there is no way to enter a freeform key name anywhere else in the app (META-01, META-02).
  2. A líder/coordinador/articulador/superadmin assigns a metadata value from the catalog to one of their direct subordinates via a form scoped to the catalog and to their own subordinates only (META-03).
  3. A superior selects multiple subordinates at once and assigns the same metadata value to all of them in a single bulk action (META-04).
  4. Every metadata assignment records who assigned it, to whom, what value, and when, and this is visible in an audit trail (META-05).
  5. Two concurrent assignments to different keys on the same subordinate never clobber each other — writes are atomic per key, verified against a race-condition test (META-06).
**Plans**: TBD
**UI hint**: yes

### Phase 17: Filter/Sort/Export Surfaces
**Goal**: Operators can filter, sort, and export by any assigned metadata value across the relevant Filament tables, with correct numeric ordering for numeric keys.
**Depends on**: Phase 16 (needs real assignable metadata to filter/sort/export against)
**Requirements**: FILT-01, FILT-02, FILT-03
**Success Criteria** (what must be TRUE):
  1. The Users, Coordinators, Leaders, and Articuladores Filament tables can be filtered by a chosen metadata key and value (FILT-01).
  2. The same tables can be sorted by a metadata key's value, with numeric-typed keys sorting numerically (10 > 2) rather than alphabetically (FILT-02).
  3. Existing CSV exports for users/coordinadores/leaders include columns for each assigned metadata key (FILT-03).
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 12 → 13 → 14 → 15 → 16 → 17

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1-5.1. v1.0 MVP Hardening | 25/25 | Complete | 2026-07-24 |
| 6-11. v1.1 Consulta de Puesto de Votación Resiliente | 15/15 | Complete | 2026-08-10 |
| 12. Hierarchy & Metadata Schema Foundation | 0/2 | Planned | - |
| 13. Hierarchy Authorization & Call-Site Audit | 0/? | Not started | - |
| 14. Articulador Admin Resource & Hierarchy Wiring | 0/? | Not started | - |
| 15. Articulador Self-Service Panel | 0/? | Not started | - |
| 16. Metadata Catalog UI & Assignment | 0/? | Not started | - |
| 17. Filter/Sort/Export Surfaces | 0/? | Not started | - |

---

*v1.1 shipped 2026-08-10. v1.2 roadmap created 2026-08-10 — 6 phases (12-17), 17/17 requirements mapped.*
