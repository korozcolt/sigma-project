# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — MVP Hardening

**Shipped:** 2026-07-24
**Phases:** 8 (5 core + 3 urgent insertions: 02.1, 04.1, 05.1) | **Plans:** 25 | **Active days:** 9 (across ~4 months, 2026-03-25 → 2026-07-24; sessions not separately tracked)

### What Was Built
- Full "Votante" → "Apoyo" entity rename across every panel and export, plus duplicate-cédula handling: an auditable suffix instead of a hard block, leader/coordinator exclusion rules, Gremio/Subcategoría classification, and an admin-only CSV bulk importer with partial-success rejection reporting (Phase 02.1).
- Six trustworthy Apoyo reporting widgets with CSV export — leader/coordinator/polling-place rankings, rejections, duplicates (the one deliberate cross-campaign isolation exception), and jurisdiction dentro/fuera (Phase 04.1).
- Closure of every gap found by a full 5-agent codebase audit of Phases 1-5: reason-specific authorization denials, job/queue-context campaign safety, UI-wired census validation with next-action guidance, ownership-scoped Coordinator/Leader dashboards, follow-up backlog + drill-through, DB-level Day D duplicate-vote prevention, and a real-queue `FinalizeElectionEvent` with structured logging (Phase 05.1).
- Two client-requested features with live human-verify checkpoints: OTP-gated leader-account creation via Hablame SMS, and a Super Admin maintenance kill switch with automatic self-bypass (Phase 05.1).

### What Worked
- **Audit-then-close instead of blind re-execution.** Phases 1-5's roadmap status had gone stale (marked "Not started" despite ~55-70% real coverage from incidental work + inserted phases). A parallel 5-agent audit (one per phase) established real per-requirement status before deciding what to build, so Phase 05.1 closed exactly the ~16 genuine gaps instead of re-executing four phases from scratch.
- **RED-test-first "Wave 0" scaffolding.** Both 02.1 and 04.1 started with a wave that wrote every expected-behavior test up front, so later implementation waves built against a fixed, already-agreed target instead of discovering requirements mid-implementation.
- **Live human-verify checkpoints on irreversible/high-risk features.** The kill switch checkpoint caught a real bug (activating Super Admin locked themselves out of their own session) before sign-off — direct evidence the checkpoint step is worth its friction for anything with lockout or evidence-integrity risk.
- **Decimal phase insertion for urgent client requests.** 02.1, 04.1, and 05.1 all landed as numbered insertions with a locked CONTEXT.md of decisions, without needing to renumber or re-plan the core roadmap.

### What Was Inefficient
- Roadmap/requirements status drifted from reality for months before the 2026-07-23 audit caught it — incidental work (production bug fixes, client-requested features) wasn't reflected back into ROADMAP.md/REQUIREMENTS.md as it landed, so the eventual reconciliation required a full multi-agent audit instead of an incremental update.
- The "reasignar dueño de duplicado" action shipped a narrower flag-clearing behavior in 02.1-08/09, then had to be rewritten in 02.1-11 after UAT confirmed the client's literal requirement ("reasignar la propiedad de la cédula") meant a real `registered_by` ownership transfer — a closer first read of the original requirement wording would have avoided the rework.
- A known test-pollution risk (`CampaignContext::setCampaignId()` not reset in `afterEach()` across several pre-existing test files) was identified during Phase 05.1 but left as logged technical debt rather than fixed inline.

### Patterns Established
- Decimal phase insertion (X.1, X.2) for urgent/client-driven work, always gated on a locked `*-CONTEXT.md` of numbered decisions (D-01, D-02, ...) before planning begins.
- Wave 0 = RED test scaffolding for all planned behavior; later waves implement against it rather than writing tests alongside code.
- Full multi-agent audit before closing a milestone whose roadmap status is suspected stale, rather than trusting checkboxes.
- Live human-verify checkpoints reserved specifically for features with self-lockout or irreversible-action risk, not applied blanket to every plan.

### Key Lessons
1. Don't trust roadmap phase status once significant incidental/urgent work has landed outside the plan — audit before deciding whether to re-plan or close gaps.
2. Re-check a client's literal requirement wording against the shipped implementation before marking a feature done; a narrower technical reading can silently diverge from what was actually asked.
3. Checkpoint-based human verification earns its cost specifically on features with lockout or evidence-integrity risk (auth kill switches, Day D vote evidence) — it found a real bug here.
4. Campaign isolation should be the default assumption for every new report/export; exceptions (like the duplicates report) must be explicitly and narrowly scoped in the CONTEXT doc, never incidental.

### Cost Observations
- Model mix and per-session cost were not tracked for this milestone.
- Test suite grew to 892/892 passing by milestone close, used as the primary trust signal for the 05.1 regression gate rather than cost/token metrics.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Active Days | Phases | Key Change |
|-----------|-------------|--------|------------|
| v1.0 | 9 | 8 (5 core + 3 inserted) | Introduced decimal phase insertion + audit-then-close pattern for stale roadmap reconciliation |

### Cumulative Quality

| Milestone | Tests | Coverage | Zero-Dep Additions |
|-----------|-------|----------|---------------------|
| v1.0 | 892 | Not separately tracked | 0 (no new dependencies added) |

### Top Lessons (Verified Across Milestones)

1. Audit-then-close beats blind re-execution when roadmap status is stale — verified once in v1.0 (Phase 05.1), watch for repeat need in future milestones.
2. Live human-verify checkpoints on lockout/irreversible-risk features catch real bugs — verified once in v1.0 (kill switch checkpoint).
