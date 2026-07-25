# Roadmap: SIGMA - Sistema Integral de Gestion y Analisis Electoral

## Milestones

- ✅ **v1.0 MVP Hardening** — Phases 1-5.1 (shipped 2026-07-24)
- 🚧 **v1.1 Consulta de Puesto de Votación Resiliente** — Phases 6-11 (in progress)

## Phases

<details>
<summary>✅ v1.0 MVP Hardening (Phases 1-5.1) — SHIPPED 2026-07-24</summary>

- [x] Phase 1: Campaign Safety & Role Boundaries — completed 2026-07-24 (via 02.1 + incidental work + 05.1 gap closure)
- [x] Phase 2: Voter Spine Hardening — completed 2026-07-24 (via 02.1 + incidental work + 05.1 gap closure)
- [x] Phase 02.1: Apoyos - Reglas Core y Segmentacion (INSERTED) — 11/11 plans — completed 2026-07-24
- [x] Phase 3: Outreach & Follow-up Reliability — completed 2026-07-24 (via incidental work + 05.1 gap closure)
- [x] Phase 4: Trusted Reporting & Control Surfaces — completed 2026-07-24 (via 04.1 + 05.1 gap closure)
- [x] Phase 04.1: Reportes Avanzados de Apoyos (INSERTED) — 5/5 plans — completed 2026-07-24
- [x] Phase 5: Day D Readiness & Trust Safeguards — completed 2026-07-24 (via incidental work + 05.1 gap closure)
- [x] Phase 05.1: Cross-Phase Hardening & Trust Safeguards Closure (INSERTED) — 9/9 plans — completed 2026-07-24

Full phase details, success criteria, and per-plan history: `.planning/milestones/v1.0-ROADMAP.md`
Requirements traceability: `.planning/milestones/v1.0-REQUIREMENTS.md`
Shipped summary: `.planning/MILESTONES.md`

</details>

### 🚧 v1.1 Consulta de Puesto de Votación Resiliente (In Progress)

**Milestone Goal:** When live Registraduría lookup is unavailable, SIGMA still resolves a cédula's polling place using a local national census snapshot, clearly marks the data's origin, and automatically reconciles against the live source once it's reachable again.

This is a **brownfield completion** of the 3-tier fallback cascade already in `HasRegistraduriaPolling` (Redis cache → campaign-scoped `census_records` DB reconstruction → 2captcha live). v1.1 adds the four missing pieces: a national snapshot fallback tier, a persisted source flag, an auditable resolution history, and a scheduled reconciliation job — plus a quarantined feasibility spike for a new live source.

- [x] **Phase 6: National Census Snapshot Import** — Load the 216K-row census snapshot into a cédula-indexed, location-enriched reference table with import-quality validation (completed 2026-07-24)
- [x] **Phase 7: Source-Flag Schema & Resolution Audit Trail** — Make a voter's polling-place source a persisted, queryable attribute with an append-only change history (completed 2026-07-24)
- [ ] **Phase 8: Resilient PollingPlaceResolver Service** — Extract the fallback cascade into one service that never blocks on a dead live source or silently downgrades fresher data
- [ ] **Phase 9: Live-Source Feasibility Spike** — Time-boxed, non-blocking spike to validate (or rule out) `wsp.registraduria.gov.co` reCAPTCHA Enterprise as a live-source adapter
- [ ] **Phase 10: Operator Provenance & Fallback Controls** — Show result origin, allow on-demand re-check, and let operators triage voters still on fallback data
- [ ] **Phase 11: Scheduled Reconciliation Job** — Unattended, campaign-safe, bounded, un-freezable job that upgrades fallback-sourced voters when the live source recovers

## Phase Details

