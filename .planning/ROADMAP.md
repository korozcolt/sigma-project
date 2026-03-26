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

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Campaign Safety & Role Boundaries | 0/TBD | Not started | - |
| 2. Voter Spine Hardening | 0/TBD | Not started | - |
| 3. Outreach & Follow-up Reliability | 0/TBD | Not started | - |
| 4. Trusted Reporting & Control Surfaces | 0/TBD | Not started | - |
| 5. Day D Readiness & Trust Safeguards | 0/TBD | Not started | - |
