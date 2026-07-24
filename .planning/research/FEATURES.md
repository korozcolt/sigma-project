# Feature Research

**Domain:** Resilient external (government) data lookup with local fallback + background reconciliation — polling-place resolution for SIGMA voters ("Apoyos")
**Researched:** 2026-07-24
**Confidence:** HIGH (grounded in existing codebase patterns + verified industry resilience patterns; LOW only on live `wsp.registraduria.gov.co` viability, a separate feasibility spike)

## Context Anchor (read this first)

This feature is **not greenfield**. The existing `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` already documents and runs a 3-layer lookup:

1. **Layer 1** — Redis cache (`registraduria:cedula:{cedula}`, 30-day TTL)
2. **Layer 2** — DB reconstruction from `census_records` + `polling_places` (permanent, zero cost)
3. **Layer 3** — 2captcha request to the (now-dead) Registraduría domains — last resort, costs money

The codebase already *has* a fallback cascade. What this milestone adds is: (a) a **national** census snapshot as the Layer-2 source (216K-row CSV, cédula → divipol + mesa, currently unwired), (b) an **explicit data-source flag** persisted on the voter so results stop being anonymous, and (c) a **scheduled reconciliation job** that upgrades snapshot-sourced records once the live source returns. The industry patterns below (cache-aside + stale fallback + stale-while-revalidate) *are* this cascade — SIGMA is conceptually 60% there; the gap is **provenance and reconciliation**, not the fallback itself.

## Feature Landscape

### Table Stakes (Users Expect These)

Features that make the dual-source result *trustworthy*. Missing any means an operator can't tell real data from a guess — violating SIGMA's Core Value ("trustworthy, campaign-safe data and clear operational traceability").

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Explicit source flag persisted on the result** (`live` / `local-snapshot` / `manual`) | On Day D an operator must know if a polling place is authoritative or a best-effort snapshot. Anonymous results are untrustworthy. | LOW | New nullable column(s) on `voters` (e.g. `polling_place_source` enum + `polling_place_resolved_at`). Mirrors the existing `census_validated_at` timestamp convention already on the voters table. |
| **Visible source badge on the voter record** | Users read the record, not the DB. A colored badge (green=live, amber=snapshot, grey=manual) is the cheapest provenance signal. | LOW | Filament `TextColumn`/`IconColumn` `->badge()->color()`. Same pattern as existing status columns. A small badge prevents large "is this number real?" arguments. |
| **No silent data loss / no silent overwrite** | Fallback must never erase a good live result with a snapshot guess, and reconciliation must never blow away a manually-corrected value without a trace. | MEDIUM | Precedence: `manual` > `live` > `snapshot`. Reconciliation only touches records currently flagged `snapshot`. Every transition logged. |
| **Audit trail of every source transition** | SIGMA already treats traceability as a product requirement (`ValidationHistory`, `VoteRecord`). A silently-changing polling place would be an outlier. | LOW–MEDIUM | Reuse the `ValidationHistory` shape (`voter_id`, `previous_*`, `new_*`, `validated_by`/actor, `validation_type`, `notes`). Extend `validation_type` values or add a sibling `PollingPlaceResolution` history table. **Strongly prefer reusing the existing pattern.** |
| **Graceful degradation — voter workflow never blocks on the live source** | If Registraduría is down, the operator still gets an answer (snapshot) and keeps working. The whole point. | LOW | Try live → on failure/timeout, fall back to snapshot and flag it. Short timeout / circuit-breaker so a dead domain never hangs the UI. |
| **Manual re-check / re-verify action** | When an operator suspects a snapshot record is wrong, or hears the live source is back, they need one-click "consultar de nuevo". | LOW | Filament row `Action` ("Reconsultar Registraduría") re-running the cascade for one voter. The current `openRegistraduriaBrowser()` suffix action is the seed. |
| **Freshness / resolved-at timestamp** | "When was this looked up?" is the second question after "where did it come from?". Distinguishes a fresh live hit from a 6-month-old snapshot. | LOW | `polling_place_resolved_at` shown next to the badge. |
| **Filter / view of voters currently on fallback data** | Coordinators need to answer "how many of my apoyos have only a *guessed* polling place?" for Day-D readiness. | LOW–MEDIUM | Filament `SelectFilter`/`TernaryFilter` on the source column. SIGMA already ships jurisdiction/ranking filters — known pattern. |