### Phase 6: National Census Snapshot Import
**Goal**: The national census snapshot is a queryable, cédula-indexed reference table enriched with real location data, with import quality visible before go-live.
**Depends on**: Nothing (independent — can run in parallel with Phase 7)
**Requirements**: CENSO-02, CENSO-03
**Success Criteria** (what must be TRUE):
  1. Running the national import command loads the full snapshot into a cédula-indexed `national_census_records` table kept strictly separate from campaign-scoped data.
  2. A lookup by cédula returns full department/municipality names and address, not just divipol codes (resolved via join against the seeded `polling_places` reference data).
  3. Accented, Latin-1-encoded names (e.g., "LA PEÑATA") import without UTF-8 corruption.
  4. The import validates every snapshot divipol code against the current `polling_places` seed and reports the unmatched percentage before go-live.
  5. Re-running the import is idempotent (no duplicate cédula rows).
**Plans**: 1/1 plans complete
- [x] 06-01-PLAN.md — national_census_records migration + model + factory, census:import-national streaming importer (divipol join, Latin-1 decode, unmatched-% report, idempotent upsert), and its Pest feature test (completed 2026-07-24)

### Phase 7: Source-Flag Schema & Resolution Audit Trail
**Goal**: A voter's polling-place source is a first-class persisted, queryable attribute, and every change to it is captured in an append-only audit history.
**Depends on**: Nothing (independent — can run in parallel with Phase 6; no data dependency)
**Requirements**: SRC-03
**Success Criteria** (what must be TRUE):
  1. Each voter carries a persisted, indexed `polling_place_source` (live / db_reconstruction / snapshot / manual) and `polling_place_resolved_at`, queryable by a background job.
  2. Every change to a voter's polling-place source writes an audit row recording actor, previous → new source, and timestamp.
  3. The audit trail tolerates a headless/system actor (nullable resolver actor, `ValidationHistory`-shaped) so automated changes are recorded too.
  4. A voter's full source-change history is retrievable via an Eloquent relation.
**Plans**: 1/1 plans complete
- [x] 07-01-PLAN.md — polling_place_source/polling_place_resolved_at on voters + polling_place_resolutions audit table, PollingPlaceSource enum, PollingPlaceResolution model/factory, Voter relation wiring, feature test (completed 2026-07-24)

### Phase 8: Resilient PollingPlaceResolver Service
**Goal**: A single service expresses the fallback cascade exactly once, resolving polling places without ever blocking on a dead live source or silently downgrading fresher data.
**Depends on**: Phase 6 (snapshot to read) and Phase 7 (flag + audit to write)
**Requirements**: CENSO-01, SRC-02, LIVE-01, LIVE-03
**Success Criteria** (what must be TRUE):
  1. When the live Registraduría source is unavailable, the resolver returns a polling place from the national census snapshot, flagged as snapshot-sourced. (CENSO-01)
  2. The resolver never overwrites a live-verified result with an older snapshot result — source precedence (live > db_reconstruction > snapshot) is enforced, never auto-downgraded. (SRC-02)
  3. The lookup workflow returns promptly and never hangs waiting on an unreachable live source; the automated path gives up on a `waiting_captcha` step rather than blocking. (LIVE-03)
  4. Live sources are tried in priority order via interchangeable adapters, so a new source (e.g., wsp) can be added without redesigning the resolver, and the cascade is shared by both interactive and headless callers. (LIVE-01)
**Plans**: 1/3 plans complete
- [x] 08-01-PLAN.md — LiveSourceAdapter interface, RegistraduriaService reachability probe + kill switch, PollingPlaceResolutionResult VO
- [ ] 08-02-PLAN.md — PollingPlaceResolver core: campaign-DB/national-snapshot tiers, no-downgrade guard + audit-transition persistence, bounded automated live attempt
- [ ] 08-03-PLAN.md — Bind resolver in AppServiceProvider, refactor HasRegistraduriaPolling to delegate, new interactive-cascade test coverage

