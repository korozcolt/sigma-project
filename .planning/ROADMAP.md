# Roadmap: SIGMA - Sistema Integral de Gestion y Analisis Electoral

## Overview

This roadmap hardens SIGMA along the actual campaign operations spine: first make campaign boundaries and role behavior dependable, then stabilize voter workflow state, then make outreach reliable, then make reporting trustworthy, and finally make election-day execution field-ready with operational safeguards.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Campaign Safety & Role Boundaries** - Make campaign scoping, role visibility, and ownership cues safe by default.
- [ ] **Phase 2: Voter Spine Hardening** - Stabilize the voter lifecycle from creation through validation, assignment, and readiness state.
- [ ] **Phase 3: Outreach & Follow-up Reliability** - Make call, survey, and messaging workflows traceable and resistant to stale state.
- [ ] **Phase 4: Trusted Reporting & Control Surfaces** - Turn dashboards, filters, and exports into reliable campaign decision tools.
- [ ] **Phase 5: Day D Readiness & Trust Safeguards** - Make election-day execution auditable, field-ready, and protected by verification.

## Phase Details

### Phase 1: Campaign Safety & Role Boundaries
**Goal**: Campaign-scoped users operate only within the correct campaign and role limits, with clear ownership visibility.
**Depends on**: Nothing (first phase)
**Requirements**: CAMP-01, CAMP-02, CAMP-03, CAMP-04, CAMP-05, PERM-01, PERM-02, PERM-03
**Success Criteria** (what must be TRUE):
  1. Super admin can choose the active campaign explicitly and always see which campaign is active before taking action.
  2. Campaign-scoped users only see records, panels, actions, widgets, and detail views permitted for their campaign and role.
  3. When an action is blocked, SIGMA shows whether campaign scope, role, or territorial responsibility is the reason.
  4. Operators can tell who owns a voter, territory, or follow-up queue from the relevant workflow surface.
**Plans**: TBD
**UI hint**: yes

### Phase 2: Voter Spine Hardening
**Goal**: Operators can move voters through creation, assignment, validation, and readiness states without ambiguity or state drift.
**Depends on**: Phase 1
**Requirements**: VOTE-01, VOTE-02, VOTE-03, VOTE-04, VOTE-05, VOTE-06
**Success Criteria** (what must be TRUE):
  1. Operator can create or import voters into the active campaign without cross-campaign contamination.
  2. Operator can assign each voter to the correct territory and responsible role within the active campaign.
  3. Operator can validate a voter against census data and clearly see the result and source.
  4. Operator can see each voter's current stage, missing data, and next recommended action.
  5. Voter status and segmentation stay consistent across imports, validation, surveys, calls, messages, dashboards, and Day D entry points.
**Plans**: TBD
**UI hint**: yes

### Phase 02.1: Apoyos - Reglas Core y Segmentacion (rename cosmetico Votante->Apoyo, exclusion lider-apoyo, duplicados con sufijo, gremio/subcategoria, import masivo CSV) (INSERTED)

**Goal:** Campaign operators work with "Apoyos" (not "Votantes") everywhere in the UI, leaders/coordinators cannot be registered as someone else's Apoyo, duplicate cedulas are tracked with an auditable suffix instead of blocked, Apoyos can be optionally classified by Gremio/Subcategoria, and admins can bulk-import Apoyos via CSV with a partial-success rejection report.
**Requirements**: D-01, D-02, D-03, D-04, D-05, D-06, D-07, D-08, D-09, D-10 (locked decisions from 02.1-CONTEXT.md; no formal REQ-IDs assigned)
**Depends on:** Phase 2
**Plans:** 11/11 plans complete

Plans:
- [x] 02.1-01-PLAN.md — Wave 0: test scaffolding + Filament import infrastructure migrations
- [x] 02.1-02-PLAN.md — Gremio/Subcategoria hierarchical global catalog (D-04, D-05, D-09)
- [x] 02.1-03-PLAN.md — Duplicate-cedula schema, VoterStatus::DUPLICATE, VoterTest rewrite, REGLAS_NEGOCIO §2 (D-02, D-10)
- [x] 02.1-04-PLAN.md — Leader/coordinator exclusion validation rule (D-08)
- [x] 02.1-05-PLAN.md — Rename sweep: Filament admin Pages/Widgets/Resources + lang/es (D-01)
- [x] 02.1-06-PLAN.md — Rename sweep: Exports/Console Commands/Enums/public registration (D-01)
- [x] 02.1-07-PLAN.md — Rename sweep: Leader/Coordinator/Campaign-admin panels + landing page (D-01)
- [x] 02.1-08-PLAN.md — Voter classification/document schema + duplicate-reassignment audit action (D-03, D-10)
- [x] 02.1-09-PLAN.md — VoterForm/VotersTable/VoterResource wiring: rename + Gremio/Subcategoria + duplicate confirm UX + exclusion rule + VoterResourceTest rewrite (D-01, D-02, D-04, D-05, D-08)
- [x] 02.1-10-PLAN.md — ApoyosImport CSV bulk import, admin-only, partial success + rejection report (D-06, D-07)