### Differentiators (Competitive Advantage)

Features that make SIGMA a *command center* for this problem, not a lookup box. Align with the "dashboards as operational control surface" decision in PROJECT.md. **Do not build all of these** — pick 1–2 for v1.1.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Reconciliation-queue / stale-data widget** (count of snapshot-sourced voters, last successful live reconcile time, next scheduled run) | Turns an invisible background job into an operator-visible health signal — "342 apoyos still on snapshot data; live source last reachable 3h ago". Extends SIGMA's dashboard direction. | MEDIUM | New Filament `StatsOverviewWidget`/`TableWidget`. Model after existing `FollowUpBacklogOverview` and `TopPollingPlacesTable`. Campaign-scoped by default. |
| **Silent auto-upgrade on reconciliation** (snapshot → live, subtle trace, no operator interruption) | The headline milestone behavior: records self-heal without nagging anyone. The trace (audit + badge amber→green) is the proof. | MEDIUM | Scheduled job iterates `where source = snapshot`, retries live, on success updates value+flag+timestamp and writes a history row. Queue + exponential backoff w/ jitter (see PITFALLS). |
| **Per-record reconciliation status** ("verified live" / "pending re-check" / "live source unreachable — using snapshot") | Richer than a binary badge; tells the operator whether the system has *given up* or is *still trying*. | MEDIUM | Derived from source flag + last-attempt timestamp + failure count. Mostly presentation over the same columns. |
| **Confidence / match-quality on snapshot hits** (exact cédula match vs. no snapshot row at all) | Snapshot is only as good as coverage (216K rows ≠ national completeness). Distinguishing "found" from "not in snapshot, unknown" prevents false confidence. | MEDIUM | Three-state result: `live` / `snapshot-hit` / `unresolved`. The `unresolved` state is itself valuable — see anti-features. |
| **Bulk re-check action** (select N snapshot voters → reconcile now) | Lets a coordinator force reconciliation before Day D instead of waiting for the schedule. | MEDIUM | Filament bulk action dispatching the same job per record. Must guard against hammering the paid/captcha live source (rate limit / chunk). |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| **Treat snapshot data as authoritative / hide the source once filled** | "It has a polling place now, ship it" — cleaner UI. | Silently laundering a guess into truth is the worst outcome for a Day-D operation. Destroys the trust this milestone exists to build. | Always carry the source flag through to every surface (record, export, Day-D lookup). Never drop it. |
| **Block the voter workflow until live reconciliation succeeds** | "We only want real data." | Registraduría is confirmed dead today; blocking would freeze the entire apoyo pipeline indefinitely. | Non-blocking fallback + background reconciliation. Operator proceeds on snapshot; system upgrades later. |
| **Real-time reconciliation on every page view / write** | "Always show the freshest data." | The live source is captcha-gated and *costs money* (2captcha) per hit. Per-view live calls = cost explosion + latency + rate-limit bans. | Scheduled batch reconciliation + cheap layers (cache, snapshot) first. Live call is the expensive last resort — already the codebase's stated order. |
| **Auto-overwrite manually-corrected polling places during reconciliation** | "Keep everything in sync with the official source." | An operator who hand-fixed a wrong record sees their correction silently reverted — infuriating and data-destroying. | Precedence `manual > live > snapshot`. Reconciliation touches only `snapshot`-flagged records. |
| **Aggressive retry of a dead endpoint** | "Keep trying so we catch it the moment it's back." | Tight retry loops against a captcha endpoint waste 2captcha budget and risk IP bans; 216K simultaneous retries can self-DoS. | Exponential backoff **with jitter**, capped attempts, circuit breaker, "source unreachable" cool-down before next sweep. |
| **A full manual polling-place editor UI as the "fix"** | "Just let them type it in." | Encourages bypassing the lookup entirely, spreading unverifiable `manual` data with no provenance discipline. | Keep manual entry, but flag it `manual` and audit it exactly like the other sources — manual is a *source*, not an escape hatch. |
| **Notify operators on every silent upgrade** | "Tell me when data changes." | The milestone explicitly wants *silent* upgrades. Per-record notifications on a batch of hundreds = spam that defeats the purpose. | Aggregate visibility via the reconciliation widget + per-record audit trail, not push notifications. |

## Feature Dependencies

