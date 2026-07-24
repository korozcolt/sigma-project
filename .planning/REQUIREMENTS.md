# Requirements: SIGMA - Sistema Integral de Gestion y Analisis Electoral

**Defined:** 2026-03-25
**Core Value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## v1 Requirements

### Campaign Safety

- [ ] **CAMP-01**: Super admin can select an active campaign context explicitly and see which campaign context is currently active
- [ ] **CAMP-02**: Campaign-scoped users can only view data that belongs to their active campaign across tables, widgets, dashboards, and detail pages
- [ ] **CAMP-03**: Campaign-scoped users can only create, update, import, export, message, validate, or trigger workflows within their active campaign
- [ ] **CAMP-04**: Campaign records capture election scope and territory rules needed to operate municipal, departmental, and national campaigns safely
- [x] **CAMP-05**: Background jobs, exports, and reporting queries honor campaign boundaries the same way interactive UI actions do

### Permissions and Role Experience

- [ ] **PERM-01**: Admins, coordinators, leaders, reviewers, and super admins each see only the panels, actions, and records that match their role
- [x] **PERM-02**: When a user cannot perform an action, SIGMA shows a clear operational reason instead of an ambiguous or technical failure
- [x] **PERM-03**: Territorial ownership and assignment are visible enough that operators can tell who is responsible for a voter, territory, or follow-up queue

### Voter Operations

- [ ] **VOTE-01**: Operator can create or import voters into the active campaign without cross-campaign contamination
- [ ] **VOTE-02**: Operator can assign voters to the correct territorial structure and responsible role within the active campaign
- [x] **VOTE-03**: Operator can validate voter records against census data and see the validation result and source clearly
- [x] **VOTE-04**: Operator can see each voter's current workflow stage, what is missing, and the next recommended action
- [ ] **VOTE-05**: Voter status remains consistent across imports, validation, surveys, calls, messages, dashboards, and Day D workflows
- [x] **VOTE-06**: Operator can segment voters for follow-up using campaign-safe filters based on readiness, validation, territory, and contact state

### Outreach and Follow-up

- [x] **OUTR-01**: Reviewer or operator can work a campaign-safe call queue without runtime errors or cross-campaign leakage
- [ ] **OUTR-02**: Call outcomes update follow-up state in a way that is traceable to the assignment and contact attempt
- [ ] **OUTR-03**: Survey responses remain linked to the outreach attempt or call context that produced them
- [x] **OUTR-04**: SMS or outbound communication status is auditable from send intent through provider outcome
- [x] **OUTR-05**: SIGMA prevents duplicate or invalid follow-up caused by stale queue state, stale segmentation, or missing reconciliation

### Reporting and Control

- [ ] **REPT-01**: Dashboards, widgets, filters, and exports show counts that reconcile to campaign-scoped source records
- [x] **REPT-02**: Decision-makers can see campaign health indicators for voter progress, validation progress, follow-up backlog, and Day D readiness
- [x] **REPT-03**: Coordinators and leaders can view territorial progress and owned workload relevant to their scope
- [x] **REPT-04**: Key operational metrics support drill-through from aggregate count to underlying record list

### Election Day

- [ ] **DAYD-01**: Operator can find the correct voter quickly within the active campaign during election-day operations
- [ ] **DAYD-02**: Operator can mark vote status with required evidence rules enforced for "voted" outcomes
- [x] **DAYD-03**: SIGMA prevents duplicate or conflicting election-day status registration for the same voter and event
- [x] **DAYD-04**: Operators can see participation progress by campaign and relevant territory during live election-day activity
- [ ] **DAYD-05**: Election-day actions and evidence are stored with audit-ready metadata tied to campaign, actor, time, and event

### Quality and Verification

- [x] **QUAL-01**: The highest-risk workflows are protected by automated tests for campaign isolation, permissions, import/export behavior, reporting consistency, and Day D evidence integrity
- [x] **QUAL-02**: Trust-critical operational failures are observable through logs, monitoring, or queue/error visibility before users have to report them manually