### Phase 3: Outreach & Follow-up Reliability
**Goal**: Campaign teams can run call, survey, and messaging workflows with traceable outcomes and no stale queue behavior.
**Depends on**: Phase 2
**Requirements**: OUTR-01, OUTR-02, OUTR-03, OUTR-04, OUTR-05
**Success Criteria** (what must be TRUE):
  1. Reviewer or operator can work a campaign-safe call queue without runtime failures or cross-campaign leakage.
  2. Call outcomes update follow-up state and remain traceable to the assignment and contact attempt that produced them.
  3. Survey responses remain linked to the outreach context that generated them.
  4. Operators can audit outbound SMS or messaging from send intent through provider outcome.
  5. SIGMA blocks duplicate or invalid follow-up caused by stale queue state, stale segments, or unreconciled contact results.
**Plans**: TBD
**UI hint**: yes

### Phase 4: Trusted Reporting & Control Surfaces
**Goal**: Decision-makers can rely on dashboards, filters, and exports as operational truth with drill-through by territory and workload.
**Depends on**: Phase 3
**Requirements**: REPT-01, REPT-02, REPT-03, REPT-04
**Success Criteria** (what must be TRUE):
  1. Dashboards, widgets, filters, and exports show counts that reconcile to the campaign-scoped source records behind them.
  2. Decision-makers can see campaign health indicators for voter progress, validation progress, follow-up backlog, and Day D readiness.
  3. Coordinators and leaders can view territorial progress and owned workload relevant to their scope.
  4. Users can drill from key aggregate metrics into the underlying record list that explains the number.
**Plans**: TBD
**UI hint**: yes

### Phase 04.1: Reportes Avanzados de Apoyos (ranking lider-coordinador-puesto votacion, informe rechazos, informe duplicados, export CSV plano, informe jurisdiccion dentro-fuera) (INSERTED)

**Goal:** Seis reportes/dashboards trustworthy sobre datos de Apoyos (ranking lideres/coordinadores/puestos de votacion, informe de rechazos, informe de duplicados con excepcion intencional de cruce de campanas, CSV plano combinado Apoyos+Lideres+Coordinadores, informe de jurisdiccion dentro/fuera) — cada uno como widget de dashboard con boton de exportar, excluyendo apoyos DUPLICATE de todos los conteos/rankings salvo el propio informe de duplicados.
**Requirements**: D-01, D-02, D-03, D-04, D-05, D-06 (locked decisions from 04.1-CONTEXT.md; no formal REQ-IDs assigned)
**Depends on:** Phase 4
**Plans:** 5/5 plans complete

Plans:
- [x] 04.1-01-PLAN.md — Wave 0: RED test scaffolding for all 6 reports + D-01 regression test + missing PollingPlaceFactory
- [x] 04.1-02-PLAN.md — Rankings: fix TopLeadersTable (D-01), build TopCoordinatorsTable (D-05 team rollup), build TopPollingPlacesTable
- [x] 04.1-03-PLAN.md — RejectionsReportTable + DuplicatesReportTable (D-06 intentional cross-campaign exception)
- [x] 04.1-04-PLAN.md — JurisdictionReportTable (D-04 Nacional hide) + ApoyosLideresCoordinadoresTable/Export (D-03 flat CSV)
- [x] 04.1-05-PLAN.md — Wave 2: wire all 6 new widgets into AdminPanelProvider + full-suite regression gate

### Phase 5: Day D Readiness & Trust Safeguards
**Goal**: Election-day execution is field-ready, auditable, and protected by release-time verification and production visibility.
**Depends on**: Phase 4
**Requirements**: DAYD-01, DAYD-02, DAYD-03, DAYD-04, DAYD-05, QUAL-01, QUAL-02
**Success Criteria** (what must be TRUE):
  1. Operator can find the correct voter quickly within the active campaign during election-day operations.
  2. Operator can record vote status with required evidence enforced while SIGMA blocks duplicate or conflicting registrations.
  3. Campaign teams can see live participation progress by campaign and relevant territory during Day D activity.
  4. Election-day actions and evidence remain audit-ready with campaign, actor, time, and event metadata.
  5. Maintainers can detect trust-critical workflow failures through automated verification and operational visibility before users have to report them manually.
**Plans**: TBD
**UI hint**: yes

### Phase 05.1: Cross-Phase Hardening & Trust Safeguards Closure (INSERTED)

