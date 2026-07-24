# Pitfalls Research

**Domain:** Offline data fallback + scheduled reconciliation + captcha-automated government scraping (SIGMA v1.1 "Consulta de Puesto de Votación Resiliente")
**Researched:** 2026-07-24
**Confidence:** HIGH on codebase-specific integration pitfalls (read the actual files); HIGH on Laravel/2captcha mechanics (verified against current docs); MEDIUM on Registraduría-specific behavior (live endpoints dead, `wsp.` subdomain unvalidated — flagged for the feasibility spike).

> Scope note: this milestone does **not** build a fallback from scratch. A 3-tier cascade already exists in `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` (Redis → campaign-scoped `census_records` reconstruction → 2captcha live). The pitfalls below are specific to the *new* pieces: a national-scope snapshot table, a source-origin flag, and a scheduled reconciliation job — and how those integrate with the existing campaign-isolation and `ValidationHistory` audit patterns.

---

## Critical Pitfalls

### Pitfall 1: The 2023 snapshot is silently treated as authoritative live data

**What goes wrong:**
The census CSV is dated `2023-10-21` (filename `censo_decoded_202310210734.csv`). By election day 2026 it is ~2.5 years stale: voters have moved, died, been re-assigned to new *puestos*, and new mesas have been created. If a fallback result renders identically to a live result, an operator (or a Day-D field worker) treats a stale polling place as current fact and sends a voter to the wrong location.

**Why it happens:**
The existing `fillPollingPlaceFields()` writes snapshot data into the same `polling_place_id` / `polling_table_number` / `census_records` fields that a live lookup writes. Nothing downstream distinguishes them. The path of least resistance is to reuse that method verbatim for the fallback, which erases the origin distinction at the moment of write.

**How to avoid:**
Make the source flag a **first-class, non-null column on the write target** (e.g. `voters.polling_place_source` enum: `live` | `db_reconstruction` | `snapshot_2023` | `manual`, plus `polling_place_verified_at` timestamp). Set it in the *same* update that writes the polling place — never as a nullable afterthought. Surface the snapshot's as-of date (`2023-10-21`) in the UI, not just a generic "offline" label, so operators grasp *how* stale. Treat a missing/null source as "unknown, untrusted," not "live."

**Warning signs:**
Any code path that sets `polling_place_id` without also setting the source flag in the same statement. A UI where a snapshot result and a live result are pixel-identical.

**Phase to address:** Source-flagging phase (must land *with* the fallback-lookup phase, not after).

---

### Pitfall 2: The national snapshot table becomes a campaign-isolation leak

**What goes wrong:**
The snapshot CSV is **national and campaign-agnostic** (columns `divipol;codificado;cedula;dpto;…;puesto;nombre;mesa` — no `campaign_id`). This is intentional (one national census serves all campaigns). But the moment a fallback *reads* from it and *writes* campaign-scoped data (`census_records`, `voters`, `PollingPlace::firstOrCreate`), a bug can splice one campaign's cédula list against another's, or let Campaign A confirm a cédula exists in the national census that Campaign B never uploaded. SIGMA already had a real cross-campaign leak in a reassignment flow — this is the same failure class.

**Why it happens:**
Developers reason "the snapshot is public national data, so isolation doesn't apply to it." True for the *lookup table* — but the derived writes (`CensusRecord::updateOrCreate` with `campaign_id`, `PollingPlace::firstOrCreate`) are campaign-scoped, and `fillPollingPlaceFields()` reads `CampaignContext::currentCampaignId()`. If that context is wrong/absent (see Pitfall 4), rows land under the wrong campaign.

**How to avoid:**
Keep the snapshot table strictly **read-only and lookup-only** (indexed on `cedula`, no `campaign_id`, no writes from campaign flows). Every write *derived* from a snapshot read must go through the existing campaign-scoping and must assert a non-null, correct `campaign_id`. Add a test: Campaign A performs a fallback lookup; assert nothing readable/writable appears under Campaign B. Do **not** add the snapshot to any global scope that campaign models share.

**Warning signs:**
A `campaign_id` column creeping onto the snapshot table. A fallback write where `campaign_id` is derived from anything other than the authenticated request's active campaign. Snapshot rows appearing in a campaign-scoped report.

