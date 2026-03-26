# Project Research Summary

**Project:** SIGMA - Sistema Integral de Gestion y Analisis Electoral
**Domain:** Political campaign operations platform
**Researched:** 2026-03-25
**Confidence:** HIGH

## Executive Summary

Current campaign-operations products converge on a clear baseline: a trustworthy voter database, targeted outreach, field or call workflows, role-aware operational dashboards, and election-day execution support. The strongest systems do not win by having the most modules; they win by making the voter workflow coherent across targeting, contact, reporting, and turnout operations.

For SIGMA, the research supports a brownfield hardening strategy, not a platform rewrite. The existing Laravel/Filament/Livewire stack is a good fit for this milestone. The main gap is not missing framework capability; it is operational integrity across campaign boundaries, workflow state transitions, reporting truth, and communications reconciliation. The biggest risk is that dashboards or workflows look complete while still requiring tribal knowledge or leaking inconsistent state.

## Key Findings

### Recommended Stack

SIGMA's current stack is already aligned with this product category. The strongest near-term move is to harden in place and add production support pieces where needed: PostgreSQL 17 with PostGIS, Redis plus Horizon for queues/cache/session, S3-compatible object storage for evidence and artifacts, and observability and rollout controls through Pulse, Telescope, Pennant, and static analysis. Search and analytics expansions should stay conditional on real operator pain, not assumed by default. Mixing a major framework upgrade into this milestone would create unnecessary execution risk.

**Core technologies:**
- Laravel 12 - workflow orchestration, auth, policies, jobs, and operational backend
- Filament 4 + Livewire/Volt - role-aware operational UI and workflow screens
- PostgreSQL 17 + PostGIS - safer production persistence, territorial logic, and reporting than SQLite
- Redis + Horizon - queues, locks, cache, and background workflow coordination
- S3-compatible object storage - durable evidence and import/export artifact storage
- Pulse / Telescope / Pennant / Larastan - observability, rollout control, and earlier bug detection

### Expected Features

Research from NGP VAN, NationBuilder, and companion field tools reinforces that users expect targeted voter workflows, canvassing/phone-bank execution, script-driven contact capture, progress reporting, and election-day actionability. SIGMA already covers much of that territory; the opportunity is to make the voter spine feel unified and trustworthy.

**Must have (table stakes):**
- Campaign-safe voter targeting, imports, and follow-up workflows
- Call-center and contact logging with clear queue state
- Dashboards and exports that match operational reality
- Election-day readiness and turnout execution support

**Should have (competitive):**
- Strong one-instance multi-campaign isolation
- Territorial accountability and progress visibility by role
- Evidence-backed Day D workflows

**Defer (v2+):**
- Offline-first field app
- Predictive or AI scoring
- Large fundraising/compliance expansion

### Architecture Approach

The recommended architecture is a campaign-bound operational monolith with explicit workflow services. Keep the transactional source of truth in the core domain, move fragile state transitions out of widgets/resources into actions or services, and treat reporting as a derived read layer rather than a side effect of UI list queries.

**Major components:**
1. Campaign boundary and authorization - resolves who can see or change what
2. Voter spine and outreach workflows - owns state transitions from import to follow-up
3. Reporting and Day D execution - exposes operational truth and field readiness

### Critical Pitfalls

1. **Partial campaign isolation** - centralize campaign-safe query and policy enforcement
2. **Workflow state drift** - define one authoritative voter lifecycle instead of module-local meanings
3. **Communications without reconciliation** - ingest provider outcomes and queue state changes
4. **Trustless reporting** - build KPI truth from explicit aggregates and verify it
5. **Demo-ready Day D** - test duplicates, evidence rules, and live operational edges

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: Campaign Safety Baseline
**Rationale:** All downstream work depends on dependable campaign boundaries and campaign-domain metadata.
**Delivers:** Campaign domain fields, stricter scoping enforcement, policy/query coverage, export/import safety.
**Addresses:** Campaign isolation, role predictability, pending campaign-domain work from `PROGRESO.md`.
**Avoids:** Partial campaign isolation.

### Phase 2: Voter Spine Hardening
**Rationale:** This is the core user-priority workflow and the main product spine.
**Delivers:** Clear voter lifecycle, import and assignment reliability, validation continuity, next-action clarity.
**Uses:** Existing Laravel/Filament/Livewire workflow surfaces.
**Implements:** Central workflow services around voter operations.

### Phase 3: Outreach and Follow-up Reliability
**Rationale:** Calls, surveys, and messaging are where operational continuity often breaks.
**Delivers:** Stable call queue behavior, communications reconciliation, segment-safe outreach, audit trails.
**Uses:** Queueing, provider callbacks, and outreach domain rules.

### Phase 4: Trusted Command Center Reporting
**Rationale:** Decision support only matters once source workflows are clean.
**Delivers:** Verified dashboards, progress indicators, territorial and leader views, dependable exports.
**Implements:** Reporting read models or aggregate builders.

### Phase 5: Day D Operational Readiness
**Rationale:** Election-day execution must sit on top of a trustworthy voter and reporting core.
**Delivers:** Fast lookup, evidence-backed vote marking, duplication protection, live participation visibility.

### Phase Ordering Rationale

- Campaign isolation must precede reporting and Day D because every later surface depends on it
- The voter spine must be hardened before communications and dashboards can become trustworthy
- Day D comes last because it consumes state from everything else

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 3:** Messaging provider reconciliation and queue design details
- **Phase 4:** Reporting model design and aggregate verification strategy
- **Phase 5:** Field-usage assumptions and Day D failure-mode handling

Phases with standard patterns (skip research-phase if needed):
- **Phase 1:** Campaign scoping and policy hardening
- **Phase 2:** Workflow service extraction around existing CRUD-heavy flows

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Backed by the current repo and official docs for supporting choices |
| Features | HIGH | Product-category expectations are consistent across major campaign tools |
| Architecture | HIGH | Brownfield monolith hardening is a strong fit for SIGMA's current maturity |
| Pitfalls | HIGH | Strong overlap between user-reported pain and known failure modes in operations-heavy systems |

**Overall confidence:** HIGH

### Gaps to Address

- Messaging provider details need confirmation from the actual chosen vendor integration and deployment environment
- Production database choice should be finalized explicitly during planning if current environments still depend on SQLite anywhere
- Search and realtime needs should be validated against actual operator pain before adding Meilisearch or Reverb

## Sources

### Primary (HIGH confidence)
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/.planning/PROJECT.md`
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/PROGRESO.md`
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/docs/REGLAS_NEGOCIO.md`
- https://www.ngpvan.com/
- https://www.ngpvan.com/voter-file-access/
- https://www.ngpvan.com/resources/guides/voter-contact-scripts-guide/
- https://www.ngpvan.com/wp-content/uploads/2024/10/MiniVAN-reporting.pdf
- https://www.ngpvan.com/wp-content/uploads/Use-and-manage-VPB.pdf
- https://nationbuilder.com/ecanvasserapp
- https://nationbuilder.com/handynation
- https://www.twilio.com/docs/messaging/guides/outbound-message-status-in-status-callbacks

### Secondary (MEDIUM confidence)
- https://nationbuilder.com/voter_file_screencast
- https://www.postgresql.org/about/press/presskit17/
- https://redis.io/docs/latest/develop/whats-new/8-0/

---
*Research completed: 2026-03-25*
*Ready for roadmap: yes*
