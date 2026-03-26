# Requirements: SIGMA - Sistema Integral de Gestion y Analisis Electoral

**Defined:** 2026-03-25
**Core Value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## v1 Requirements

### Campaign Safety

- [ ] **CAMP-01**: Super admin can select an active campaign context explicitly and see which campaign context is currently active
- [ ] **CAMP-02**: Campaign-scoped users can only view data that belongs to their active campaign across tables, widgets, dashboards, and detail pages
- [ ] **CAMP-03**: Campaign-scoped users can only create, update, import, export, message, validate, or trigger workflows within their active campaign
- [ ] **CAMP-04**: Campaign records capture election scope and territory rules needed to operate municipal, departmental, and national campaigns safely
- [ ] **CAMP-05**: Background jobs, exports, and reporting queries honor campaign boundaries the same way interactive UI actions do

### Permissions and Role Experience

- [ ] **PERM-01**: Admins, coordinators, leaders, reviewers, and super admins each see only the panels, actions, and records that match their role
- [ ] **PERM-02**: When a user cannot perform an action, SIGMA shows a clear operational reason instead of an ambiguous or technical failure
- [ ] **PERM-03**: Territorial ownership and assignment are visible enough that operators can tell who is responsible for a voter, territory, or follow-up queue

### Voter Operations

- [ ] **VOTE-01**: Operator can create or import voters into the active campaign without cross-campaign contamination
- [ ] **VOTE-02**: Operator can assign voters to the correct territorial structure and responsible role within the active campaign
- [ ] **VOTE-03**: Operator can validate voter records against census data and see the validation result and source clearly
- [ ] **VOTE-04**: Operator can see each voter's current workflow stage, what is missing, and the next recommended action
- [ ] **VOTE-05**: Voter status remains consistent across imports, validation, surveys, calls, messages, dashboards, and Day D workflows
- [ ] **VOTE-06**: Operator can segment voters for follow-up using campaign-safe filters based on readiness, validation, territory, and contact state

### Outreach and Follow-up

- [ ] **OUTR-01**: Reviewer or operator can work a campaign-safe call queue without runtime errors or cross-campaign leakage
- [ ] **OUTR-02**: Call outcomes update follow-up state in a way that is traceable to the assignment and contact attempt
- [ ] **OUTR-03**: Survey responses remain linked to the outreach attempt or call context that produced them
- [ ] **OUTR-04**: SMS or outbound communication status is auditable from send intent through provider outcome
- [ ] **OUTR-05**: SIGMA prevents duplicate or invalid follow-up caused by stale queue state, stale segmentation, or missing reconciliation

### Reporting and Control

- [ ] **REPT-01**: Dashboards, widgets, filters, and exports show counts that reconcile to campaign-scoped source records
- [ ] **REPT-02**: Decision-makers can see campaign health indicators for voter progress, validation progress, follow-up backlog, and Day D readiness
- [ ] **REPT-03**: Coordinators and leaders can view territorial progress and owned workload relevant to their scope
- [ ] **REPT-04**: Key operational metrics support drill-through from aggregate count to underlying record list

### Election Day

- [ ] **DAYD-01**: Operator can find the correct voter quickly within the active campaign during election-day operations
- [ ] **DAYD-02**: Operator can mark vote status with required evidence rules enforced for "voted" outcomes
- [ ] **DAYD-03**: SIGMA prevents duplicate or conflicting election-day status registration for the same voter and event
- [ ] **DAYD-04**: Operators can see participation progress by campaign and relevant territory during live election-day activity
- [ ] **DAYD-05**: Election-day actions and evidence are stored with audit-ready metadata tied to campaign, actor, time, and event

### Quality and Verification

- [ ] **QUAL-01**: The highest-risk workflows are protected by automated tests for campaign isolation, permissions, import/export behavior, reporting consistency, and Day D evidence integrity
- [ ] **QUAL-02**: Trust-critical operational failures are observable through logs, monitoring, or queue/error visibility before users have to report them manually

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

| Requirement | Phase | Status |
|-------------|-------|--------|
| CAMP-01 | TBD | Pending |
| CAMP-02 | TBD | Pending |
| CAMP-03 | TBD | Pending |
| CAMP-04 | TBD | Pending |
| CAMP-05 | TBD | Pending |
| PERM-01 | TBD | Pending |
| PERM-02 | TBD | Pending |
| PERM-03 | TBD | Pending |
| VOTE-01 | TBD | Pending |
| VOTE-02 | TBD | Pending |
| VOTE-03 | TBD | Pending |
| VOTE-04 | TBD | Pending |
| VOTE-05 | TBD | Pending |
| VOTE-06 | TBD | Pending |
| OUTR-01 | TBD | Pending |
| OUTR-02 | TBD | Pending |
| OUTR-03 | TBD | Pending |
| OUTR-04 | TBD | Pending |
| OUTR-05 | TBD | Pending |
| REPT-01 | TBD | Pending |
| REPT-02 | TBD | Pending |
| REPT-03 | TBD | Pending |
| REPT-04 | TBD | Pending |
| DAYD-01 | TBD | Pending |
| DAYD-02 | TBD | Pending |
| DAYD-03 | TBD | Pending |
| DAYD-04 | TBD | Pending |
| DAYD-05 | TBD | Pending |
| QUAL-01 | TBD | Pending |
| QUAL-02 | TBD | Pending |

**Coverage:**
- v1 requirements: 30 total
- Mapped to phases: 0
- Unmapped: 30

---
*Requirements defined: 2026-03-25*
*Last updated: 2026-03-25 after initialization*