**Goal:** Close the genuine, bounded gaps found by a full codebase audit (2026-07-23) of Phases 1-5 — do NOT rebuild anything already working. The audit found Phases 1-5's core spines substantially already built (via inserted phases 02.1/04.1 plus incidental work), with the roadmap's "Not started" status stale across the board. This phase closes the specific remaining gaps rather than re-executing Phases 1-5 from scratch.
**Requirements**: PERM-02, PERM-03, CAMP-05, VOTE-03, VOTE-04, VOTE-06, OUTR-01, OUTR-04, OUTR-05, REPT-02, REPT-03, REPT-04, DAYD-03, DAYD-04, QUAL-01, QUAL-02 (all previously-defined v1 requirements, now Partial rather than new; plus 2 client-requested items without formal REQ-IDs: leader-registration OTP via Hablame SMS, and a Super Admin maintenance kill switch)
**Depends on:** Phases 1-5 (audit baseline — see `.planning/phases/05.1-cross-phase-hardening-closure/05.1-CONTEXT.md` for full gap detail per requirement)
**Success Criteria** (what must be TRUE):
  1. Authorization denials tell the operator whether campaign scope, role, or territorial ownership caused the block (PERM-02), and a consolidated view shows who owns a given territory/follow-up queue (PERM-03).
  2. Campaign scoping is verified safe inside queue/job contexts, not just interactive requests (CAMP-05).
  3. Leader registration requires OTP verification via Hablame SMS with a per-campaign-configurable message, and reliably reaches the device (`priority: true` fix); a Super Admin can toggle maintenance mode with automatic self-bypass.
  4. Operator can trigger census validation from the Voter UI and see a clear result + source (VOTE-03); the Voter profile shows what's missing and the next recommended action (VOTE-04); VotersTable supports filtering by contact state (VOTE-06).
  5. Call-queue campaign isolation and anti-duplicate-assignment logic have dedicated regression tests (OUTR-01, OUTR-05); Hablame SMS status classification correctly handles `statusId` values beyond 102/106 so real deliveries aren't misreported as failed (OUTR-04).
  6. Coordinator and Leader dashboards show workload/territory scoped to that user's own team, not campaign-wide totals (REPT-03); dashboards show a follow-up backlog indicator (REPT-02); at least the highest-value widgets support drill-through from aggregate to filtered record list (REPT-04).
  7. `vote_records` has a DB-level uniqueness constraint on `(voter_id, election_event_id)` and a defined voted/did-not-vote conflict rule (DAYD-03); Day D participation stats break down by territory, not just campaign totals (DAYD-04).
  8. `FinalizeElectionEvent` has a direct test (dispatching the real job, not reimplementing its query) and DB-level duplicate prevention is tested (QUAL-01); the Day D path has structured logging and the finalize job runs queued (not `dispatchSync`) with failure visibility (QUAL-02).
**Plans:** 5/9 plans executed (Wave 1 complete)

Plans:
- [x] 05.1-01-PLAN.md — Auth/permissions hardening: reason-specific denial messages + consolidated ownership view + CAMP-05 audit (PERM-02, PERM-03, CAMP-05)
- [ ] 05.1-02-PLAN.md — OTP verification for coordinator-driven leader-account creation, via Hablame SMS priority:true (client request)
- [ ] 05.1-03-PLAN.md — Super Admin maintenance kill switch with automatic bypass (client request)
- [x] 05.1-04-PLAN.md — Voter UI: census validation action + profile guidance + contact-state filter (VOTE-03, VOTE-04, VOTE-06)
- [x] 05.1-05-PLAN.md — Outreach: Hablame statusId fix + call-queue isolation/anti-duplicate regression tests (OUTR-01, OUTR-04, OUTR-05)
- [x] 05.1-06-PLAN.md — Ownership-scoped Coordinator/Leader dashboards (REPT-03)
- [ ] 05.1-07-PLAN.md — Follow-up backlog widget + drill-through from aggregate widgets to filtered lists (REPT-02, REPT-04)
- [x] 05.1-08-PLAN.md — Day D: DB unique constraint + conflict handling + per-municipality participation breakdown (DAYD-03, DAYD-04)
- [ ] 05.1-09-PLAN.md — FinalizeElectionEvent direct job test + real queue dispatch + structured Day D logging (QUAL-01, QUAL-02)
**UI hint**: yes (REPT-03 dashboard scoping, VOTE-04 profile guidance, PERM-02 error messaging all have user-facing surfaces)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 5.1

**Audit note (2026-07-23):** A full codebase audit found Phases 1-5's roadmap status below stale — see `.planning/phases/05.1-cross-phase-hardening-closure/05.1-CONTEXT.md` for the complete per-requirement coverage table. Actual coverage: Phase 1 ~70%, Phase 2 ~65%, Phase 3 ~70%, Phase 4 ~55%, Phase 5 ~60-65%. Phase 05.1 closes the real remaining gaps across all five.

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Campaign Safety & Role Boundaries | 0/TBD | Substantially covered (~70%, via 02.1 + incidental work) — see 05.1-CONTEXT.md | - |
| 2. Voter Spine Hardening | 0/TBD | Substantially covered (~65%, via 02.1 + incidental work) — see 05.1-CONTEXT.md | - |
| 3. Outreach & Follow-up Reliability | 0/TBD | Substantially covered (~70%, incidental work) — see 05.1-CONTEXT.md | - |
| 4. Trusted Reporting & Control Surfaces | 5/5 | Substantially covered (~55%, via 04.1) — see 05.1-CONTEXT.md | 2026-07-23 |
| 5. Day D Readiness & Trust Safeguards | 0/TBD | Substantially covered (~60-65%, incidental work) — see 05.1-CONTEXT.md | - |
| 05.1. Cross-Phase Hardening & Trust Safeguards Closure (INSERTED) | 5/9 | In Progress (Wave 1 complete) | - |