## v2 Requirements

### Workflow Enhancements

- **WFLO-01**: Operator can use guided shortcuts or bulk actions for common voter workflow transitions
- **WFLO-02**: Decision-makers can save reusable operational views and advanced segments

### Field Expansion

- **FIELD-01**: Field teams can use an offline-first mobile or PWA experience for canvassing and election-day support
- **FIELD-02**: SIGMA can capture richer field telemetry such as route completion or GPS-based activity progress

### Intelligence

- **INTL-01**: SIGMA can prioritize voters or territories with predictive scoring or AI-assisted recommendations

## Out of Scope

| Feature | Reason |
|---------|--------|
| Major new modules outside campaign operations hardening | This milestone is focused on operational maturity, not breadth expansion |
| Cross-campaign blended workflows for normal campaign users | Violates the campaign isolation goal that defines this product direction |
| Big-bang rewrite to SPA, microservices, or a new platform | Adds migration risk while the current problem is trust in existing workflows |
| Offline-first field app in this milestone | Valuable later, but too large for the current hardening-focused phase |
| Predictive AI features before reporting is trustworthy | Advanced intelligence is not useful until operational truth is dependable |

## Traceability

**Reconciled 2026-07-23** against a full codebase audit — see `.planning/phases/05.1-cross-phase-hardening-closure/05.1-CONTEXT.md` for per-requirement evidence. Phases 1-5's roadmap status was stale; most requirements are already substantially satisfied via inserted phases 02.1/04.1 plus incidental work. Remaining "Partial" items close in Phase 05.1.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CAMP-01 | Phase 1 | Done |
| CAMP-02 | Phase 1 | Done |
| CAMP-03 | Phase 1 | Done |
| CAMP-04 | Phase 1 | Done |
| CAMP-05 | Phase 1 | Partial (closing in Phase 05.1) |
| PERM-01 | Phase 1 | Done |
| PERM-02 | Phase 1 | Partial (closing in Phase 05.1) |
| PERM-03 | Phase 1 | Partial (closing in Phase 05.1) |
| VOTE-01 | Phase 2 | Done |
| VOTE-02 | Phase 2 | Done |
| VOTE-03 | Phase 2 | Partial (closing in Phase 05.1) |
| VOTE-04 | Phase 2 | Partial (closing in Phase 05.1) |
| VOTE-05 | Phase 2 | Done |
| VOTE-06 | Phase 2 | Partial (closing in Phase 05.1) |
| OUTR-01 | Phase 3 | Partial (closing in Phase 05.1) |
| OUTR-02 | Phase 3 | Done |
| OUTR-03 | Phase 3 | Done |
| OUTR-04 | Phase 3 | Partial (closing in Phase 05.1 — live bug confirmed 2026-07-23) |
| OUTR-05 | Phase 3 | Partial (closing in Phase 05.1) |
| REPT-01 | Phase 4 | Done |
| REPT-02 | Phase 4 | Done (closed in Phase 05.1, plan 07) |
| REPT-03 | Phase 4 | Partial (closing in Phase 05.1 — biggest Phase 4 gap) |
| REPT-04 | Phase 4 | Done (closed in Phase 05.1, plan 07) |
| DAYD-01 | Phase 5 | Done |
| DAYD-02 | Phase 5 | Done |
| DAYD-03 | Phase 5 | Done (closed in Phase 05.1, plan 08) |
| DAYD-04 | Phase 5 | Done (closed in Phase 05.1, plan 08) |
| DAYD-05 | Phase 5 | Done |
| QUAL-01 | Phase 5 | Done (closed in Phase 05.1, plan 09) |
| QUAL-02 | Phase 5 | Done (closed in Phase 05.1, plan 09) |

**Coverage:**
- v1 requirements: 30 total
- Mapped to phases: 30
- Unmapped: 0
- Done: 17 | Partial: 12 | Not covered: 1

---
*Requirements defined: 2026-03-25*
*Last updated: 2026-07-23 after full codebase audit reconciliation (Phase 05.1 inserted)*
