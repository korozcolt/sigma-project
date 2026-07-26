# Phase 11: Scheduled Reconciliation Job - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-26
**Phase:** 11-scheduled-reconciliation-job
**Areas discussed:** wsp wiring scope, system actor for audit (RECON-03), schedule/limits (RECON-04), terminal state (RECON-05), lock duration (RECON-06)

---

## wsp Live-Source Wiring

| Option | Description | Selected |
|--------|-------------|----------|
| Include wiring within Phase 11 | Fix isReachable() + build request/response flow so the job is functional day one | ✓ |
| Separate quick-task first | Resolve wiring independently before planning Phase 11 | |
| Leave as scaffold | Build the job knowing live tier is a no-op for now | |

**User's choice:** Include within Phase 11.

**Follow-up after discovering the HTML-parsing gap (found mid-discussion, not initially known):**

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, still part of Phase 11 | Parser is a necessary part of making wiring actually work | ✓ |
| Split into a prior separate task | Scope it out to keep this phase focused on the job itself | |

**User's choice:** Still part of Phase 11, even with the larger discovered scope.

---

## System Actor (RECON-03)

| Option | Description | Selected |
|--------|-------------|----------|
| resolved_by=null + resolved_via='reconciliation' | Zero new migrations, already-supported schema | ✓ |
| Seeded system/bot user | New migration/seeder, explicit "who" in resolved_by | |

**User's choice:** Nullable resolved_by + resolved_via='reconciliation'.

---

## Schedule & Limits (RECON-04)

**Frequency:**
| Option | Description | Selected |
|--------|-------------|----------|
| Hourly | Matches prior research recommendation | ✓ |
| Every 15-30 min | Faster reconciliation, more runs/day | |
| Once daily | Minimal spend, slower reconciliation | |

**User's choice:** Hourly.

**Batch size / budget:**
| Option | Description | Selected |
|--------|-------------|----------|
| 50 voters/run, ~500/day cap | Predictable, cheap ceiling | ✓ |
| No fixed cap, only circuit breaker | Process all fallback voters each run | |
| Other specific number | User-provided alternative | |

**User's choice:** 50 voters/run, ~500/day cap.

---

## Terminal / Exhaustion State (RECON-05)

**Retry count:**
| Option | Description | Selected |
|--------|-------------|----------|
| 5 consecutive failed attempts | Balanced margin for outages | ✓ |
| 10 attempts | More outage tolerance | |
| Time-based instead of count-based | e.g., 30 days elapsed | |

**User's choice:** 5 consecutive failed attempts.

**Representation:**
| Option | Description | Selected |
|--------|-------------|----------|
| New columns: reconciliation_attempts + reconciliation_exhausted_at | Explicit counter + timestamp on voters | ✓ |
| Reuse polling_place_resolutions rows to count | No new columns, derive from audit history | |

**User's choice:** New columns on voters.

---

## Lock Duration (RECON-06)

| Option | Description | Selected |
|--------|-------------|----------|
| 10 minutes | Ample margin above realistic run time | ✓ |
| 30 minutes | Extra margin for slow live source | |
| 5 minutes | Aggressive, faster stuck-lock release | |

**User's choice:** 10 minutes.

---

## Claude's Discretion

- Exact circuit-breaker mechanics for the per-run early-exit on confirmed live-source outage.
- Whether the wsp HTML parser lives in the Python service or PHP service.
- Exact query shape for selecting eligible-for-reconciliation voters.
- Command/job naming conventions.

## Deferred Ideas

None — the only scope question (wsp wiring) was resolved as in-scope, not deferred.
