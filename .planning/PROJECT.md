# SIGMA - Sistema Integral de Gestion y Analisis Electoral

## What This Is

SIGMA is a brownfield political operations platform for running campaign management from a single system. It centralizes campaign setup, territorial organization, voter operations, validation, communications, reporting, and election-day execution with role-based access and campaign-level data isolation.

As of v1.0 (shipped 2026-07-24), the platform's core electoral workflows across all Filament panels have been hardened end to end for operational trust: campaign isolation, role/permission clarity, the Apoyo (voter) lifecycle, outreach, reporting, and Day D execution are all verified against their v1 requirements. The product direction remains to consolidate SIGMA as the command center a campaign can depend on daily, not just a collection of modules.

## Core Value

Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## Requirements

### Validated

- ✓ Multi-campaign campaign management exists with active campaign context and super admin override - existing
- ✓ Role-based access exists across super admins, campaign admins, coordinators, leaders, and reviewers - existing
- ✓ Territorial organization exists with departments, municipalities, neighborhoods, and user assignments - existing
- ✓ Voter registration, lifecycle states, and electoral census validation exist - existing
- ✓ Survey workflows, call center flows, and SMS communication features exist - existing
- ✓ Election-day execution exists with vote records, evidence capture, and audit-oriented traceability - existing
- ✓ Administrative panels, leader/coordinator panels, widgets, exports, and operational dashboards exist - existing
- ✓ Apoyos (renamed from Votantes in the UI) support duplicate-cédula tracking with an auditable sufijo, leader/coordinator exclusion from being registered as someone else's Apoyo, global Gremio/Subcategoría classification, and admin-only CSV bulk import - validated in Phase 02.1
- ✓ Advanced Apoyo reporting exists: leader/coordinator/polling-place rankings (excluding duplicate-status apoyos), coordinator coverage as team rollup, rejections report, duplicates report (intentional cross-campaign exception), combined flat CSV export, and jurisdiction dentro/fuera report - validated in Phase 04.1
- ✓ Campaign-safe behavior is enforced by default across critical workflows (campaign scoping, permissions, imports/exports, reporting, jobs) - validated in Phases 1-5 (built via 02.1/04.1 + incidental work) and closed out in Phase 05.1 (CAMP-05, PERM-02/03 gaps)
- ✓ End-to-end voter operations chain is hardened: campaign context, territorial assignment, creation/import, census validation (now UI-wired with clear result+source), follow-up segmentation (contact-state filter), and Day D readiness - validated in Phases 1-5 + Phase 05.1 (VOTE-03/04/06 gaps)
- ✓ Operator friction reduced: voter profile shows current stage, what's missing, and next recommended action - validated in Phase 05.1 (VOTE-04)
- ✓ Outreach reliability: campaign-safe call queue (regression-tested), traceable call outcomes, linked survey responses, auditable SMS status (Hablame statusId classification bug fixed), anti-duplicate follow-up (regression-tested) - validated in Phases 1-5 + Phase 05.1 (OUTR-01/04/05 gaps)
- ✓ Operational dashboards are reliable for decision-making: counts reconcile to campaign-scoped data, follow-up backlog visible, coordinator/leader dashboards scoped to their own team/territory (not campaign-wide), drill-through from aggregate to filtered record list - validated in Phase 4/04.1 + Phase 05.1 (REPT-02/03/04 gaps, REPT-03 was the single biggest gap found)
- ✓ Role and permission behavior is stable and explains itself: authorization denials name the specific reason (campaign scope, role, or territorial ownership) instead of a generic 403 - validated in Phase 05.1 (PERM-02)
- ✓ Day D flows are field-ready: live voter lookup, evidence-gated vote marking (photo+GPS), DB-level duplicate-vote prevention with a defined conflict rule, per-territory participation breakdown - validated in Phase 5 + Phase 05.1 (DAYD-03/04 gaps)
- ✓ Highest-risk workflows are test-protected and operationally observable: `FinalizeElectionEvent` has a direct job test and DB-level duplicate-prevention test, the Day D closure path runs on the real queue with structured logging and `failed_jobs` visibility instead of `dispatchSync` - validated in Phase 05.1 (QUAL-01/02, QUAL-02 was the single biggest gap found)
- ✓ Leader-account creation requires OTP verification via Hablame SMS with a per-campaign-configurable message; a Super Admin can toggle Laravel's native maintenance mode with automatic self-bypass - client-requested items, validated in Phase 05.1 (live-tested checkpoints; the kill switch's initial self-lockout bug was found and fixed during checkpoint verification)

### Active

