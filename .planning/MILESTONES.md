# Milestones

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
