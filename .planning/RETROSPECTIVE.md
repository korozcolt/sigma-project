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

## Milestone: v1.1 — Consulta de Puesto de Votación Resiliente

**Shipped:** 2026-08-10 (phases completed 2026-07-26; formal milestone close delayed ~2 weeks behind a large volume of post-ship production work)
**Phases:** 6 (Phases 6-11) | **Plans:** 15 | **Timeline:** 2026-07-24 → 2026-07-26 (~2 days) for the roadmap itself

### What Was Built
- A national census snapshot fallback tier: the 216K-row snapshot imported into a cédula-indexed, divipol-enriched reference table (Phase 6), a first-class persisted+auditable `polling_place_source` on every voter (Phase 7), and a single `PollingPlaceResolver` service unifying the campaign-DB → snapshot → live cascade behind a no-downgrade guard (Phase 8).
- A validated new live source: `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) proven viable end-to-end via a quarantined, non-blocking spike (29/30 real 2captcha solves, Verdict: GO — Phase 9).
- Operator-facing provenance controls (source badge, role-gated force-refresh, fallback-voters dashboard widget, all human-verified live — Phase 10) and an unattended hourly reconciliation job that upgrades fallback-sourced voters when the live source recovers, bounded and exhaustion-safe (Phase 11).

### What Worked
- **Quarantining the risky unknown (Phase 9's captcha spike) as a standalone non-blocking phase** meant the deterministic core (snapshot/flag/resolver/reconcile) never depended on an outcome nobody could guarantee in advance — it shipped regardless of the spike's result, and the spike came back GO.
- **The `LiveSourceAdapter` interface paid for itself almost immediately** — designed in Phase 8 for one adapter, it absorbed two more real live sources (infovotantes, consultacenso) as post-ship quick tasks with zero resolver redesign, exactly as intended.
- **The no-downgrade guard (source precedence enforced at the persistence layer, not just at read time)** prevented an entire class of "stale data silently wins" bugs across both the interactive and headless/reconciliation paths sharing one resolver.

### What Was Inefficient
- **Formal milestone closure lagged ~2 weeks behind actual completion.** Phases 6-11 finished 2026-07-26 and STATE.md said "Ready for `/gsd:complete-milestone`" the whole time, but ~50 quick tasks landed against production in the gap before anyone ran it — MILESTONES.md/PROJECT.md accuracy drifted the same way v1.0's roadmap status drifted (a repeat of a v1.0 "Key Lesson"). Close milestones promptly once phases finish, even if maintenance work is still flowing in.
- **A git-worktree/`gsd-tools` root-resolution bug recurred 6+ times across Phases 6-11**, silently redirecting CLI state writes to the wrong `.planning/` or requiring hand-editing STATE.md/ROADMAP.md in the worktree every time. Never fixed at the source despite being diagnosed on the first occurrence (Phase 6) — logged as a recurring workaround instead of a one-time fix.
- **Worktrees were provisioned stale (missing `vendor/`, `.env`, sometimes `node_modules`/`public/build`) on the same recurring basis**, adding a manual recovery ritual (fast-forward + composer install + env copy) to nearly every parallel-wave plan.
- **Three `checkpoint:human-verify` sign-offs from post-ship quick tasks are still outstanding** at milestone close (260801-hvd, 260804-i5f, 260804-jbc) — code-complete and test-covered, but never given the real-browser confirmation the project's own standing preference requires before considering UI-facing fixes trustworthy.

### Patterns Established
- Quarantine a genuinely unknown external dependency (captcha/third-party feasibility) into its own non-blocking phase rather than a blocking prerequisite of the phase that needs it.
- Design integration points as pluggable interfaces (`LiveSourceAdapter`) even for a single known implementation when "more sources later" is a stated future need — the second and third adapters proved the interface-first bet correct.
- Enforce data-freshness/precedence guards (no-downgrade) at the persistence layer shared by every write path, not per-caller.

### Key Lessons
1. Run `/gsd:complete-milestone` promptly once phases finish — letting real, uncounted production work accumulate for weeks before formal close (repeat of v1.0's Key Lesson #1) makes the eventual archival/review step disproportionately large and risks losing context on what shipped when.
2. A recurring tooling bug diagnosed once but "worked around" every subsequent time (the `gsd-tools` worktree root-resolution issue, 6+ occurrences) costs more in aggregate than fixing it at the source would have — treat the second occurrence of the same workaround as the trigger to fix it, not the tenth.
3. Pluggable-adapter design for external integrations pays for itself fast when "more sources" is even loosely anticipated — verified twice here (infovotantes, consultacenso) within days of the interface shipping.
4. Outstanding human-verification checkpoints should block milestone closure (or be explicitly accepted as a known gap), not silently roll forward past it — three carried into this closure without an explicit accept/reject decision.

### Cost Observations
- Model mix and per-session cost were not tracked for this milestone (same gap as v1.0).
- Test suite reached 78+ targeted-suite passes on the final touched files at milestone close; full-suite runs remained subject to the known `CampaignContext` test-pollution flake, tracked as accepted pre-existing risk rather than blocking.

---

## Milestone: v1.3 — Visualización de Datos MonoCharts

**Shipped:** 2026-08-21
**Phases:** 5 (Phases 20-24) | **Plans:** 21 | **Timeline:** 2026-08-20 → 2026-08-21 (~2 days)

### What Was Built
- A React+Recharts+Motion island mechanism mounted via a dedicated Vite entry and an Alpine `wire:ignore` bridge (mount-once/update-via-`root.render()`/unmount-on-destroy-or-navigate), proven safe against `wire:poll` ticks and Livewire SPA navigation on all 5 panels (Phase 20).
- All 3 pre-existing Chart.js `ChartWidget`s and all 3 embedded sparklines migrated onto the new pipeline with zero `getData()` query changes, confirmed byte-identical via `git diff` (Phase 21).
- 5 new table-stakes visualizations previously entirely missing: a 12-state VoterStatus donut, a coordinator-team stacked-bar, call-contactability and message-delivery funnels, and a SCALE-survey gauge+histogram (Phase 22).
- 4 curated visualizations requiring real modeling decisions rather than component swaps: a happy-path Voter lifecycle funnel, a top-8+"Otros" ValidationHistory Sankey, a Departamento→Municipio→Barrio drill-down treemap, and a caller×hour contact-rate heatmap, plus a rejection-reasons stacked-area (Phase 23).
- A cached, 30s-polling, campaign+event-scoped live Día D voting line chart that never re-runs its aggregation query on every tick (Phase 24).
- By milestone end: 12 chart kinds live behind a single `ChartRouter.jsx` dispatch contract, Chart.js fully retired from the codebase.

### What Worked
- **Building the shared JS chart-kind library as its own plan before any PHP widget plan, repeated in every phase (20-01, 21-01, 22-01, 23-01).** Every later PHP-only widget plan in that phase then needed zero further JS changes — the kind-registration work was front-loaded and paid for itself across 2-5 downstream plans each time.
- **Migrating pre-existing charts with a hard "zero `getData()` changes" constraint (Phase 21).** Kept the rendering-layer swap cleanly separable from any data-shaping risk, and made the migration's correctness independently verifiable via a straight `git diff` on the query methods.
- **Retroactive verification instead of re-execution when a gap is a missing artifact, not missing work.** The milestone audit found Phase 21 had shipped correctly (integration-checker confirmed the code) but never produced a `VERIFICATION.md`. Closing it meant spawning `gsd-verifier` retroactively against the already-shipped code — not re-running any of the 7 plans.
- **A live browser checkpoint catching a real bug the plan's automated tests couldn't have caught (Phase 21-07).** A hardcoded-light-theme bug only surfaced because a human looked at the app in dark mode; closed before the milestone's audit could have found it as a shipped defect.

### What Was Inefficient
- **Worktree staleness recurred on nearly every plan across Phases 20-21** — sessions started 5+ commits behind `main`, missing the phase's own planning corpus, `.env`, `vendor/`, `node_modules/`, and `public/build/`. This is the same class of recurring tooling issue flagged as unfixed in both the v1.0 and v1.1 retrospectives — now observed in a third consecutive milestone without a source fix.
- **A plan's first-proposed fix for the Phase 23 funnel label-overflow bug (a `margin.right` adjustment) did not actually work** and required a second, more invasive fix (a custom unclamped `LabelList` renderer) discovered only by checking in a real browser — the initial fix was accepted on code-review plausibility rather than visually confirmed before being marked done.
- **`21-01-SUMMARY.md`'s frontmatter claims requirements-completed that weren't actually closed until a later plan** — a documentation-only inconsistency, but one that directly caused the milestone audit's first pass to need a second, retroactive-verification round to resolve.

### Patterns Established
- Shared-library-first sequencing within a visualization phase: one plan builds every new chart-kind component + router registration + empty-state copy the phase needs, before any PHP widget plan consumes it.
- "Zero query changes" as an explicit, checkable constraint when migrating a rendering layer — makes the migration's blast radius independently verifiable, not just asserted.
- Retroactive `gsd-verifier` runs to close a missing-artifact gap without re-executing already-shipped, already-integration-checked work.

### Key Lessons
1. The recurring worktree-staleness/tooling-root-resolution issue has now cost time across three consecutive milestones (v1.0, v1.1, v1.3) via the same hand-workaround every time — this has crossed from "log it" to "fix it at the source" territory per v1.1's own Key Lesson #2, which was itself not acted on.
2. A visually-oriented bug fix (label overflow, theme color, layout clipping) should not be marked done on code-review plausibility alone — verify it rendered correctly in a real browser before closing the plan, not just before closing the phase.
3. A phase's SUMMARY.md frontmatter (`requirements-completed`) must reflect the plan's actual completion state, not an intended end-state — a wrong frontmatter claim on one early plan (21-01) propagated into a milestone-audit false negative two phases and one full milestone-close cycle later.

### Cost Observations
- Model mix and per-session cost were not tracked for this milestone (same gap as v1.0/v1.1).
- 120 commits, 141 files changed, +16,260/-184 lines across the 2-day roadmap execution window.
- Full v1.3 test suite: 6 dedicated Browser tests for the migrated widgets, plus per-phase Feature/Unit/Browser coverage for every new chart widget — all green at milestone audit time (17/17 requirements satisfied, 0 broken E2E flows).

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Active Days | Phases | Key Change |
|-----------|-------------|--------|------------|
| v1.0 | 9 | 8 (5 core + 3 inserted) | Introduced decimal phase insertion + audit-then-close pattern for stale roadmap reconciliation |
| v1.1 | ~2 (roadmap execution) | 6 | Pluggable live-adapter interface + quarantined non-blocking feasibility spike pattern |
| v1.3 | ~2 (roadmap execution) | 5 | Shared-library-first sequencing per visualization phase + retroactive `gsd-verifier` to close missing-artifact (not missing-work) audit gaps |

### Cumulative Quality

| Milestone | Tests | Coverage | Zero-Dep Additions |
|-----------|-------|----------|---------------------|
| v1.0 | 892 | Not separately tracked | 0 (no new dependencies added) |
| v1.1 | 78+ (targeted, final touched files) | Not separately tracked | 0 (no new Composer dependencies added) |
| v1.3 | 17/17 requirements satisfied, 0 broken E2E flows (milestone audit) | Every new/migrated chart widget has a dedicated Pest 4 Browser test | New npm deps: React 19, Recharts 3, Motion (no new Composer dependencies) |

### Top Lessons (Verified Across Milestones)

1. Audit-then-close beats blind re-execution when roadmap status is stale — verified once in v1.0 (Phase 05.1), watch for repeat need in future milestones.
2. Live human-verify checkpoints on lockout/irreversible-risk features catch real bugs — verified once in v1.0 (kill switch checkpoint); v1.3 reinforced this for visual/theme bugs specifically (Phase 21-07's dark-mode fix).
3. Roadmap/milestone bookkeeping drifts from reality when incidental/urgent work lands outside the plan and isn't reconciled promptly — now verified **twice** (v1.0's multi-month drift, v1.1's ~2-week post-ship quick-task backlog). Close milestones and reconcile status as work lands, not in a single large catch-up pass.
4. Pluggable-adapter design for external/third-party integrations pays for itself quickly once a second implementation is needed — verified once in v1.1 (`LiveSourceAdapter`, two more adapters added within days).
5. The `gsd-tools` worktree/root-resolution and worktree-staleness issue has now recurred across **three** consecutive milestones (v1.0, v1.1, v1.3) as a hand-workaround, never root-caused despite being flagged as fixable after the v1.1 milestone — the strongest candidate for a source-level fix before the next milestone starts.
6. Visually-oriented bug fixes need a real-browser check before being marked done, not just a code-review-plausible patch — verified once in v1.3 (Phase 23's funnel label-overflow fix needed a second, more invasive attempt after the first "looked right" in code).