```
[National census snapshot import]  (cédula-indexed table, joined to polling_places)
    └──requires──> [polling_places reference table]  (already seeded from divipole-nacional.json ✓)

[Explicit source flag on voter]
    └──requires──> [National census snapshot import]   (need a real Layer-2 to attribute to)

[Visible source badge + freshness]
    └──requires──> [Explicit source flag on voter]

[Filter: voters on fallback data]
    └──requires──> [Explicit source flag on voter]

[Manual re-check action]
    └──requires──> [Fallback lookup cascade]  (existing HasRegistraduriaPolling ✓, minus dead live layer)

[Scheduled reconciliation job]
    └──requires──> [Explicit source flag on voter]        (must know which records to retry)
    └──requires──> [Audit trail of source transitions]    (must record the upgrade)
    └──enhanced-by──> [Live source feasibility spike]     (job is inert until a live source exists)

[Reconciliation-queue widget]
    └──requires──> [Scheduled reconciliation job]  (nothing to show without a queue)

[Audit trail]
    └──reuses──> [ValidationHistory pattern]  (existing ✓)
```

### Dependency Notes

- **Source flag is the linchpin.** Almost every other feature (badge, filter, reconciliation, widget) reads it. Land the schema + flag first; everything else is presentation or scheduling on top. Natural Phase-1 anchor.
- **Reconciliation job is inert without a live source.** Its *value* is gated on the feasibility spike for `wsp.registraduria.gov.co`. Build the job as a no-op-safe scaffold (retry → live adapter → if adapter unavailable, log & skip) so it ships even if the live source is still down, then "lights up" when the adapter lands. Sequence the spike **before or parallel to** the job, not after.
- **Audit trail should reuse `ValidationHistory`, not invent a new system.** It already carries `voter_id`, previous/new state, actor (`validated_by`), a `validation_type` discriminator, and `notes`. A polling-place resolution is structurally identical to a census-status validation. Add a `validation_type` like `polling_resolution` (or a thin sibling table sharing the shape) to keep traceability consistent.
- **Widget depends on the job existing** — no reconciliation queue, nothing to visualize. Last thing to build, easiest to defer to v1.x.
- **Snapshot import is independent and parallelizable** — depends only on the already-seeded `polling_places` table, so it can be built on a separate track while the flag/schema work proceeds.

## MVP Definition

### Launch With (v1.1 core)

The minimum for a *trustworthy* dual-source result — exactly the PROJECT.md "Active" requirement set.

- [ ] **National census snapshot import** — cédula-indexed table joined to `polling_places` — *without it there is no fallback to attribute or reconcile.*
- [ ] **Explicit source flag + resolved-at timestamp on voter** — *the linchpin; makes every result honest about its origin.*
- [ ] **Source badge + freshness visible on the voter record** — *provenance must reach the human, not just the DB.*
- [ ] **Non-blocking fallback cascade** (live → snapshot, flagged) — *the resiliency behavior itself; reuses existing `HasRegistraduriaPolling` cascade minus the dead live layer.*
- [ ] **Audit trail of source transitions** (reusing `ValidationHistory`) — *SIGMA's traceability bar; non-negotiable for Day-D trust.*
- [ ] **Scheduled reconciliation job** (snapshot → live upgrade, no-op-safe until live source exists) — *the self-healing behavior named in the milestone goal.*

### Add After Validation (v1.x)

- [ ] **Manual re-check action** (per record) — *trigger: operators ask to force a re-lookup; cheap once the cascade is wrapped in an action.*
- [ ] **Filter/view of voters on fallback data** — *trigger: coordinators need Day-D readiness triage.*
- [ ] **Reconciliation-queue / stale-data widget** — *trigger: once the job runs in prod and operators want queue-depth + last-reachable visibility.*
- [ ] **Confidence/coverage state (`unresolved` vs `snapshot-hit`)** — *trigger: if snapshot coverage gaps cause false-confidence complaints.*

### Future Consideration (v2+)

