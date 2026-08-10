# Milestones

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
