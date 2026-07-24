# Architecture Research

**Domain:** Resilient dual-source (live-scrape vs. local-census-snapshot) polling-place lookup inside an existing Laravel 12 / Filament 4 / Livewire 3 app (SIGMA v1.1)
**Researched:** 2026-07-24
**Confidence:** HIGH (grounded in the actual codebase; every integration point below names a real existing file/class)

## Executive Framing

This is a **brownfield integration**, not a greenfield build. The single most important finding of this research is that **a fallback lookup chain already exists** — it is just incomplete for the v1.1 requirements. Before designing anything new, understand what is already there:

`app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php::openRegistraduriaBrowser()` already implements a **3-tier cascade**:

1. **Redis cache** (`registraduria:cedula:{cedula}`, 30-day TTL) — instant, free
2. **DB reconstruction** (`resolveFromDatabase()`) — joins the **campaign-scoped** `census_records` table against `polling_places`, free, permanent
3. **2captcha live lookup** (`RegistraduriaService::startLookup()` → async poll) — last resort, costs money

So "fallback" is not a new concept in this codebase. What v1.1 actually adds on top of the existing cascade is **four missing pieces**:

- **A national census snapshot table** (the 216K-row CSV is nationwide reference data; the existing `census_records` table is campaign-scoped and semantically different).
- **A persisted, queryable source flag on the voter** (today the "source" is only communicated via a transient Filament `Notification` — "desde caché" / "desde base de datos" — and is lost the moment the lookup completes).
- **An auditable resolution history** (nothing today records *why* a voter's polling place has the value it has).
- **A scheduled reconciliation job** to re-attempt live lookup for snapshot-sourced voters.

The correct architectural move is to **extract the cascade out of the Filament trait into a dedicated orchestrating service**, add the national snapshot as a new tier, and make the source flag a first-class persisted attribute so a background job can query for it.

## Standard Architecture (target state)

### System Overview

```
┌───────────────────────────────────────────────────────────────────────┐
│                          INTERACTIVE (Filament UI)                      │
├───────────────────────────────────────────────────────────────────────┤
│  HasRegistraduriaPolling (trait)   RegistraduriaController (browser AJAX)│
│         │ delegates to                       │ (unchanged — live browser)│
│         ▼                                     ▼                          │
├───────────────────────────────────────────────────────────────────────┤
│                     ORCHESTRATION (new service layer)                    │
│   ┌─────────────────────────────────────────────────────────────────┐  │
│   │  PollingPlaceResolver  (NEW)                                      │  │
│   │   resolve(cedula): PollingPlaceResolution                        │  │
│   │   ── tier 1: Redis cache                                          │  │
│   │   ── tier 2: live (RegistraduriaService)                          │  │
│   │   ── tier 3: national snapshot (NationalCensusRecord)  ← NEW tier │  │
│   │   ── writes source flag + audit row                              │  │
│   └───────────┬───────────────────────────┬──────────────────────────┘  │
│               │                            │                             │
├───────────────┼────────────────────────────┼─────────────────────────────┤
│               ▼                            ▼                             │
│   RegistraduriaService (EXISTING,   NationalCensusRecord (NEW model)     │
│   thin HTTP client → Python svc)    → joined to PollingPlace at import   │
├───────────────────────────────────────────────────────────────────────┤
│                       BACKGROUND (queue + scheduler)                     │
│   ReconcileSnapshotPollingPlaces (NEW job, mirrors FinalizeElectionEvent)│
│   Schedule::job(...)->hourly()->withoutOverlapping()  (routes/console.php)│
│         │ queries voters WHERE polling_place_source = 'snapshot'         │
│         └─ re-attempts live via PollingPlaceResolver                     │
├───────────────────────────────────────────────────────────────────────┤
│                              PERSISTENCE                                 │
│  voters (+ polling_place_source, polling_place_resolved_at)  ← NEW cols  │
│  polling_place_resolutions  (NEW audit table, ValidationHistory-shaped)  │
│  national_census_records    (NEW, 216K rows, cedula-unique)  ← NEW table │
│  polling_places (EXISTING national ref)   census_records (EXISTING, camp)│
└───────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Status | Responsibility |
|-----------|--------|----------------|
| `RegistraduriaService` | **KEEP AS-IS** | Thin HTTP client to the Python microservice. Single responsibility: `startLookup()` / `getResult()`. **Do not add fallback logic here** — it must stay a pure live-source adapter so the resolver can treat it as one tier among several. |
| `PollingPlaceResolver` | **NEW** | The orchestrator. Owns the cascade decision (cache → live → snapshot), returns a `PollingPlaceResolution` value object carrying the resolved fields **and the source flag**, and persists both the voter flag and the audit row. This is the *only* place the fallback order is expressed. |
| `NationalCensusRecord` | **NEW model + table** | Nationwide cédula→polling-place reference data imported from `censo_decoded_202310210734.csv`. `polling_place_id` FK resolved at import time via the divipol codes. Sibling to `PollingPlace` (both are national reference data, **not** campaign-scoped). |
| `voters.polling_place_source` + `polling_place_resolved_at` | **NEW columns** | The *current* source flag, directly on the voter so it is visible in the UI and **queryable by the reconciliation job**. Values: `live`, `snapshot`, `manual`, `cache`. |
| `polling_place_resolutions` | **NEW audit table** | Append-only history of every resolution, structurally modeled on `ValidationHistory` (see below). Answers "this voter's polling place came from source X on date Y, attempted by Z." |
| `ReconcileSnapshotPollingPlaces` | **NEW queued job** | Scheduled job that finds `polling_place_source = 'snapshot'` voters and re-attempts live lookup via the resolver. Structurally mirrors `FinalizeElectionEvent`. |
| `HasRegistraduriaPolling` | **REFACTOR** | Stop owning the cascade. Delegate to `PollingPlaceResolver`. Keep the Filament-specific concerns (notifications, filling `$this->data[...]`, the async browser modal state). Its private `resolveFromDatabase()` logic moves into the resolver. |
| `RegistraduriaController` | **KEEP AS-IS** | Proxies the interactive browser/captcha flow (screenshot/click/viewport) to the Python service. Untouched by v1.1. |
| `census_records` (campaign-scoped) | **KEEP** | Stays as the per-campaign enrichment accumulator. **Not** the national snapshot. May remain as an additional resolver tier (see "Two census tables" below). |

## Key Design Decisions

### Decision 1: National snapshot is a NEW table, not a reuse of `census_records`

**Recommendation:** Create `national_census_records` (model `NationalCensusRecord`). Do **not** widen the existing `census_records`.

**Why:** The existing `census_records` table is fundamentally campaign-scoped:
- `campaign_id` is a non-null FK with `cascadeOnDelete()`.
- Unique constraint is `(campaign_id, document_number)`.
- It uses the `HasCampaignContext` trait, so every query is auto-scoped to the active campaign.
- Its lifecycle is per-campaign upload (`CensusImporter`) + per-lookup enrichment (`HasRegistraduriaPolling::fillPollingPlaceFields()` does `CensusRecord::updateOrCreate([...campaign_id...])`).

The 216K-row CSV is **nationwide, campaign-agnostic reference data** — the same category as `polling_places` (also nationwide, also seeded from an external file). Trying to shoehorn it into `census_records` breaks in three ways: (a) making `campaign_id` nullable defeats the unique constraint (MySQL/Postgres do not dedupe NULLs), (b) it would pollute every campaign-scoped census query with 216K national rows, and (c) it conflates two lifecycles (national import-once vs. campaign accumulate). Keep national reference data next to `polling_places`, not inside campaign operational data.

**Schema (`national_census_records`):**

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `document_number` | string, **unique**, indexed | The `cedula` column from the CSV — the lookup key |
| `polling_place_id` | nullable FK → `polling_places` | **Resolved at import time** via divipol join (see Decision 4) |
| `table_number` | string, nullable | The CSV `mesa` column |
| `dane_department_code` / `dane_municipality_code` / `zone_code` / `place_code` | string | Raw divipol components kept for traceability + re-join if `polling_places` is re-seeded |
| `polling_station_name` | string, nullable | The CSV `nombre` column (fallback when FK resolution misses) |
| `imported_at` | timestamp | Snapshot provenance / which import run |

No `campaign_id`, no `HasCampaignContext` trait, no timestamps churn — this is static reference data.

### Decision 2: Source flag = column on `voters` (current) + `polling_place_resolutions` table (history)

The requirement has two halves — "**visible** on the voter's result" and "**auditable**" — and they want different storage.

**Current flag → columns on `voters`:**
```
polling_place_source      enum: live | snapshot | manual | cache   (nullable)
polling_place_resolved_at timestamp (nullable)
```
This must live on the voter itself because:
- The reconciliation job's core query is `Voter::where('polling_place_source', 'snapshot')` — that has to be an indexed column, not a JOIN into an audit table's latest row.
- The Filament UI needs to show the current source next to the polling place without an extra query.

Add both to the `$fillable` array and a `PollingPlaceSource` enum cast (follow the `VoterStatus` enum precedent in `app/Enums/`).

**History → new `polling_place_resolutions` table, modeled on `ValidationHistory`:**

This directly satisfies the quality gate's "reuse the ValidationHistory-style audit pattern." I reuse the *pattern* — not the table — because `ValidationHistory`'s `previous_status`/`new_status` columns are **non-nullable and cast to the `VoterStatus` enum**; a polling-place source change is not a voter-status change and would abuse those columns. Instead, mirror its shape:

| `ValidationHistory` (precedent) | `polling_place_resolutions` (new, same shape) |
|---|---|
| `voter_id` FK cascadeOnDelete | `voter_id` FK cascadeOnDelete |
| `previous_status` / `new_status` | `previous_source` / `new_source` |
| `validated_by` FK → users | `resolved_by` FK → users **(nullable — jobs have no user)** |
| `validation_type` (`census`/`election`) | `resolved_via` (`interactive`/`reconciliation`) |
| `notes` text nullable | `notes` text nullable (+ `polling_place_id`, `table_number` snapshot) |
| scopes: `forVoter`, `byType`, `recent` | same scopes: `forVoter`, `bySource`, `recent` |
| indexes: `voter_id`, `validation_type`, `created_at` | same indexes |

Model `PollingPlaceResolution` gets a `voter()` BelongsTo and a `resolver()` BelongsTo (nullable), exactly mirroring `ValidationHistory::voter()` / `validator()`. Add a `Voter::pollingPlaceResolutions(): HasMany` relation next to the existing `validationHistories()`.

**One notable difference from `ValidationHistory`:** `validated_by` is non-nullable there because a human always drives validations. Reconciliation runs headless, so `resolved_by` **must** be nullable — this is the one place the pattern legitimately diverges.

### Decision 3: Fallback decision lives in a NEW `PollingPlaceResolver`, not in `RegistraduriaService` and not in the trait

**Do not** put the cascade in `RegistraduriaService` — it must stay a single-responsibility live-source HTTP adapter so it is testable and swappable (relevant given the separate `wsp.registraduria.gov.co` feasibility spike may replace the live source entirely).

**Do not** leave the cascade in `HasRegistraduriaPolling` — it is trapped there today, UI-coupled, and cannot be reused by the background job. The trait's `resolveFromDatabase()` is exactly the logic that must become reusable.

**Instead:** `app/Services/PollingPlaceResolver.php` exposes:
```
resolve(string $cedula, ?Voter $voter = null): PollingPlaceResolution
```
returning a `PollingPlaceResolution` value object `{ source, pollingPlaceId, tableNumber, fields[], resolvedAt }`. The resolver:
1. Checks Redis cache → `source = cache`.
2. Attempts live via `RegistraduriaService` → `source = live` (interactive path stays async/UI-driven; see the async caveat below).
3. Falls back to `NationalCensusRecord::where('document_number', $cedula)->first()` → `source = snapshot`.
4. When a `Voter` is passed, persists `polling_place_source` + `polling_place_resolved_at` and appends a `polling_place_resolutions` row.

Both the Filament trait and the reconciliation job call this one method, so the fallback order is expressed exactly once.

**Two census tables — how they relate (avoid confusion):** after v1.1 there are two census-shaped tables with different jobs. `census_records` = campaign-scoped, mutable, accumulates verified live results per campaign (existing tier 2 of today's cascade). `national_census_records` = nationwide, static, import-once baseline (new snapshot tier). The resolver can keep the campaign `census_records` reconstruction as a tier *before* the national snapshot (it holds richer, campaign-verified data), then fall to the national snapshot as the broad baseline. Recommended resolver tier order: `cache → live → campaign census_records → national snapshot`. Only the last two count as "snapshot" source for the flag.

**Async caveat (important, flag for roadmap):** the *live* tier is genuinely asynchronous and partly human-driven — `startLookup()` returns a `session_id`, then the Alpine/browser modal in `registraduria-browser.blade.php` handles `waiting_captcha` by showing screenshots and forwarding clicks. A headless job **cannot** complete a lookup that requires a human captcha click. So the resolver needs two live modes: an **interactive** mode (returns the session for the UI to drive) and an **automated** mode (polls `getResult()` with bounded backoff and *gives up* if it hits `waiting_captcha` without auto-solve). How completely the automated mode works is **gated by the `wsp.registraduria.gov.co` feasibility spike** — if the new source auto-solves via 2captcha server-side, reconciliation is fully unattended; if not, reconciliation can only opportunistically catch cases the automated path resolves and must leave the rest snapshot-flagged for a human.

**Live-first vs. cost-first tension (flag for roadmap):** the v1.1 requirement says "attempt live first, fall back to snapshot." The *existing* interactive code is deliberately cost-**last** (cache → DB → 2captcha) because live costs money per lookup. These conflict. Recommended resolution: the reconciliation job is always live-first (that is its whole purpose); the *interactive* path keeps cache as a transparent perf layer, then follows the requirement's live→snapshot order, while retaining the existing explicit `forceRefreshFromRegistraduria()` "Actualizar datos" action for operator-driven fresh live pulls. The roadmap should confirm this ordering with the client since it has a real per-lookup cost implication.

### Decision 4: Import via an Artisan command using the divipol join, mirroring `PollingPlaceSeeder`

The CSV row `280019909010,00;280019909;1100696116;28;0;0;1;;99;0;9;CHOCHO;10` decodes to `dpto=28, mcpio=1, zona=99, puesto=9, nombre=CHOCHO, mesa=10`. Those four codes (`dpto/mcpio/zona/puesto`) are exactly the `(dane_department_code, dane_municipality_code, zone_code, place_code)` key on `polling_places`, and `nombre` matches `polling_places.name`. So the import can resolve `polling_place_id` per row by pre-loading a keyed map of `polling_places` (same technique `PollingPlaceSeeder` already uses with its `keyBy` maps).

**Build it as `php artisan census:import-national` (an Artisan command), not a Filament import or a plain seeder**, because 216K rows demands streaming: use `LazyCollection::make(fn() => ...fgetcsv...)` + `->chunk(1000)` + `NationalCensusRecord::upsert()` on the unique `document_number`. This mirrors `CensusImporter::importInBatches()` (batched `insert()`) but at national scale and idempotent (upsert lets re-running a newer snapshot update in place). Note the CSV is Latin-1 (`LA PE�ATA`) — decode with `mb_convert_encoding(..., 'UTF-8', 'ISO-8859-1')` on ingest.

### Decision 5: Reconciliation job is a structural clone of `FinalizeElectionEvent`

`FinalizeElectionEvent` is the established, test-protected queued-job pattern in this codebase (per PROJECT.md QUAL-01/02). Clone its structure exactly:

| `FinalizeElectionEvent` element | `ReconcileSnapshotPollingPlaces` equivalent |
|---|---|
| `implements ShouldQueue` + `use Queueable` | identical |
| ctor with scalar IDs (`electionEventId`) | ctor with optional `?int $limit` (batch slice per run) |
| `Log::info('election_event.finalize.started', [...])` | `Log::info('polling_reconcile.started', [...])` |
| `Voter::query()->where(...)->chunkById(500, fn)` | `Voter::where('polling_place_source','snapshot')->oldest('polling_place_resolved_at')->limit($n)->chunkById(500, fn)` |
| per-record `ValidationHistory::create([...])` | per-record `PollingPlaceResolution::create([...])` via the resolver |
| `Log::info('...completed', ['voters_closed' => $n])` | `Log::info('polling_reconcile.completed', ['reconciled' => $n, 'still_snapshot' => $m])` |
| `failed(\Throwable $e)` → `Log::error` | identical |

**Scheduling:** register in `routes/console.php` alongside the existing scheduled entries, using the `Schedule::job(...)->withoutOverlapping()` idiom already established by `Schedule::command('birthday:dispatch-webhooks')->everyMinute()->withoutOverlapping()`:
```php
Schedule::job(new ReconcileSnapshotPollingPlaces)->hourly()->withoutOverlapping();
```

**Throttling (important given live-lookup cost/rate limits):** unlike `FinalizeElectionEvent` (pure DB writes, cheap), each reconciliation record triggers a slow, possibly paid live lookup against the Python service. Do **not** re-attempt every snapshot voter every run. Use `->oldest('polling_place_resolved_at')->limit(N)` so each hourly run reconciles a bounded slice (least-recently-attempted first). If N-per-run proves too slow inline, the scale-up path is to fan out one lightweight per-voter job (mirroring the existing per-item `App\Jobs\SendMessage` pattern) onto a rate-limited queue — but start with the bounded in-job `chunkById` loop; it matches `FinalizeElectionEvent` and is simpler to test.

## Data Flow (in words)

**Interactive lookup (operator enters a cédula in the Filament voter form):**

```
cédula entered
  → HasRegistraduriaPolling suffix action
    → PollingPlaceResolver::resolve(cedula, voter)
        tier 1: Redis cache hit?  → fill fields, source = cache        → STOP
        tier 2: live attempt (RegistraduriaService.startLookup → browser modal / poll)
                  success?        → fill fields, source = live, warm cache → STOP
        tier 3: campaign census_records / NationalCensusRecord by document_number found?
                  yes             → fill fields (via polling_place_id), source = snapshot
                  no              → not found
    → persist voter.polling_place_source + polling_place_resolved_at
    → append polling_place_resolutions row (resolved_via = interactive, resolved_by = current user)
    → Filament Notification shows source to operator
```

**Reconciliation (hourly, headless):**

```
Schedule fires ReconcileSnapshotPollingPlaces
  → Voter WHERE polling_place_source = 'snapshot'
          ORDER BY polling_place_resolved_at ASC  LIMIT N
  → chunkById(500):
        for each voter:
          PollingPlaceResolver::resolve(voter.document_number, voter)  [automated live mode]
            live succeeded (no human captcha needed)?
              yes → update voter polling place, source = live,
                    append polling_place_resolutions (resolved_via = reconciliation, resolved_by = null)
              no  → leave as snapshot (bump resolved_at so it rotates to back of queue)
  → Log::info completed { reconciled, still_snapshot }
```

## Recommended Build Order (respects the dependency chain)

The dependencies are strict: you cannot fall back to a snapshot that isn't imported, you cannot flag a source without the schema, and you cannot reconcile without both the flag (to query) and the resolver (to re-attempt).

1. **National snapshot table + model + import command** — `national_census_records` migration, `NationalCensusRecord` model, `php artisan census:import-national` (streaming upsert + divipol→`polling_place_id` join, Latin-1 decode). *Blocks everything; the fallback has nothing to read until this exists.* Ship with a test that imports a small fixture CSV and asserts `polling_place_id` resolution.

2. **Source-flag schema** — migration adding `voters.polling_place_source` + `polling_place_resolved_at` (+ `PollingPlaceSource` enum + cast + `$fillable`); `polling_place_resolutions` table + `PollingPlaceResolution` model (ValidationHistory-shaped, `resolved_by` nullable) + `Voter::pollingPlaceResolutions()` relation. *Must exist before the resolver can write a flag and before the job can query for stale records.* Can proceed in parallel with step 1 (no data dependency between them).

3. **`PollingPlaceResolver` orchestrating service** — extract/generalize the cascade out of `HasRegistraduriaPolling` (its `resolveFromDatabase()` becomes a resolver tier), add the `NationalCensusRecord` snapshot tier, persist flag + audit row, return a `PollingPlaceResolution` VO. Refactor the trait to delegate. *Depends on 1 (snapshot to read) and 2 (flag/audit to write).*

4. **`ReconcileSnapshotPollingPlaces` job + schedule** — clone `FinalizeElectionEvent`, query `snapshot`-flagged voters with a bounded `limit`, re-attempt via the resolver's automated mode, register in `routes/console.php`. *Depends on 2 (flag to query) and 3 (resolver to re-attempt).* Ship with a direct job test mirroring the `FinalizeElectionEvent` test.

5. **(Parallel / non-blocking) `wsp.registraduria.gov.co` feasibility spike** — does not block 1–4, but **determines how effective step 4's automated live mode can be**. If the new source cannot be auto-solved server-side, step 4 still ships but reconciles only the subset the automated path can complete; document that limitation rather than blocking on it.

## Anti-Patterns to Avoid

| Anti-pattern | Why it's wrong here | Do instead |
|---|---|---|
| Widening `census_records` with a nullable `campaign_id` to hold the national snapshot | Breaks the `(campaign_id, document_number)` unique dedup (NULLs aren't deduped), pollutes every campaign-scoped census query with 216K rows, conflates two lifecycles | New `national_census_records` table (Decision 1) |
| Putting the cascade logic inside `RegistraduriaService` | Destroys its single responsibility as a live-source adapter; makes it untestable and un-swappable right when a source swap (`wsp`) is being evaluated | `PollingPlaceResolver` owns the cascade (Decision 3) |
| Forcing polling-source history through `ValidationHistory` | Its `previous_status`/`new_status` are non-null and `VoterStatus`-cast; a source change is not a status change | New `polling_place_resolutions`, same *shape* as ValidationHistory (Decision 2) |
| Reusing `FinalizeElectionEvent`'s "process every matching voter every run" for reconciliation | Live lookups are slow/paid/rate-limited; re-hitting all snapshot voters hourly stampedes the Python service | Bounded `->oldest(...)->limit(N)` slice per run (Decision 5) |
| Making the reconciliation job depend on a human captcha click | Jobs are headless; the interactive modal can't run in a queue worker | Automated live mode that *gives up* on `waiting_captcha`; gate completeness on the `wsp` spike (Decision 3 async caveat) |
| Importing 216K rows via a Filament import action or a single `insert()` | Memory blow-up / timeout | Streaming `LazyCollection` + chunked `upsert()` in an Artisan command (Decision 4) |
| Storing the source only in a transient Filament `Notification` (current behavior) | Not persisted, not queryable, lost after the request — the reconciliation job can't find snapshot voters | Persisted `voters.polling_place_source` column (Decision 2) |

## Testing Considerations (per project QUAL requirements)

The project explicitly requires test protection for voter/Day-D flows and treats `FinalizeElectionEvent`'s direct job test as the QUAL-01/02 precedent. For v1.1:
- **Import command test:** small fixture CSV → assert row count, `polling_place_id` FK resolution, and Latin-1 → UTF-8 handling.
- **Resolver test:** table-driven (Pest dataset) over the tiers — cache hit, live success, live-down→snapshot fallback, total miss — asserting the correct `source` flag and that a `polling_place_resolutions` row is written each time.
- **Reconciliation job test:** direct job test mirroring the `FinalizeElectionEvent` test — seed snapshot-flagged voters, fake the resolver's live tier as reachable, assert voters flip to `source = live` and audit rows are appended with `resolved_by = null`; and the inverse (live still down → stay `snapshot`).
- **Campaign isolation:** `national_census_records` is intentionally *not* campaign-scoped (it's national reference data), but the resolver writes to campaign-scoped `voters` — assert a lookup for campaign A never mutates campaign B's voter, consistent with the project's strict-isolation constraint.

## Sources

- **HIGH** — Direct reads of the SIGMA codebase (authoritative, current):
  - `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` (existing 3-tier cascade, `resolveFromDatabase()`)
  - `app/Services/RegistraduriaService.php` (live HTTP adapter)
  - `app/Http/Controllers/RegistraduriaController.php` (interactive browser proxy)
  - `app/Jobs/FinalizeElectionEvent.php` (queued-job pattern to mirror)
  - `app/Models/ValidationHistory.php` + `database/migrations/2025_11_03_171233_create_validation_histories_table.php` (audit pattern to mirror)
  - `app/Models/CensusRecord.php` + `database/migrations/2025_11_03_170817_create_census_records_table.php` + `2026_05_10_190000_make_census_records_nullable.php` (campaign-scoped — why the snapshot needs a new table)
  - `app/Services/CensusImporter.php` (batched-insert precedent for the import command)
  - `app/Services/VoterValidationService.php` (existing ValidationHistory writer pattern)
  - `app/Models/Voter.php` / `app/Models/PollingPlace.php` + `database/migrations/2026_01_22_000003_add_polling_place_to_voters_table.php` (where the source columns attach)
  - `database/seeders/PollingPlaceSeeder.php` + `database/external-data/divipole-nacional.json` (divipol keyed-map join technique)
  - `database/external-data/censo_decoded_202310210734.csv` (216,528 rows; column layout + Latin-1 encoding confirmed by direct inspection)
  - `routes/console.php` (`Schedule::job()->withoutOverlapping()` idiom to reuse)
  - `.planning/PROJECT.md` (v1.1 milestone requirements, QUAL test-protection precedent, strict-isolation constraint)
