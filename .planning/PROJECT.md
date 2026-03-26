# SIGMA - Sistema Integral de Gestion y Analisis Electoral

## What This Is

SIGMA is a brownfield political operations platform for running campaign management from a single system. It centralizes campaign setup, territorial organization, voter operations, validation, communications, reporting, and election-day execution with role-based access and campaign-level data isolation.

Today the platform already covers core electoral workflows across multiple Filament panels, but the next milestone is focused on making those workflows feel operationally trustworthy end to end. The product direction is to consolidate SIGMA as the command center a campaign can depend on daily, not just a collection of modules.

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

### Active

- [ ] Harden the end-to-end voter operations chain from campaign context and territorial assignment through voter creation/import, census validation, follow-up, communication flows, and Day D readiness
- [ ] Enforce campaign-safe behavior by default across critical workflows so non-super-admin users cannot view, edit, import, export, validate, message, or report across campaigns
- [ ] Reduce operator friction by making stage, missing data, next actions, and responsible role obvious throughout voter workflows
- [ ] Make operational dashboards, widget counts, filters, and exports reliable enough for campaign decision-making
- [ ] Stabilize role and permission behavior so each role has a predictable experience without hidden visibility or access surprises
- [ ] Strengthen Day D flows so live voter lookup, vote marking, evidence capture, duplication protection, and participation visibility are field-ready
- [ ] Protect the highest-risk operational workflows with tests around campaign isolation, imports/exports, validation integrity, permissions, reporting consistency, and Day D evidence/status rules

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
| Focus the next milestone on the voter operations spine | It is the business-critical chain connecting campaign structure, validation, communications, reporting, and Day D execution | - Pending |
| Harden before expanding scope | Existing capabilities already cover most core modules, but trust depends on smooth end-to-end operation | - Pending |
| Treat multi-campaign safety as a default product behavior | Role boundaries and campaign scoping must be invisible and reliable for normal users | - Pending |
| Use dashboards and reporting as operational control surfaces | SIGMA should act as a campaign command center, not just a record system | - Pending |
| Capture production failures as planning inputs | Real breakages like the call queue widget error reveal where operator trust is weakest | - Pending |

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

---
*Last updated: 2026-03-25 after initialization*