- [ ] **Bulk re-check action** — *defer: needs rate-limiting discipline against the paid/captcha live source; risky before the live adapter is proven.*
- [ ] **Per-record reconciliation status narrative** ("still trying" vs "gave up") — *defer: presentation polish, only if the binary badge proves insufficient.*
- [ ] **Automated snapshot refresh pipeline** (new national CSV drops) — *defer: one-time import is enough for v1.1; recurring ingestion is its own project.*

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| National census snapshot import | HIGH | MEDIUM | P1 |
| Source flag + resolved-at on voter | HIGH | LOW | P1 |
| Source badge + freshness on record | HIGH | LOW | P1 |
| Non-blocking fallback cascade | HIGH | LOW | P1 |
| Audit trail (reuse ValidationHistory) | HIGH | LOW–MEDIUM | P1 |
| Scheduled reconciliation job (no-op-safe) | HIGH | MEDIUM | P1 |
| Live source feasibility spike (`wsp.registraduria.gov.co`) | HIGH | MEDIUM–HIGH | P1 (gates job value) |
| Manual re-check action | MEDIUM | LOW | P2 |
| Filter: voters on fallback data | MEDIUM | LOW–MEDIUM | P2 |
| Reconciliation-queue widget | MEDIUM | MEDIUM | P2 |
| Confidence/coverage state | MEDIUM | MEDIUM | P2 |
| Bulk re-check action | LOW–MEDIUM | MEDIUM | P3 |
| Reconciliation status narrative | LOW | MEDIUM | P3 |
| Automated snapshot refresh pipeline | LOW | HIGH | P3 |

**Priority key:**
- P1: Must have for v1.1 launch
- P2: Should have, add when possible
- P3: Nice to have, future consideration

## Reuse Map (existing codebase patterns this feature should lean on)

| New feature | Existing pattern to reuse | Location |
|-------------|---------------------------|----------|
| Fallback cascade (cache → snapshot → live) | Already implemented, 3-layer | `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` |
| Audit trail of source transitions | `ValidationHistory` (voter_id, prev/new, actor, type, notes) | `app/Models/ValidationHistory.php` |
| Source flag / resolved-at timestamp | `census_validated_at` timestamp convention on voters | `database/migrations/2025_11_03_132954_create_voters_table.php` |
| Reconciliation / stale-data widget | `FollowUpBacklogOverview`, `TopPollingPlacesTable`, `ValidationProgressChart` | `app/Filament/Widgets/` |
| Fallback filter | jurisdiction/ranking Filament filters | `app/Filament/Widgets/JurisdictionReportTable.php` |
| Snapshot ↔ location enrichment | `polling_places` seeded reference join (already used by Layer 2) | `app/Models/PollingPlace.php` |
| Scheduled reconciliation job | `ShouldQueue` job on real queue + structured logging + `failed_jobs` visibility (QUAL-01/02 precedent) | Day-D `FinalizeElectionEvent` pattern |

**Note:** There are currently **no** `data_source` / `is_stale` / `reconciliation` columns anywhere in the schema (verified via grep). This milestone introduces them — a clean addition, not a retrofit.

## Sources

- Existing SIGMA codebase (`HasRegistraduriaPolling` concern, `ValidationHistory`, voters migrations, Filament widgets) — HIGH confidence, primary source
- `.planning/PROJECT.md` — milestone goal, constraints, Core Value — HIGH confidence
- [Building Resilient REST API Integrations: Cache-Aside, Stale Fallback, and Background Refresh (Medium, Olga)](https://medium.com/@oshiryaeva/building-resilient-rest-api-integrations-cache-aside-stale-fallback-and-background-refresh-9028e5497dfb) — MEDIUM confidence (SWR / stale-fallback UX)
- [Resilience mechanisms in API clients: Retry, Circuit Breakers, Fallbacks (Medium, Pearl Rathour, 2026)](https://medium.com/@pearl.rathour33/resilience-mechanisms-in-api-clients-retry-logic-circuit-breakers-and-fallbacks-09d8f58569d2) — MEDIUM confidence
- [Data Freshness Boundaries in Data Architecture (NILUS)](https://www.nilus.be/blog/data_freshness_boundaries_in_data_architecture/) — MEDIUM confidence ("a small badge prevents large governance arguments")
- [Queue-Based Exponential Backoff: A Resilient Retry Pattern (DEV Community)](https://dev.to/andreparis/queue-based-exponential-backoff-a-resilient-retry-pattern-for-distributed-systems-37f3) — MEDIUM confidence (backoff + jitter, dead-letter queue)
- [Job Retries with Exponential Backoff (OneUptime)](https://oneuptime.com/blog/post/2026-01-21-bullmq-retry-exponential-backoff/view) — MEDIUM confidence

---
*Feature research for: resilient government-data lookup with local fallback + background reconciliation*
*Researched: 2026-07-24*
</content>