*(none yet — v1.0 shipped 2026-07-24; run `/gsd:new-milestone` to scope the next milestone's Active requirements)*

### Out of Scope

- Major net-new modules unrelated to the voter operations spine - this milestone is about operational maturity, not feature sprawl
- Cross-campaign blended workflows for standard campaign users - strict campaign isolation is a product requirement, not a convenience feature
- Large platform expansion before hardening current flows - reliability and trust take priority over breadth
- Treating dashboards as cosmetic analytics only - reporting must be grounded in operational truth, not vanity metrics

## Context

SIGMA is built as a Laravel 12, Filament 4, Livewire 3, Volt, and Tailwind 4 application with multiple panels and an established test suite. The current codebase already supports multi-campaign operation, territorial structures, voter management, census validation, surveys, call center work, SMS messaging, and election-day activity capture.

The most fragile workflow for the next milestone is the voter operations chain because it crosses campaign scoping, permissions, territorial assignment, imports, validation records, survey relationships, communication flows, reporting, and Day D actionability. The product risk is not only missing features; it is that transitions between existing features can still feel brittle, require too much internal system knowledge, or surface inconsistent data.

A concrete example of this fragility is the production `CallQueueTable` widget failure in the admin panel, where a widget closure typed for `Illuminate\\Database\\Eloquent\\Builder` received a `HasMany` relation instead. Issues like this directly undermine operator trust because they interrupt follow-up operations in a critical workflow.

Real users think in tasks rather than modules. They need to load voters, validate census status, understand territory ownership, segment contacts, trigger calls or messages, and know who is ready for election day without having to understand SIGMA's internal structure.

## Constraints

- **Architecture**: Maintain the existing Laravel, Filament, Livewire, and Eloquent architecture - the current platform is already substantial and should be hardened in place
- **Product Scope**: Prioritize hardening existing workflows over adding major new modules - the immediate goal is operational trust
- **Isolation**: Campaign data isolation must be strict by default - cross-campaign leakage would damage trust and correctness
- **Roles**: Experiences must remain role-aware and predictable - admins, coordinators, leaders, and reviewers each need stable boundaries
- **Operations**: Reporting, widgets, and exports must reflect campaign reality closely enough for real decisions - inaccurate operational numbers are unacceptable
- **Quality**: The highest-risk voter and Day D flows require test protection - fragile workflows cannot rely on manual confidence alone

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Focus the next milestone on the voter operations spine | It is the business-critical chain connecting campaign structure, validation, communications, reporting, and Day D execution | Implemented across Phases 1-5 + 05.1 |
| Harden before expanding scope | Existing capabilities already cover most core modules, but trust depends on smooth end-to-end operation | Implemented — Phase 05.1 closed the remaining gaps instead of adding new modules |
| Treat multi-campaign safety as a default product behavior | Role boundaries and campaign scoping must be invisible and reliable for normal users | Implemented — Phase 05.1 closed CAMP-05/PERM-02/03 |
| Use dashboards and reporting as operational control surfaces | SIGMA should act as a campaign command center, not just a record system | Implemented — Phase 05.1 closed REPT-02/03/04 (ownership-scoped dashboards, backlog, drill-through) |
| Capture production failures as planning inputs | Real breakages like the call queue widget error reveal where operator trust is weakest | Implemented — the same audit-then-close pattern found and fixed a live Hablame SMS bug and a kill-switch self-lockout bug during Phase 05.1 |
| "Reasignar dueño de duplicado" performs a real ownership transfer (registered_by), not just a status-flag clear | Client's original written requirement said "reasignar la propiedad de la cédula al otro Líder" — literal ownership transfer, confirmed during Phase 02.1's gap closure (plan 02.1-11) after initial narrower reading was flagged by verification | Implemented in Phase 02.1 |
| Coordinator coverage report shows no numeric "meta" (quota/goal) field | No quota/goal field exists anywhere in the schema; client confirmed during Phase 04.1 discuss-phase that leader-assignment coverage visibility (leaders count, apoyos/leader, zero-apoyo leaders) satisfies the original "meta vs. real" framing without adding new schema | Implemented in Phase 04.1 |
| Duplicates report is the one intentional exception to strict campaign isolation | A duplicate cédula spanning two different campaigns IS the case that must be visible; every other widget/export in Phase 04.1 remains strictly campaign-scoped | Implemented in Phase 04.1 |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `$gsd-transition`):
1. Requirements invalidated? -> Move to Out of Scope with reason
2. Requirements validated? -> Move to Validated with phase reference
3. New requirements emerged? -> Add to Active
4. Decisions to log? -> Add to Key Decisions
5. "What This Is" still accurate? -> Update if drifted

**After each milestone** (via `$gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check - still the right priority?
3. Audit Out of Scope - reasons still valid?
4. Update Context with current state

## Current State

**Shipped: v1.0 MVP Hardening (2026-07-24).** All 30 v1 requirements Done. See `.planning/milestones/v1.0-ROADMAP.md` and `.planning/milestones/v1.0-REQUIREMENTS.md` for the full archived record, and `.planning/MILESTONES.md` for the shipped summary.

## Next Milestone Goals

Not yet defined — run `/gsd:new-milestone` to scope v1.1 (or v2.0, depending on direction).

---
*Last updated: 2026-07-24 after v1.0 milestone completion*