### Phase 9: Live-Source Feasibility Spike
**Goal**: A time-boxed spike settles whether `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) can serve as a real live-source adapter, end to end — without blocking the deterministic core.
**Depends on**: Nothing (independent, non-blocking spike; runs in parallel with Phases 6-8; must complete before Phase 11's automated live mode is trusted, never sequenced after it)
**Requirements**: LIVE-02
**Success Criteria** (what must be TRUE):
  1. The spike extracts the wsp Enterprise sitekey (+ `action`/`data-s` if present), solves the Enterprise captcha (`enterprise=1` + sitekey), injects the token, and submits one real cédula end-to-end against the live endpoint.
  2. The outcome is classified explicitly as success / denied-by-score / not-found / source-unreachable — a returned token is never treated as a successful lookup.
  3. A documented go/no-go decision for adopting wsp as a live-source adapter is produced, and the milestone still delivers its resilient core (snapshot + provenance + reconciliation) regardless of the spike's outcome.
**Plans**: TBD

### Phase 10: Operator Provenance & Fallback Controls
**Goal**: Operators can see the origin of every polling-place result, re-check any voter on demand, and triage everyone still on fallback data.
**Depends on**: Phase 8 (resolver populates and surfaces the source flag)
**Requirements**: SRC-01, SRC-04, SRC-05
**Success Criteria** (what must be TRUE):
  1. Every polling-place result visibly shows its source (live / database reconstruction / local snapshot / manual) to the operator on the voter record. (SRC-01)
  2. An operator can trigger a manual re-check of a voter's polling place at any time from the record. (SRC-04)
  3. An operator can filter/view the set of voters currently on a fallback-sourced (non-live) polling place. (SRC-05)
**Plans**: TBD
**UI hint**: yes

### Phase 11: Scheduled Reconciliation Job
**Goal**: An unattended scheduled job safely upgrades fallback-sourced voters to live data when the live source recovers — campaign-safe, auditable, bounded, and impossible to silently freeze.
**Depends on**: Phase 7 (flag to query) and Phase 8 (resolver to re-attempt); informed by Phase 9's go/no-go for the live path
**Requirements**: RECON-01, RECON-02, RECON-03, RECON-04, RECON-05, RECON-06
**Success Criteria** (what must be TRUE):
  1. A scheduled job automatically re-attempts live lookup for fallback-sourced voters and upgrades them — persisting the new source flag and an audit row with a system-actor/reason — when the live source succeeds. (RECON-01, RECON-03)
  2. The job resolves each voter's campaign from the voter record (`$voter->campaign_id`), never from ambient/interactive session context, and never touches a voter outside that campaign. (RECON-02)
  3. The job is rate-limited and bounded from day one (per-run cap + circuit breaker + per-record backoff) so a prolonged outage cannot drain the captcha budget or self-flood. (RECON-04)
  4. A voter whose live source can never be resolved (or needs human captcha interaction the job can't complete) reaches a terminal/exhaustion state instead of being retried forever. (RECON-05)
  5. A stuck or expired scheduler lock cannot silently freeze reconciliation — `withoutOverlapping()` carries an explicit expiry sized to the job's real max runtime. (RECON-06)
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 6 → 7 → 8 → 9 → 10 → 11. Phases 6 and 7 have no interdependency and may be built in either order or in parallel; Phase 9 (spike) is non-blocking and may run any time before Phase 11.

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 6. National Census Snapshot Import | v1.1 | 1/1 | Complete   | 2026-07-24 |
| 7. Source-Flag Schema & Audit Trail | v1.1 | 1/1 | Complete   | 2026-07-24 |
| 8. Resilient PollingPlaceResolver Service | v1.1 | 0/3 | Not started | - |
| 9. Live-Source Feasibility Spike | v1.1 | 0/TBD | Not started | - |
| 10. Operator Provenance & Fallback Controls | v1.1 | 0/TBD | Not started | - |
| 11. Scheduled Reconciliation Job | v1.1 | 0/TBD | Not started | - |

---

*v1.1 roadmap created 2026-07-24. Next: `/gsd:plan-phase 6`.*
