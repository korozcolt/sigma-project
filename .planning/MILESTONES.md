# Milestones

## v1.2 Articuladores + Metadata de Usuario (Shipped: 2026-08-12)

**Scope:** 8 roadmap phases (12-19, includes 2 milestone-audit gap-closure phases), 29 plans, 67 tasks.
**Timeline:** 2026-08-10 → 2026-08-12 (~2 days), 87 PHP files changed (+6,516/-62 lines).
**Requirements:** 17/17 v1.2 requirements Done (ARTIC-01..05, AUTHZ-01..03, META-01..06, FILT-01..03).

**Key accomplishments:**

- New `articulador` (`area_coordinator`) hierarchy tier — a dedicated `area_coordinator_user_id` self-referencing FK (structurally independent of `coordinator_user_id`, no backend-enforced cap), a Filament admin resource for superadmin/admin_campaign management, and a full self-service panel (`AreaCoordinatorPanelProvider` at `/articulador`) mirroring the existing coordinador experience — create/edit coordinadores, own-team scoping, no OTP (Phases 12, 14, 15).
- Existing coordinador-scoped surfaces (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`, and later `CampaignStatsOverview`/`TerritorialDistributionChart`) correctly resolve an articulador's full transitive team via a centralized `User::teamCoordinatorUserIds()` helper, with a new `CoordinatorPolicy` denying cross-boundary view/edit access with a named 403 reason (Phase 13, closed end-to-end in Phase 18).
- Superadmin-managed, typed metadata-key catalog (`MetadataKeyResource`: numeric/text/date/select) with atomic, append-only, fully audited per-subordinate value assignment — individual and bulk — reachable from both the Filament admin panel and the Volt coordinador/articulador panels, gated so a `reviewer` role can no longer write metadata rows with zero authorization check (Phase 16).
- All four Filament admin tables (Usuarios, Coordinadores, Líderes, Articuladores) filter and sort by any assigned metadata value with correct numeric ordering (not alphabetical), and the four matching CSV/xlsx exports gained the same metadata columns — one shared SQL-scale `withCurrentValueSelects()`/`applyMetadataFilter()` mechanism, zero N+1 queries (Phase 17).
- A post-ship milestone audit found and closed two real gaps with dedicated phases: an unreachable export route for articuladores (`LeadersExportController`'s route excluded `area_coordinator`, fixed with a narrowly-scoped route split — Phase 18), and a genuine cross-articulador dashboard data leak where `CampaignStatsOverview`/`TerritorialDistributionChart` showed full-campaign totals instead of the articulador's own team (Phase 19).
- Phase 15's 3 manual-only UAT items (dashboard widget scoping, cédula autofill lock/unlock, sidebar navigation) were replaced with real Pest v4 Browser test coverage against a genuine Chromium session — which itself caught and fixed 2 more live bugs along the way: a 403 on Día D navigation (`DiaD::canAccess()` missing `area_coordinator`) and a dashboard-crashing `RouteNotFoundException` from `VoterResource::getUrl()` called outside the admin panel (Phase 19).

---

## v1.1 Consulta de Puesto de Votación Resiliente (Shipped: 2026-08-10)

**Scope:** 6 roadmap phases (6-11), 15 plans, 29 tasks.
**Timeline:** 2026-07-24 → 2026-07-26 (~2 days), 94 commits, 100 files changed (+229,065/-272 lines — includes the one-time 216K-row national census CSV data import).
**Requirements:** 17/17 v1.1 requirements Done (CENSO-01/02/03, SRC-01..05, LIVE-01..03, RECON-01..06).

**Key accomplishments:**

- Imported the 216K-row national census snapshot into a cédula-indexed, divipol-enriched `national_census_records` table via `census:import-national`, reporting unmatched-divipol percentage instead of aborting on bad rows (Phase 6).
- Made a voter's polling-place source a first-class, auditable attribute: `polling_place_source`/`polling_place_resolved_at` on `voters` plus an append-only `polling_place_resolutions` audit trail tolerating a nullable headless actor for automated writes (Phase 7).
- Built the single `PollingPlaceResolver` service expressing SIGMA's entire fallback cascade (campaign-DB → national snapshot → bounded live attempt) with a no-downgrade guard so a live-verified result can never be silently overwritten by staler data, fully covered by 17 Pest tests (Phase 8).
- Validated `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) as a live-source adapter end-to-end — 29/30 real 2captcha-solved attempts succeeded across 3 test cédulas — documenting a **Verdict: GO** before the system was allowed to rely on it (Phase 9).
- Shipped operator-facing provenance controls: a source badge on the voter edit form, a three-role gate on the paid force-refresh action, and a campaign-scoped fallback-voters dashboard widget — all human-verified live in the running Filament admin panel (Phase 10).
- Delivered an unattended hourly `census:reconcile-live` job that safely re-attempts live lookup for fallback-sourced voters, bounded and circuit-breaker-gated so a prolonged outage can't self-flood, with a defined terminal/exhaustion state and a lock that can't be silently frozen — covering RECON-01 through RECON-06 (Phase 11).

---

## v1.0 MVP Hardening (Shipped: 2026-07-24)

**Scope:** 8 roadmap phases (5 core + 3 urgent insertions: 02.1, 04.1, 05.1), 25 formally-planned plans across the 3 insertion phases (66 tasks), core Phases 1-5 delivered via those insertions plus incidental work and closed out by Phase 05.1's gap audit.
**Timeline:** 2026-03-25 → 2026-07-24 (~121 days), 226 commits, 342 files changed (+35,589/-1,485 lines).
**Requirements:** 30/30 v1 requirements Done (verified via Phase 05.1 goal-backward check, 22/22 must-haves, 892/892 tests green).

**Key accomplishments:**

- Renamed the product's core entity from "Votante" to "Apoyo" everywhere (admin/leader/coordinator panels, exports, public registration), replaced the hard duplicate-cédula block with an auditable suffix + admin-only ownership-reassignment action, added leader/coordinator exclusion rules and Gremio/Subcategoría classification, and shipped an admin-only CSV bulk importer with partial-success rejection reporting (Phase 02.1).
- Shipped six trustworthy Apoyo reporting surfaces as dashboard widgets with CSV export — leader/coordinator/polling-place rankings, a rejections report, a duplicates report (the one deliberate cross-campaign isolation exception), and a jurisdiction dentro/fuera report — all excluding duplicate-status Apoyos except where intentional (Phase 04.1).
- Closed a full-codebase audit's remaining gaps across campaign safety, permissions, voter operations, outreach, and reporting: authorization denials now name the specific reason (campaign/role/territory), job/queue contexts are verified campaign-safe, census validation is UI-triggerable with a visible source and next-action guidance, and Coordinator/Leader dashboards now scope to their own team instead of the whole campaign (Phase 05.1).
- Delivered two client-requested features with live human-verified checkpoints: OTP-gated leader-account creation over Hablame SMS, and a Super Admin maintenance kill switch with automatic self-bypass — the checkpoint process caught and fixed a real self-lockout bug before sign-off (Phase 05.1).
- Hardened Day D execution: a DB-level unique constraint plus a defined conflict rule now prevent duplicate/conflicting vote records, participation stats break down per-territory instead of campaign-only, and `FinalizeElectionEvent` runs on the real queue with structured logging and a dedicated duplicate-prevention test instead of `dispatchSync()` silently swallowing failures (Phase 05.1).

---
