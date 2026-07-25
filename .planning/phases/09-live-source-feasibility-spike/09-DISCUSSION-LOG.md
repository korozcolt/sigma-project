# Phase 9: Live-Source Feasibility Spike - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-25
**Phase:** 9-live-source-feasibility-spike
**Areas discussed:** Spike code disposition, Test cédulas & captcha budget, Go/no-go scope boundary, Decision documentation & timebox

---

## Spike Code Disposition

| Option | Description | Selected |
|--------|-------------|----------|
| Modify app.py in place | Fastest path to an answer; change target/sitekey/captcha params directly; cleanup later if it works | ✓ |
| Build a second adapter now | New Python service + new Laravel LiveSourceAdapter from the start, production-ready immediately | |
| You decide | Claude/planner picks based on speed to an answer | |

**User's choice:** Modify app.py in place
**Notes:** Matches the phase's "time-boxed, non-blocking spike" framing.

| Option | Description | Selected |
|--------|-------------|----------|
| Local dev machine | Faster iteration, no deploy step; local IP hits Registraduría instead of production IP | ✓ |
| korserver Dokploy container | Validates from production IP/env, but requires a redeploy first (container runs stale code per STATE.md) | |
| You decide | Claude picks based on speed to a conclusive answer | |

**User's choice:** Local dev machine
**Notes:** None further.

---

## Test Cédulas & Captcha Budget

| Option | Description | Selected |
|--------|-------------|----------|
| Real campaign Apoyo cédulas | Pull 5-10 real cédulas from existing voter data | |
| I'll provide specific cédulas | User supplies known test numbers directly | ✓ |
| You decide | Claude picks a reasonable sample at execution time | |

**User's choice:** I'll provide specific cédulas
**Notes:** Follow-up asked whether to provide now vs. at execution — user chose to provide later, at execution time. Executor must pause and ask before running any live submission.

| Option | Description | Selected |
|--------|-------------|----------|
| ~20-30 solves | Enough for a real signal across the 0.3→0.9 score ladder, still trivially cheap | ✓ |
| Just enough for 1 success/clear failure | Fastest, cheapest, smaller sample | |
| You decide | Claude sizes the budget during planning | |

**User's choice:** ~20-30 solves
**Notes:** None further.

---

## Go/No-Go Scope Boundary

| Option | Description | Selected |
|--------|-------------|----------|
| Stop at go/no-go recommendation | Phase 9 delivers spike + documented recommendation only; wiring is separate follow-up | ✓ |
| Wire it in if it succeeds | Immediately update RegistraduriaService/app.py and flip the kill switch in this same phase | |

**User's choice:** Stop at go/no-go recommendation
**Notes:** Matches ROADMAP's literal wording; keeps the spike honestly time-boxed.

---

## Decision Documentation & Timebox

| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated SPIKE-RESULTS.md | Standalone doc with outcome taxonomy breakdown, sitekey/action findings, final recommendation | ✓ |
| Phase SUMMARY.md only | Folded into the standard end-of-phase summary, no extra file | |

**User's choice:** Dedicated SPIKE-RESULTS.md
**Notes:** Gates Phase 11's scoping decisions — wanted as a durable standalone reference.

| Option | Description | Selected |
|--------|-------------|----------|
| Budget exhausted = stop | Stop once the ~20-30 solve budget is used; inconclusive counts as documented "no-go for now" | ✓ |
| Fixed wall-clock limit | Cap at a fixed elapsed time regardless of budget used | |
| You decide | Planner sets a practical stopping condition | |

**User's choice:** Budget exhausted = stop
**Notes:** Ties the timebox directly to the budget decision.

---

## Claude's Discretion

- Sitekey/action/data-s extraction mechanics (manual devtools vs. scripted).
- Exact structure of SPIKE-RESULTS.md beyond required content.
- Whether to keep old dead-domain v2 logic commented out in app.py vs. relying on git history.

## Deferred Ideas

- Wiring a working wsp adapter into production (RegistraduriaService, REGISTRADURIA_LIVE_ENABLED) — deferred regardless of spike outcome.
- Running the spike from the korserver Dokploy container — deferred; local dev chosen instead. Revisit only if local results are ambiguous and IP reputation is suspected.