**Phase to address:** Snapshot-import phase (schema decision) + fallback-lookup phase (write-path isolation test).

---

### Pitfall 3: The reconciliation job has no human actor, breaking the ValidationHistory audit trail

**What goes wrong:**
`ValidationHistory.validated_by` is a **non-null FK to `users`** (see `FinalizeElectionEvent`, which always passes `$this->validatedByUserId`). A headless scheduled reconciliation job has no logged-in user. Cloning the `FinalizeElectionEvent` pattern naively either (a) crashes on the non-null FK, or (b) tempts a dev to skip writing history entirely — so a voter's polling place silently changes from snapshot→live with **no audit record of why/when**, violating SIGMA's "clear operational traceability" core value.

**Why it happens:**
`FinalizeElectionEvent` gets its actor from the human who clicked "close event." The reconciliation job is autonomous — there is no such human. The audit pattern was never designed for a system actor.

**How to avoid:**
Decide the system-actor strategy **before** writing the job: either a dedicated `system`/`registraduria-bot` user seeded once and passed as `validated_by`, or make `validated_by` nullable + add a `validation_type = 'auto_reconciliation'` and record the source transition in `notes` (e.g. `"Reconciliación automática: snapshot_2023 → live (Registraduría respondió {timestamp})"`). Every silent upgrade must produce exactly one `ValidationHistory` row so the transition is queryable. This is a schema/seed decision, not a code detail — surface it in the reconciliation phase plan.

**Warning signs:**
A reconciliation code path that updates a voter's polling place without a corresponding `ValidationHistory::create`. A hardcoded `validated_by => 1`. FK constraint violations in the job's `failed_jobs` entries.

**Phase to address:** Reconciliation-job phase (blocked by a schema/seed decision that should be made explicit in the plan).

---

### Pitfall 4: The reconciliation job runs with no campaign context and writes to the wrong (or no) campaign

**What goes wrong:**
`fillPollingPlaceFields()` — the method the reconciliation job will likely reuse to persist an upgraded result — calls `CampaignContext::currentCampaignId()`. In an HTTP request that's the operator's active campaign. In a **headless queued job there is no session and no active campaign**, so `currentCampaignId()` returns null (silently skipping the census enrichment via its `if ($cedula && $campaignId)` guard) or, worse, whatever a leaked singleton last held. A voter belongs to exactly one campaign, so the job must resolve campaign *from the voter row*, not from context.

**Why it happens:**
The existing write path was built for the interactive Filament flow where campaign context is always set. Reusing it in a job inherits an assumption that no longer holds.

**How to avoid:**
The reconciliation job must derive `campaign_id` **from each `$voter->campaign_id`** as it iterates (inside the `chunkById` loop), never from `CampaignContext`. Extract the persistence logic so it accepts an explicit `campaign_id` argument instead of reading ambient context. Add a test asserting the job writes each upgraded voter under that voter's own campaign.

**Warning signs:**
`CampaignContext::currentCampaignId()` reachable from the job. Reconciled `census_records` rows with null or mismatched `campaign_id`. Enrichment silently no-op'ing in production.

