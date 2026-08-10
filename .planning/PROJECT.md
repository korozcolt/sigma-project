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
- ✓ The national census snapshot (`censo_decoded_202310210734.csv`, 216,527 rows) is imported into a cédula-indexed `national_census_records` table, isolated from campaign-scoped data, enriched with full department/municipality names and address via the `polling_places` FK, with Latin-1 encoding handled correctly and an unmatched-divipol-code percentage reported on every import - validated in Phase 6 (CENSO-02, CENSO-03)
- ✓ A voter's polling-place source (live / db_reconstruction / snapshot / manual) is a persisted, indexed, queryable attribute, and every change to it is captured in an append-only audit trail (actor, previous → new source, timestamp) that tolerates a nullable/headless actor for automated reconciliation writes - validated in Phase 7 (SRC-03)
- ✓ Voter polling-place lookup falls back through a single `PollingPlaceResolver` cascade (campaign DB → national snapshot → bounded live attempt) without ever blocking on a dead live source, never silently downgrades a live-verified result to a staler one (precedence/no-downgrade guard), and the live-source architecture supports multiple interchangeable adapters without a resolver redesign - validated in Phase 8 (CENSO-01, SRC-02, LIVE-01, LIVE-03)
- ✓ Feasibility of `wsp.registraduria.gov.co` (reCAPTCHA checkbox, possibly Enterprise-registered on Google's backend) as a live polling-place lookup source is validated end-to-end with a documented go/no-go decision - **Verdict: GO** (29/30 real 2captcha-solved attempts across 3 known cédulas succeeded; the plain non-Enterprise checkbox solve was sufficient, the `enterprise=1` escalation path exists but was never needed) - validated in Phase 9 (LIVE-02); production wiring (reachability probe fix + HTML-to-structured-fields parser) completed in Phase 11
- ✓ The data source behind every polling-place result (live / database reconstruction / local snapshot / manual) is visibly shown to the operator on the voters table, view page, and edit form via a color-coded badge, an operator can filter/triage voters currently on fallback-sourced data (table filter + a campaign-scoped dashboard widget), and the pre-existing manual re-check action remains available to every role while the paid, cache-bypassing "Actualizar datos" force-refresh is now restricted to admin/coordinator/super-admin roles - validated in Phase 10 (SRC-01, SRC-04, SRC-05), confirmed by human visual verification of all surfaces in the running app
- ✓ An hourly, unattended, bounded (50 voters/run, ~500/day cap) scheduled job automatically re-attempts live lookup for fallback-sourced voters and upgrades them to `live` when the source succeeds, recording an auditable `resolved_via='reconciliation'` reason on every real transition; the job resolves each voter's campaign from the voter record with no ambient session dependency; a voter reaches a permanent exhaustion state after 5 consecutive failed live attempts (a snapshot fallthrough counts as failure, never success); and a stuck run cannot freeze reconciliation indefinitely (`withoutOverlapping(10)` minutes) - validated in Phase 11 (RECON-01 through RECON-06)
- ✓ Schema/model layer structurally allows exactly one extra hierarchy level above coordinador (articulador → coordinador), with no relation or migration permitting articulador-of-articulador or coordinador-of-coordinador nesting, and no cap/counter/validation rule limits how many coordinadores one articulador can have - validated in Phase 12 (ARTIC-04, ARTIC-05)
- ✓ Existing coordinador-scoped surfaces (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`) correctly resolve an articulador's full transitive team via `User::teamCoordinatorUserIds()`; a dedicated `CoordinatorPolicy` denies an articulador from viewing/editing a coordinador that doesn't belong to them with a named 403 reason; cross-campaign isolation holds for the new role at both the query and direct-record level - validated in Phase 13 (AUTHZ-01, AUTHZ-02, AUTHZ-03)

### Active

- ARTIC-01, ARTIC-02, ARTIC-03 — articulador admin resource, self-service panel, coordinador behavior preservation (Phases 14-15)
- META-01 through META-06 — metadata catalog CRUD, freeform-prohibited assignment, bulk assignment, audit trail, atomic writes (Phase 16)
- FILT-01, FILT-02, FILT-03 — Filament filter/sort/export by metadata (Phase 17)

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
| National census snapshot lives in its own cédula-indexed table, isolated from campaign-scoped data | It's shared reference data (one national snapshot), not owned by any single campaign — mirrors the existing `polling_places` reference-data pattern | Implemented in Phase 6 |
| A single `PollingPlaceResolver` service owns the entire fallback cascade (campaign-DB → national snapshot → bounded live attempt), with a source-precedence no-downgrade guard | The cascade was previously duplicated across interactive/headless code paths; a live-verified result must never be silently overwritten by a staler snapshot/reconstruction result | Implemented in Phase 8, still the single point every new live adapter plugs into |
| Live sources are pluggable via a `LiveSourceAdapter` interface tried in priority order | A new live source must be addable without redesigning the resolver | Implemented in Phase 8 — proven out repeatedly post-ship when infovotantes and consultacenso were added as additional adapters (quick tasks 260726-eu3, 260731-ezk) |
| `wsp.registraduria.gov.co` feasibility spike quarantined as a non-blocking phase (9) | The deterministic snapshot/flag/resolver/reconcile core must never be gated on an unresolved captcha unknown | Implemented — Verdict: GO (29/30 real 2captcha-solved attempts) |
| Reconciliation job's automated writes use `resolved_by = null` + `resolved_via = 'reconciliation'` instead of a seeded system/bot user | Phase 7 already made `resolved_by` nullable for exactly this case; avoids a fake user row for a headless actor | Implemented in Phase 11 |
| Reconciliation job is bounded and circuit-breaker-gated with a defined per-voter exhaustion state | A prolonged live-source outage must not exhaust the paid captcha-solving budget or retry an unresolvable voter forever | Implemented in Phase 11 (RECON-04/05/06) |
| `area_coordinator_user_id` is a dedicated self-referencing FK, never a reuse of `coordinator_user_id` | Overloading the existing column would conflate two different hierarchy semantics and make the "no further nesting" invariant impossible to enforce structurally | Implemented in Phase 12 |
| `user_metadata_values` is append-only (no unique constraint on user_id+metadata_key_id) instead of a JSON column on `users` | Gives native per-assignment audit history for free (META-05) with no separate audit table, and makes future point-in-time value queries (v2 META-07/08) addable without a schema change | Implemented in Phase 12 (D-02) |
| `CoordinatorPolicy` is a new, dedicated ownership-aware Policy (view/update only) rather than extending `VoterPolicy`'s role-only pattern | It's the first policy in the codebase to compare a per-record owner (`area_coordinator_user_id`) against the acting user, and stays purely additive — every other Filament ability on `User` falls through untouched | Implemented in Phase 13 |
| Direct non-owned coordinador access returns 403 with a named reason, not a generic 403 or 404 | Matches the already-validated Phase 05.1 precedent (PERM-02: authorization denials name the specific reason) | Implemented in Phase 13 |

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

**Shipped: v1.1 Consulta de Puesto de Votación Resiliente (2026-08-10).** All 6 phases (6-11) done, all 17 v1.1 requirements validated. The resilient polling-place resolution cascade (campaign DB → national snapshot → bounded live attempt), operator provenance/triage UI, and the hourly automated reconciliation job are all live in the codebase. Since Phase 11 completed, ~50 follow-on quick tasks hardened and extended this cascade in production (additional live adapters, cost controls, a general audit-log system, a `reports_viewer` role, dashboard drill-throughs) — see `.planning/STATE.md`'s Quick Tasks Completed log. See `.planning/milestones/v1.1-ROADMAP.md` and `.planning/milestones/v1.1-REQUIREMENTS.md` for the full archived record.

## Current Milestone: v1.2 Articuladores + Metadata de Usuario

**Goal:** Articuladores organize a set of coordinadores (creating and managing them, one extra hierarchy level, no further nesting), and any superior (líder/coordinador/articulador/superadmin) can assign values from a superadmin-predefined key catalog (e.g. `biaticos`, `almuerzo`, `incentivo`) to their subordinates — filterable and sortable in Filament listings.

**Target features:**
- New `articulador` role (Spatie) above `coordinator`, able to create/manage coordinadores (no hard limit enforced) — **schema landed in Phase 12**
- New articulador→coordinador hierarchy relation (mirrors the existing `coordinator_user_id` self-referencing FK pattern); coordinadores keep working exactly as today, no coordinador→coordinador nesting — **schema landed in Phase 12**
- Superadmin-managed predefined catalog of metadata keys (new table/config), not freeform — **schema landed in Phase 12**
- Append-only `user_metadata_values` table (not a JSON column on `users` — revised during Phase 12 planning, D-02) + UI for superiors to assign values to subordinates against that catalog; every assignment is its own row, giving native per-assignment audit history for free
- Filter and sort by metadata key/value in the Filament tables for users/coordinators/leaders/articuladores

## Next Milestone Goals

Not yet defined beyond v1.2.

---
*Last updated: 2026-08-10 — Phase 13 complete (2/6 phases of v1.2)*
