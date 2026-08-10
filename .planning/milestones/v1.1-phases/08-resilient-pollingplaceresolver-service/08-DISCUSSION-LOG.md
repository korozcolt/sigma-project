# Phase 8: Resilient PollingPlaceResolver Service - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-24
**Phase:** 08-resilient-pollingplaceresolver-service
**Areas discussed:** Live-first vs. cost-first ordering, Automated "never blocks" give-up behavior, Operator-visible behavior change scope, Audit-row write granularity

---

## Live-first vs. cost-first ordering

| Option | Description | Selected |
|--------|-------------|----------|
| Cost-last: cache → DB reconstruction → national snapshot → live | Matches today's existing behavior; avoids latency from a guaranteed-dead live attempt | |
| Live-first: live → snapshot/DB → cache | Matches literal milestone wording | |

**User's choice (free text):** "el problema es que debemos buscar informacion completamente actualizada, y para esto debemos confiar en que el punto que consultamos en la registraduria lo está, entonces, por eso se aplica cache para que el costo de esta consulta baje un poco en caso de que ya se haya consultado una cedula anteriormente, el costo ya sabemos que es mayor pero tenemos la fiabilidad de que la información va coorecta"

**Follow-up clarification:** Confirmed exact order as **Cache (if exists) → Live → DB reconstruction/national snapshot** for the interactive path — cache short-circuits repeat lookups for cost, but a fresh cédula goes to live first for reliability. User confirmed the existing 30-day cache TTL is sufficient, no additional expiration logic needed.

**Notes:** User explicitly prioritizes data reliability/freshness over cost for the interactive path — a reversal from the existing cost-last cascade, but preserves cache as a cost-saver for repeats.

---

### Automated path tier order

| Option | Description | Selected |
|--------|-------------|----------|
| Live-first: live → snapshot | Matches reconciliation's purpose (upgrading stale voters) | ✓ |
| Same cost-last order as interactive | Consistent ordering everywhere, but defeats reconciliation's purpose | |

**User's choice:** Live-first: live → snapshot (Recommended)

---

### Reachability probe

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, probe before spending any captcha cost | Avoids paying/waiting on guaranteed-dead domains | ✓ |
| No, just attempt the real lookup and let it fail/timeout | Simpler, but wastes cost/time on dead domains | |

**User's choice:** Yes, probe before spending any captcha cost (Recommended)

---

### Live kill switch

| Option | Description | Selected |
|--------|-------------|----------|
| Yes — config flag to fully disable the live tier for now | Zero cost/latency from dead-domain attempts until Phase 9 ships a working adapter | ✓ |
| No — let the reachability probe alone handle it | One less moving part | |

**User's choice:** Yes — a config flag to fully disable the live tier for now (Recommended)

---

## Automated "never blocks" give-up behavior

### Poll attempts

| Option | Description | Selected |
|--------|-------------|----------|
| 3–5 polls with short backoff | Catches a slightly-slow live response without hanging | ✓ |
| 1 poll, immediate give-up if not "done" | Fastest fail, risks under-utilizing a working live source | |

**User's choice:** 3–5 polls with short backoff (Recommended)

### Waiting captcha

| Option | Description | Selected |
|--------|-------------|----------|
| Treat as "not automatable right now" — fall back immediately | Matches Pitfall 5's guidance exactly | ✓ |
| Keep polling until overall timeout expires | Risks holding a request/job slot open for a human-driven flow | |

**User's choice:** Treat as "not automatable right now" — fall back to snapshot/DB immediately (Recommended)

### Max wait time

| Option | Description | Selected |
|--------|-------------|----------|
| A few seconds total (under 10s) | Matches LIVE-03's "return promptly" requirement | ✓ |
| Longer window (30s+) | Maximizes live-source success rate but feels slow | |

**User's choice:** A few seconds total (e.g. under 10s) (Recommended)

---

## Operator-visible behavior change scope

### UI behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Keep it pixel-identical for now | This phase is internal plumbing, no user-facing surface per roadmap | ✓ |
| Update notification wording now to reflect the new tier | Adds a small user-facing change ahead of Phase 10 | |

**User's choice:** Keep it pixel-identical for now (Recommended)

### Force refresh vs. no-downgrade guard

| Option | Description | Selected |
|--------|-------------|----------|
| Yes — force refresh always goes straight to live, no guard applied | It's an explicit operator action, not automatic (Pitfall 10 targets automatic downgrades) | ✓ |
| Force refresh should still respect precedence | More conservative, but could frustrate an operator's deliberate re-verify request | |

**User's choice:** Yes — force refresh always goes straight to live, no guard applied (Recommended)

---

## Audit-row write granularity

### Write when

| Option | Description | Selected |
|--------|-------------|----------|
| Only when there's a real change of source or place | Aligns with the table's purpose: record transitions, not confirmations | ✓ |
| Always, on every resolve() regardless of change | More complete audit, but high row volume from repeat no-op lookups | |

**User's choice:** Solo cuando hay un cambio real de fuente o de lugar (Recommended)

### Re-verification with no change

| Option | Description | Selected |
|--------|-------------|----------|
| No — no new row without a real change | `polling_place_resolved_at` on the voter already reflects "last confirmed" | ✓ |
| Yes — record the re-verification as evidence it was attempted | Useful for "when did we last try live" from history alone | |

**User's choice:** No — sin cambio real, no hay fila nueva (Recommended)

---

## Claude's Discretion

- Exact `PollingPlaceResolution` value object shape beyond `source`/`pollingPlaceId`/`tableNumber`/`resolvedAt`
- Exact backoff timing between the 3–5 automated polls (within the ~10s total ceiling)
- Reachability probe implementation (DNS check vs. HTTP HEAD vs. reused HTTP client with short timeout)
- Exact refactor mechanics of `HasRegistraduriaPolling` (how much logic moves vs. delegates), as long as operator-visible behavior stays identical and the cascade is expressed exactly once

## Deferred Ideas

None — discussion stayed within phase scope. Reconciliation job mechanics (Phase 11), the wsp.registraduria.gov.co feasibility spike (Phase 9), and any operator-visible UI change (Phase 10) were acknowledged but not discussed here.