**Phase to address:** Reconciliation-job phase (this is the #1 integration risk — call it out in the plan's first checklist item).

---

### Pitfall 5: The scheduled job silently blocks/hangs on a captcha step it can never complete headlessly

**What goes wrong:**
The live tier today is **partly human-driven**: `openRegistraduriaBrowser()` opens a Filament modal, and the result arrives via `#[On('registraduria-result')]` dispatched from Alpine.js after a human interacts. A scheduled, headless reconciliation job **cannot complete a lookup that requires a human click**. If the job calls the live path optimistically, each record either hangs waiting for a dispatch that never comes, times out, or throws — burning queue workers and captcha budget for zero upgrades.

**Why it happens:**
The `registraduria-service` Python microservice *is* designed to be fully automated (2captcha + Playwright, no human), but the *SIGMA-side* trigger flow (`HasRegistraduriaPolling`) is interactive. Whether the job can run non-interactively depends entirely on whether the `wsp.registraduria.gov.co` spike proves a **fully server-solvable** flow. Until then, "automate reconciliation" is gated.

**How to avoid:**
Gate the reconciliation job on the feasibility spike outcome. If the live flow still needs a human step, the job must **detect that and flag records for manual review** (e.g. enqueue them into an operator worklist / set a `needs_manual_reverify` flag) rather than call an interactive path headlessly. Never let the job `dispatchSync` or block on a human-dependent step. Give the job a hard per-record timeout. The job's contract should be "attempt automated upgrade; if not automatable, flag and move on" — never "wait."

**Warning signs:**
Queue workers stuck in `waiting_result`. Jobs timing out at the queue's `retry_after`. Zero upgrades despite many runs. Any call from the job into the modal-triggering path (`registraduriaOpen = true`).

**Phase to address:** Feasibility-spike phase (determines if the job is even automatable) → Reconciliation-job phase (must handle the "not automatable" branch explicitly).

---

### Pitfall 6: The reconciliation job floods the 2captcha budget when Registraduría is down

**What goes wrong:**
Every live lookup costs real money (2captcha). If the job retries *all* snapshot-flagged voters on every scheduled run, and Registraduría is down (its current state — both live domains are DNS-dead), the job spends the entire captcha budget on requests that all fail, every run, forever. At 216,528 potential snapshot rows this is a runaway cost, not a rounding error.

**Why it happens:**
The naive reconciliation loop is "for each snapshot voter, try live." Without a circuit breaker or per-record backoff, a persistent outage turns into a persistent spend. SIGMA's own `FinalizeElectionEvent` template has no rate limiting because it does free DB writes — cloning it directly inherits no budget guard.

**How to avoid:**
Build the budget guard **from day one, not after the first bill**: (1) a **circuit breaker** — if N consecutive live attempts fail, stop the run and back off (the live source is down; don't hammer it); (2) **per-record exponential backoff** — a `last_reverify_attempt_at` + `reverify_attempts` column so a record isn't retried until its backoff window elapses; (3) a **per-run cap** (e.g. max 200 live attempts/run) so one run can't drain the budget; (4) a **global daily captcha ceiling** read from config. Probe cheaply first (an HTTP/DNS reachability check on the target domain) *before* spending a captcha solve.

**Warning signs:**
2captcha balance dropping with zero successful upgrades. The same cédulas attempted every single run. No `reverify_attempts` / `last_reverify_attempt_at` columns in the design.

**Phase to address:** Reconciliation-job phase (backoff + circuit breaker + cap are non-negotiable day-one requirements, not follow-ups).

---

### Pitfall 7: The reconciliation job re-processes the same records forever (no terminal state)

**What goes wrong:**
A record upgraded snapshot→live should stop being a reconciliation candidate. If the query is "all voters where source = snapshot" but the upgrade doesn't move them out of that set cleanly — or if `verified_at` isn't set — records either get re-queried forever (wasted work) or, if Registraduría *never* comes back, the never-resolvable set grows unbounded and every run scans all of it.

**Why it happens:**
Copying `FinalizeElectionEvent`'s `chunkById` over a broad `whereIn(status)` filter without a terminal-state column. There's no natural "done" for a record whose live source is permanently unreachable.

**How to avoid:**
Define explicit terminal states: `live` (upgraded — leaves the candidate set), and a capped-retry exhaustion state (e.g. after `reverify_attempts >= MAX`, mark `reverify_exhausted` so it's excluded until an operator manually resets or the source is confirmed back). The candidate query must be `source = snapshot AND NOT exhausted AND backoff_elapsed`. Index those columns so the scan stays cheap as the table grows.

**Warning signs:**
Job runtime growing every run. The same record count processed indefinitely. No column that removes a record from the candidate set.

**Phase to address:** Reconciliation-job phase.

---

### Pitfall 8: reCAPTCHA Enterprise token is "solved" but the lookup is still denied (score/env mismatch)

**What goes wrong:**
The existing scraper uses `method=userrecaptcha` (reCAPTCHA **v2**) against the now-dead `eleccionescolombia.registraduria.gov.co`. The new `wsp.registraduria.gov.co` uses **reCAPTCHA Enterprise (score-based)**. A solved token is **not** success: Registraduría's backend creates an assessment and can **reject a low-trust score even though the token is valid**. Verified current behavior: 2captcha only returns scores of ~0.1/0.3/0.9; tokens live ~2 minutes; and **any mismatch between the token-generation environment and the final request environment causes rejection**. Treating "2captcha returned a token" as "lookup will succeed" produces silent, intermittent failures that look like the source being flaky.

**Why it happens:**
v2 and Enterprise use different 2captcha parameters (Enterprise needs `enterprise=1`, sometimes action/min_score), and the failure mode moves server-side (risk score) where the client can't see why it was denied. The old code's success check (`result.get("status")`) won't distinguish "denied by score" from "cédula not found."

**How to avoid:**
Treat this as a **spike with a live success/deny classifier**, not a docs exercise — the parallel research already flagged that server-side score acceptance can't be settled from docs. In the spike: send `enterprise=1`, start at score 0.3 and raise toward 0.9 only if >50% are denied, keep the browser fetch environment consistent (same UA/headers/origin the token was minted under, as the current code already tries), and use the token within its ~2-min TTL. Build an explicit outcome taxonomy: `success` vs `denied_by_score` vs `not_found` vs `source_unreachable` — and only `success` counts as an upgrade. Budget for a token-rejection rate; don't assume 1 solve = 1 result.

**Warning signs:**
High 2captcha success rate but low lookup success rate. Intermittent denials that correlate with nothing. Reusing the v2 `userrecaptcha` params against the Enterprise sitekey.

**Phase to address:** Feasibility-spike phase (this is the spike's core unknown; its result gates whether the live source and the automated reconciliation job are viable at all).

---

### Pitfall 9: Jurisdiction/divipol codes drift between the 2023 snapshot and today, corrupting dentro/fuera reports

**What goes wrong:**
SIGMA already ships a jurisdiction "dentro/fuera" report (Phase 04.1) that decides whether a voter's polling place is inside or outside the campaign's territory. If the 2023 snapshot's `divipol` / municipality codes (`dpto`, `mcpio`, `zona`, `puesto`) don't match today's `polling_places` / `divipole-nacional.json` seed — because municipalities merged, codes were reissued, or a puesto was renamed/relocated — a snapshot-sourced polling place resolves to the wrong municipality or fails the `PollingPlace` join, silently flipping a voter's dentro/fuera classification. Inaccurate operational numbers are explicitly "unacceptable" per project constraints.

**Why it happens:**
`resolveFromDatabase()` and `fillPollingPlaceFields()` match on `Municipality.code` and `PollingPlace.name` (a `whereRaw LOWER(name)` string match). Snapshot names/codes from 2023 won't always string-match the current seed. The join quietly returns null and the code falls through to partial data.

**How to avoid:**
During snapshot import, **validate the snapshot's codes against the current `polling_places`/`Municipality` seed** and report the unmatched percentage — don't import blind. For snapshot-sourced results, prefer **code-based joins over name string-matching**, and when a code doesn't resolve, mark the result as low-confidence rather than silently emitting partial fields. Never let a snapshot result feed the dentro/fuera report without the source flag propagating into that report so stale classifications are visibly caveated.

**Warning signs:**
Snapshot rows whose `mcpio`/`divipol` don't match any `polling_places` row. A jump in "fuera" classifications after enabling fallback. `PollingPlace::firstOrCreate` creating many new near-duplicate places from snapshot imports.

**Phase to address:** Snapshot-import phase (validation report) + must be re-checked wherever the dentro/fuera report consumes polling data.

---

### Pitfall 10: The fallback clobbers fresher data (snapshot overwrites a good live/DB result)

**What goes wrong:**
`fillPollingPlaceFields()` unconditionally overwrites `polling_place_id`, `polling_table_number`, and `CensusRecord` via `updateOrCreate`. If the fallback path reuses it, a **stale 2023 snapshot can overwrite a previously-resolved live result** (e.g. operator re-opens a voter while Registraduría is briefly down, fallback fires, good live data is replaced with older snapshot data — a silent downgrade).

**Why it happens:**
The write path has no notion of source precedence; last-write-wins. The cache/DB tiers were all "equally trustworthy" before; introducing a *less*-trustworthy snapshot tier breaks that assumption.

**How to avoid:**
Establish a **source precedence order** (`live` > `db_reconstruction` > `snapshot_2023`) and **never downgrade**: a snapshot write must not overwrite a record already flagged `live` (or already `verified_at` within a freshness window) unless an operator explicitly forces it. Guard the write: only apply snapshot data if the existing source is null or lower-precedence.

**Warning signs:**
A voter's source flag going `live` → `snapshot_2023`. `verified_at` moving backwards. Operators reporting "the polling place changed to old data by itself."

**Phase to address:** Fallback-lookup phase (precedence guard) — depends on the source flag from the source-flagging phase existing first.

---

### Pitfall 11: `withoutOverlapping()` lock gets stuck and freezes reconciliation forever

**What goes wrong:**
The plan is to schedule via `Schedule::job(...)->withoutOverlapping()`. Verified current Laravel behavior: if a run dies without releasing the lock (fatal error, hard kill, server reboot, or a job that hangs on the captcha step per Pitfall 5), **the lock defaults to a 24-hour hold** and the reconciliation job silently won't run again until it expires — a silent scheduler failure with no error surfaced.

**Why it happens:**
`withoutOverlapping()` is correct for preventing double-runs, but its default lock TTL is long and stale locks aren't self-evident. A job that can hang (Pitfall 5) is exactly the kind that leaves stale locks.

**How to avoid:**
Always pass an explicit expiry sized to the job's realistic max runtime: `->withoutOverlapping($minutes)` for scheduled commands, or `(new WithoutOverlapping())->expireAfter($seconds)` for job middleware. Combine with a hard per-record timeout (Pitfall 5) so a run can't outlive its lock TTL. Document `schedule:clear-cache` as the recovery lever. Add monitoring/alerting on "last successful reconciliation run" age so a stuck lock is caught in hours, not on election day.

**Warning signs:**
Reconciliation "ran" per the scheduler but no rows changed and no errors logged. A 24h gap in the job's structured logs. `last_reverify_attempt_at` timestamps frozen across many scheduled ticks.

**Phase to address:** Reconciliation-job phase.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Reuse `fillPollingPlaceFields()` verbatim for the fallback | Fast; no new write path | Erases source origin, reads ambient `CampaignContext` (wrong in a job), clobbers fresher data | Never as-is — must be refactored to take explicit `campaign_id` + `source` args |
| Skip the source flag in MVP, "add it later" | Ships fallback faster | Stale data becomes indistinguishable from live; can't build the reconciliation candidate query (it needs `source = snapshot`) | Never — the flag *is* the feature and the job's WHERE clause |
| Retry all snapshot voters every run, no backoff | Simplest loop | Drains 2captcha budget during any outage; job runtime grows unbounded | Never for a paid external call |
| Hardcode `validated_by => <some user id>` in the job | Satisfies the non-null FK quickly | Audit trail lies about who changed the record; no `system` actor concept | Only if that id is a real seeded `system`/bot user, documented as such |
| Import the 216k-row CSV without code-validation against current seed | Fast import | Silent join failures corrupt dentro/fuera reports; duplicate polling places | Only with an unmatched-rows report reviewed before go-live |
| String-match polling place by `LOWER(name)` for snapshot rows | Reuses existing code | 2023 names drift from current seed → nulls → partial data | Only as a fallback *after* a code-based join fails, flagged low-confidence |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| National snapshot table ↔ campaign-scoped models | Adding `campaign_id` to the snapshot or writing to it from campaign flows | Keep it read-only/lookup-only; derive campaign writes from `$voter->campaign_id`, assert non-null |
| Reconciliation job ↔ `ValidationHistory` (non-null `validated_by` FK) | Skipping history writes or hardcoding a user id | Seed a `system` actor or make FK nullable + `validation_type='auto_reconciliation'`; one row per transition |
| Reconciliation job ↔ `CampaignContext` singleton | Reusing the interactive write path that reads `currentCampaignId()` | Pass `campaign_id` explicitly per record; ban `CampaignContext` from the job |
| Reconciliation job ↔ interactive captcha modal flow | Calling the modal-triggering live path headlessly; it hangs on a human dispatch | Only call a *fully server-solvable* path; if not automatable, flag for manual review |
| 2captcha ↔ reCAPTCHA **Enterprise** (`wsp.` subdomain) | Using v2 `userrecaptcha` params; treating a token as success | `enterprise=1`, score 0.3→0.9, respect ~2-min TTL + env consistency; classify `denied_by_score` separately |
| Snapshot import ↔ existing `polling_places` / `divipole-nacional.json` | Blind import; `firstOrCreate` spawning duplicate places | Validate codes against current seed; join by code first, report unmatched % |
| `Schedule::job()->withoutOverlapping()` | Default 24h lock hold silently freezes the job after a crash/hang | Explicit `expireAfter`/minutes arg sized to max runtime; alert on stale last-run |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Reconciliation scans all snapshot voters every run | Job runtime grows each tick | Terminal states + backoff columns in the candidate WHERE; index them | As soon as snapshot-flagged set grows into the thousands |
| Snapshot lookup not indexed on `cedula` | Slow fallback on every miss | B-tree index on `cedula` at import (216,528 rows) | Immediately at national scale |
| Per-run captcha attempts uncapped | Budget drained, queue saturated during an outage | Per-run cap + circuit breaker + reachability probe before solve | The first prolonged Registraduría outage |
| `PollingPlace::firstOrCreate` per snapshot row | Table bloats with near-duplicates | Resolve by code; only create when genuinely new | During bulk snapshot-sourced resolution |

## Security / Data-Integrity Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| National snapshot readable/joinable across campaigns | Cross-campaign data leak (repeat of prior incident) | Read-only lookup table; isolation test A-vs-B on the fallback write path |
| Stale snapshot written with no source flag | Voters sent to wrong polling place on Day D; untrustworthy reports | Non-null source flag + as-of date, set in the same write |
| Silent snapshot→live upgrade with no audit row | No traceability of why/when a voter's place changed | Mandatory `ValidationHistory` row per transition |
| Snapshot overwrites live data (downgrade) | Good data silently replaced with 2.5-year-old data | Source-precedence guard; never downgrade automatically |
| Scraping a `.gov.co` site under automation | ToS/rate/legal exposure; IP bans killing the live tier | Rate-limit + backoff + circuit breaker; treat live source as best-effort, snapshot as the resilient floor |
| 2captcha token treated as proof of a real voter | Confirming a cédula/place that doesn't reflect reality | `success` only on a real result payload, not on token receipt |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Fallback result looks identical to a live result | Operator trusts stale data as current | Distinct visual treatment + explicit "Datos de censo 2023-10-21, no verificado en vivo" label |
| Generic "offline mode" with no as-of date | Operator underestimates staleness | Show the snapshot date and "pendiente de re-verificación" |
| Silent auto-upgrade with no operator-visible signal | Operator never learns the data was corrected | Reflect source + `verified_at` on the voter profile; it's already in `ValidationHistory` |
| No indication a record is stuck needing manual re-verify | Records rot in limbo (source dead + not automatable) | Surface a "needs manual re-verification" worklist/badge |
| dentro/fuera report shows snapshot-sourced rows uncaveated | Territorial decisions made on stale classification | Propagate the source flag into the report; caveat snapshot rows |

## "Looks Done But Isn't" Checklist

- [ ] **Fallback lookup:** Often missing the **source flag written in the same statement** — verify no path sets `polling_place_id` without setting `polling_place_source`.
- [ ] **Fallback lookup:** Often missing the **no-downgrade guard** — verify a snapshot write can't overwrite a `live`-flagged record.
- [ ] **Snapshot table:** Often missing **campaign-isolation proof** — verify an A-vs-B test that Campaign A's fallback exposes nothing to Campaign B.
- [ ] **Snapshot import:** Often missing **code-validation report** — verify the unmatched-against-current-seed percentage is known before go-live.
- [ ] **Reconciliation job:** Often missing **system actor / audit row** — verify every upgrade writes exactly one `ValidationHistory`.
- [ ] **Reconciliation job:** Often missing **campaign_id from the voter row** — verify `CampaignContext` is never called inside the job.
- [ ] **Reconciliation job:** Often missing **backoff + circuit breaker + per-run cap** — verify a simulated outage doesn't drain the captcha budget.
- [ ] **Reconciliation job:** Often missing **terminal/exhaustion state** — verify a never-resolvable record eventually leaves the candidate set.
- [ ] **Reconciliation job:** Often missing **the "not automatable" branch** — verify it flags-and-skips instead of hanging on a human captcha step.
- [ ] **Reconciliation job:** Often missing **explicit `withoutOverlapping` expiry** — verify a killed run doesn't freeze the schedule for 24h.
- [ ] **Enterprise captcha spike:** Often missing **outcome classification** — verify `denied_by_score` vs `not_found` vs `unreachable` are distinguished, not lumped as "error."
- [ ] **Feasibility gate:** Often missing — verify the reconciliation-job phase is explicitly **blocked** until the spike proves a server-solvable flow.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| No source flag shipped | HIGH | Backfill is guesswork (can't retroactively know which rows were snapshot vs live); may require re-resolving all records |
| Cross-campaign leak via snapshot | HIGH | Audit all derived writes, purge mis-scoped rows, add isolation test, incident review (as with the prior leak) |
| Captcha budget drained | MEDIUM | Kill the schedule, add circuit breaker + cap + backoff, top up balance, re-enable behind a config ceiling |
| `withoutOverlapping` lock stuck | LOW | `php artisan schedule:clear-cache`; then add explicit `expireAfter` |
| Snapshot clobbered live data | MEDIUM | Re-resolve affected voters via live/DB; add precedence guard; use `ValidationHistory` to find affected rows |
| Enterprise flow proves unviable in spike | LOW (if caught in spike) / HIGH (if built first) | Keep live tier human-driven; reconciliation flags-for-manual instead of auto-upgrading |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| 1. Stale snapshot treated as authoritative | Source-flagging (with fallback-lookup) | No write sets place without source; UI shows as-of date |
| 2. National snapshot isolation leak | Snapshot-import + fallback-lookup | A-vs-B isolation test on the write path |
| 3. Audit trail broken (no actor) | Reconciliation-job (schema/seed decision) | Every upgrade → one `ValidationHistory` row |
| 4. Job runs with no/wrong campaign context | Reconciliation-job | `CampaignContext` absent from job; per-voter `campaign_id` test |
| 5. Job hangs on human captcha step | Feasibility-spike → Reconciliation-job | Simulate non-automatable path → flags, never hangs |
| 6. Captcha budget flooded | Reconciliation-job | Simulated outage stays under per-run/daily cap |
| 7. Records re-processed forever | Reconciliation-job | Never-resolvable record exits candidate set after MAX attempts |
| 8. Enterprise token accepted but denied | Feasibility-spike | Spike classifies success vs denied_by_score on live calls |
| 9. Divipol/jurisdiction drift | Snapshot-import (+ dentro/fuera consumer) | Unmatched-code report; snapshot rows caveated in report |
| 10. Fallback clobbers fresher data | Fallback-lookup (needs flag first) | No-downgrade guard test (live not overwritten by snapshot) |
| 11. `withoutOverlapping` stale lock | Reconciliation-job | Explicit expiry set; killed-run doesn't freeze 24h |

## Sources

- Codebase (HIGH): `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php`, `app/Jobs/FinalizeElectionEvent.php`, `app/Models/ValidationHistory.php`, `registraduria-service/app.py`, `database/external-data/censo_decoded_202310210734.csv`, `.planning/PROJECT.md`.
- 2captcha reCAPTCHA Enterprise / v3 score behavior (verified current): https://2captcha.com/h/how-to-bypass-recaptcha-v3-enterprise , https://2captcha.com/api-docs/recaptcha-v3 , https://www.capsolver.com/blog/reCAPTCHA/recaptcha-score-explained
- Google reCAPTCHA Enterprise assessment/score docs: https://docs.cloud.google.com/recaptcha/docs/interpret-assessment-website
- Laravel `withoutOverlapping` stale-lock behavior (verified current): https://github.com/laravel/framework/issues/37060 , https://msaied.com/articles/laravel-overlapping-scheduled-tasks-the-production-problem-nobody-talks-about , https://mozex.dev/blog/17-5-laravel-scheduler-failures-that-only-show-up-in-production
- Parallel milestone research (confirmed context): existing 3-tier cascade, 2captcha Enterprise support with `enterprise=1`, `FinalizeElectionEvent` clone pattern, human-in-the-loop captcha gate.

---
*Pitfalls research for: offline data fallback + scheduled reconciliation + captcha-automated government scraping (SIGMA v1.1)*
*Researched: 2026-07-24*
