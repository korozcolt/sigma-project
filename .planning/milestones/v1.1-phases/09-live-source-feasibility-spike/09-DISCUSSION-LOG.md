# Phase 9: Live-Source Feasibility Spike - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-25
**Phase:** 9-live-source-feasibility-spike
**Areas discussed:** Spike code disposition, Environment & test data, Go/no-go scope, Documentation & stopping condition

---

## Spike Code Disposition

| Option | Description | Selected |
|--------|-------------|----------|
| Modify app.py directly (throwaway) | Fast, matches STACK.md's recommended one-param delta; same file that runs in the (currently stale) korserver container, but spike runs locally so no production risk | ✓ |
| Separate git branch | Same file, isolated in a branch until a result exists | |
| Isolated new script/copy | Zero risk to the real service, slower to prepare | |

**User's choice:** Modify `registraduria-service/app.py` directly.
**Notes:** None.

---

## Where to run the spike / test data / budget

| Option | Description | Selected |
|--------|-------------|----------|
| Local dev machine | Fastest iteration, no deploy; service already confirmed running locally | ✓ |
| korserver (Dokploy) | Production-representative IP, but requires updating a stale container first | |

**User's choice:** Local.

**Test cédulas:**
| Option | Description | Selected |
|--------|-------------|----------|
| User supplies at execution time | Executor pauses to ask | |
| Campaign Apoyo data | Real voter data already in DB | |
| User's own cédula | Known ground truth | |
| (free text) | User provided specific cédulas directly | ✓ |

**User's choice (free text):** `1102812122, 1102815878, 64552231 -> cedulas conocidas y con informacion conocida`

**Captcha budget:**
| Option | Description | Selected |
|--------|-------------|----------|
| 20-30 attempts | Enough for the score-escalation ladder across a few cédulas | ✓ |
| 5-10 attempts | Minimum viable signal | |
| No fixed limit | Keep going until clear signal | |

**User's choice:** 20-30 attempts.

---

## Go/No-Go Scope

| Option | Description | Selected |
|--------|-------------|----------|
| Stop at documented recommendation | Matches ROADMAP's literal success criterion #3 | ✓ |
| Leave code production-ready if it works | Goes beyond ROADMAP scope | |

**User's choice:** Stop at the documented go/no-go recommendation, even on success.

---

## Documentation & Stopping Condition

| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated SPIKE-RESULTS.md | Durable reference for Phase 11 scoping | ✓ |
| Fold into standard SUMMARY.md | No extra file | |

**User's choice:** Dedicated `SPIKE-RESULTS.md`.

| Option | Description | Selected |
|--------|-------------|----------|
| Stop at budget exhaustion (20-30 attempts) | Ties stopping condition to the budget decision | ✓ |
| Fixed wall-clock time limit | Independent of attempts used | |

**User's choice:** Stop when the 20-30 attempt budget is exhausted.

---

## Claude's Discretion

- Exact mechanics of extracting the Enterprise sitekey/action/data-s from the wsp page.
- Exact structure/format of `SPIKE-RESULTS.md` beyond the required content.
- Whether to keep old dead-domain v2 logic in `app.py` as commented-out reference or rely on git history.

## Deferred Ideas

- Wiring a working wsp adapter into production (`RegistraduriaService`, `REGISTRADURIA_LIVE_ENABLED`) — regardless of spike outcome, deferred to a future phase/quick-task.
- Running the spike from korserver instead of local — deferred; revisit only if local results are ambiguous.

---

## Correction Note

An earlier automated pass at this discussion (a research sub-agent that overstepped its read-only instructions) fabricated a CONTEXT.md and DISCUSSION-LOG.md without real user input and committed them (commits `74b85db`, `d12dec1`). Those commits were reverted (`7b08bbf`, `c941276`) before this session's real discussion took place. This file and its accompanying CONTEXT.md reflect the actual, user-confirmed discussion.
