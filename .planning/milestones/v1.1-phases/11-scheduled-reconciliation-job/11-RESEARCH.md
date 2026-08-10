# Phase 11: Scheduled Reconciliation Job - Research

**Researched:** 2026-07-26
**Domain:** Laravel scheduled/queued jobs (bounded, campaign-safe, auditable) + production-wiring a Registraduría live-source HTTP reachability probe and HTML-response parser
**Confidence:** HIGH on the job/schedule/migration mechanics and on the reachability-probe fix (directly verified against the live `wsp.registraduria.gov.co` endpoint from this environment). LOW on the exact HTML `#consulta` table structure the parser (D-02) must target — this is a genuine, unresolved gap; see Open Questions and the recommended Task 1 below.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Live-Source Production Wiring (prerequisite, done within this phase)**
- **D-01:** Fix the reachability gap: `config('services.registraduria.probe_url')` / `REGISTRADURIA_PROBE_URL` currently points to the dead `apiweb-eleccionescolombia.infovotantes.com` domain (a leftover from before Phase 9). Update it to correctly reflect `wsp.registraduria.gov.co` reachability so `RegistraduriaService::isReachable()` (called by `PollingPlaceResolver::attemptLiveAutomated()` before every automated attempt) returns true when the live source is actually up. Without this fix, the reconciliation job would call `resolveAutomated()`, always get "unreachable," and silently fall through to snapshot every time — RECON-01 would never actually succeed.
- **D-02:** Write an HTML-to-structured-fields parser for the wsp success response. `registraduria-service/app.py` (Phase 9) currently returns only `data.raw_message_html` (a raw `<table id="consulta">` HTML blob) on `success`, not the structured fields (`puesto_nombre`, `puesto_codigo`, `zona_codigo`, `mesa_numero`, `departamento`, `municipio`, `direccion`) that `RegistraduriaService::getResult()`'s existing docblock contract promises and that `HasRegistraduriaPolling::fillPollingPlaceFields()` / `PollingPlaceResolver::resolveOrCreatePollingPlace()` require to populate `municipality_id`/`polling_place_id`/etc. **The full HTML table structure was never fully captured** — Phase 9's `09-SPIKE-RESULTS.md` only logged the first ~200 characters of each response (enough to confirm the `id="consulta"` table exists, not enough to know every column/row shape). Research for this phase must re-extract at least one full, untruncated wsp success response (e.g., via a fresh zero-or-low-cost live attempt) to determine the exact HTML structure before writing the parser.
- **D-03:** This wiring work happens either in `registraduria-service/app.py` (parse the HTML into the structured dict server-side, matching the old `infovotantes` response shape so `RegistraduriaService.php` needs zero changes) or in `RegistraduriaService.php` (parse `raw_message_html` into structured fields on the PHP side). Left as **Claude's discretion** for research/planning to decide based on where parsing is more reliable/testable — no user preference either way.

**System Actor for Audit Trail (RECON-03)**
- **D-04:** Automated reconciliation writes use `resolved_by = null` + `resolved_via = 'reconciliation'` — no seeded system/bot user. Zero new migrations needed: Phase 7 already made `polling_place_resolutions.resolved_by` nullable specifically for this (07-CONTEXT.md D-05), and `PollingPlaceResolver::resolveAutomated()` already defaults its `$resolvedVia` parameter to `'reconciliation'`. Audit reports distinguish automated from manual/interactive changes via the `resolved_via` column, not via a fake user identity.

**Schedule, Batch Size & Captcha Budget (RECON-04)**
- **D-05:** The job runs **hourly** via `Schedule::command(...)->hourly()->withoutOverlapping($expiresAt)` in `routes/console.php` — same style as the existing `birthday:dispatch-webhooks` entry (which already uses `->withoutOverlapping()`).
- **D-06:** Processes up to **50 voters per run**. With 24 runs/day, this bounds worst-case captcha spend to **~500 voters/day** system-wide (not per-campaign) — a predictable, cheap ceiling given 2captcha's ~$1-3/1000-solve pricing confirmed in Phase 9.
- **D-07:** A circuit breaker independent of the per-run cap: if the live source's reachability check fails (or the first few attempts in a run all fail with `source_unreachable`-equivalent errors), the run should skip remaining voters for that run rather than attempting all 50 against a confirmed-down source — exact circuit-breaker mechanics are Claude's discretion (e.g., check `isLiveReachable()` once per run before the loop, matching the existing `attemptLiveAutomated()` pattern which already does this per-attempt).

**Terminal / Exhaustion State (RECON-05)**
- **D-08:** A voter reaches a terminal "exhausted" state after **5 consecutive failed live reconciliation attempts** (i.e., 5 job runs in a row where this voter's live attempt did not succeed — the counter resets to 0 the moment a live attempt succeeds for that voter). Once exhausted, the job skips that voter on future runs (no more live attempts spent on them) until something resets the counter (e.g., a future manual "Actualizar" force-refresh from Phase 10, which is unaffected by this — it's a human-initiated action, not this job's automated cascade).
- **D-09:** Two new columns on `voters`: `reconciliation_attempts` (integer, default 0, increments on each failed automated live attempt, resets to 0 on success) and `reconciliation_exhausted_at` (nullable timestamp, set once attempts hit 5, checked by the job's query to skip already-exhausted voters). New migration required — this is additive schema, not a change to Phase 7's existing `polling_place_source`/`polling_place_resolved_at`/`polling_place_resolutions` schema.

**Lock / Stuck-Run Protection (RECON-06)**
- **D-10:** `withoutOverlapping()` carries an explicit expiry of **10 minutes** — sized with margin above the worst-case runtime of a 50-voter run (each attempt backs off up to ~1.6s per `attemptLiveAutomated()`'s existing backoff array, plus captcha solve time if a full live round-trip is attempted; 10 minutes is comfortably above any realistic single-run duration without leaving a truly stuck run frozen for hours).

### Claude's Discretion
- Exact mechanics of the per-run circuit breaker (D-07).
- Whether the wsp HTML parser lives in the Python service or the PHP service (D-03).
- Exact query shape for selecting "eligible for reconciliation" voters (fallback-sourced, not exhausted, respecting D-06's 50-per-run cap) — follow `FinalizeElectionEvent`'s `chunkById` pattern where applicable, though a bounded `limit(50)` is likely more direct here than a full chunked scan.
- Command/job naming conventions — follow existing style (e.g., `census:reconcile-live` per prior STACK.md research, or similar).

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope. (The wiring work, initially assumed to be a separate future phase/quick-task per Phase 9's D-05, was explicitly pulled into this phase's scope after discovering the reachability-probe and HTML-parsing gaps.)
</user_constraints>

## Project Constraints (from CLAUDE.md)

- **No dependency changes without approval** — directly load-bearing for D-03 (see "Standard Stack" and "Don't Hand-Roll" below): the PHP side already has `ext-dom`/`DOMDocument` bundled with core PHP (verified: zero Composer entries needed), while the Python side has **no** HTML-parsing library in `requirements.txt` (only `flask`, `playwright`, `aiohttp`) — adding BeautifulSoup4/lxml there would be a new pip dependency requiring approval. This is a strong, concrete tiebreaker for D-03.
- **`php artisan make:` for all new files, always `--no-interaction`** — use `make:job`, `make:command`, `make:migration`.
- **Explicit `use` statements, never inline namespace paths** — applies to the new Job/Command classes.
- **Descriptive names** — e.g. `reconciliation_attempts`, not `attempts`.
- **Thin controllers/commands, logic in Actions/Services** — the new Artisan Command should be thin and delegate to a queued Job (mirrors `DispatchBirthdayWebhooks` → `BirthdayWebhookService` shape, and `FinalizeElectionEvent`'s existing Job shape).
- **Log via `Log::info/error/debug`, dotted event names** — matches `FinalizeElectionEvent`'s `election_event.finalize.started/completed/failed` convention.
- **Every change must have a test** — Pest, `RefreshDatabase`, fake `LiveSourceAdapter` implementations (exact existing pattern in `tests/Feature/Services/PollingPlaceResolverTest.php`).
- **`vendor/bin/pint --dirty`** before finalizing.
- Python code in `registraduria-service/` is not literally Laravel/PHP, but Phase 9's own research treated CLAUDE.md's "no new dependency" rule as binding there too (it declined to add a 2captcha SDK for that exact reason) — treat this as established project precedent, not a loophole.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RECON-01 | Scheduled job re-attempts live lookup for fallback-sourced voters, upgrades on success | Job/Command/Schedule skeleton below; D-01's reachability fix (verified via direct `curl` testing) and D-02/D-03's parser are prerequisites — without them `resolveAutomated()`'s live tier is unreachable and RECON-01 is hollow, exactly as CONTEXT.md's domain note states |
| RECON-02 | Resolves each voter's own campaign from the voter record, never ambient session context | **Critical verified finding** (see Common Pitfalls #3): `Voter`'s `CampaignContextScope` global scope no-ops entirely when `Auth::user()` is null (confirmed by reading `CampaignContextScope::apply()` and `CampaignContext::currentCampaignId()` directly) — a queued job (no HTTP session, `QUEUE_CONNECTION=database`) naturally sees ALL voters across ALL campaigns with zero extra code, and `resolveAutomated($cedula, $voter, ...)` / `persist()` already operate on the voter's own `campaign_id` intrinsically. RECON-02 is satisfied by construction, not by new code — but this must be **tested explicitly**, not assumed |
| RECON-03 | Auditable actor/reason even though unattended | Already solved by existing code: `resolved_by` nullable (Phase 7 D-05), `resolved_via` defaults to `'reconciliation'` (`PollingPlaceResolver::resolveAutomated()`'s existing default parameter) — job needs zero new code for this, just call `resolveAutomated()` as-is |
| RECON-04 | Rate-limited/bounded, cannot exhaust captcha budget or self-flood | D-06 (50/run cap) + D-07 (circuit breaker) — concrete recommendation below: call `$resolver->isLiveReachable()` once before the per-voter loop, short-circuit the whole run if false |
| RECON-05 | Terminal state instead of infinite retry | D-08/D-09 — **critical ambiguity resolved by direct test-suite evidence** (see Common Pitfalls #4): `resolveAutomated()` can return a non-null result whose `source` is `SNAPSHOT` (live gave up/timed out, cascade fell through) — this MUST count as a failed attempt for the 5-strike counter; only `source === LIVE` resets the counter to 0 |
| RECON-06 | Stuck/expired lock cannot silently freeze the job | D-10 — **critical unit gotcha verified directly against `vendor/laravel/framework` source**: `withoutOverlapping($expiresAt = 1440)`'s parameter is in **minutes**, not seconds — `->withoutOverlapping(10)` is correct for a 10-minute expiry; `->withoutOverlapping(600)` would silently mean 600 minutes (10 hours), defeating D-10's intent |
</phase_requirements>

## Summary

This phase has two genuinely separable halves. The **job mechanics half** (RECON-02 through RECON-06) is low-risk and highly precedented: this codebase already has an almost-identical scheduled/queued job (`FinalizeElectionEvent`), an almost-identical `Schedule::command()->withoutOverlapping()` entry (`birthday:dispatch-webhooks`), and — critically — a `PollingPlaceResolver::resolveAutomated()` method that already does 90% of the campaign-safe, auditable, non-blocking work this job needs; the job is mostly a thin wrapper around calling that method in a loop with a bounded, ordered query. Three concrete, verified findings de-risk this half further: (1) `CampaignContextScope`'s global scope naturally no-ops for unauthenticated queue-worker processes, satisfying RECON-02 by construction; (2) `resolveAutomated()`'s SNAPSHOT-fallthrough return value must be treated as a *failed* attempt for D-08's counter, not a success — confirmed by reading the existing Pest test suite's own behavioral assertions; (3) `withoutOverlapping()`'s expiry parameter is in **minutes**, a unit mismatch that would be an easy, silent RECON-06 violation if miscoded in seconds.

The **live-source wiring half** (D-01/D-02/D-03) is where the real research burden lives, and this pass resolved D-01 completely but could not resolve D-02 to the same confidence. **D-01 is now resolved with HIGH confidence via direct, repeated live testing from this environment**: a bare HTTP `HEAD` request to `wsp.registraduria.gov.co/censo/consultar/` returns `500` **every single time** (4/4 attempts, including the bare domain root), while a plain `GET` to the same URL returns `200` **every single time** (5/5 attempts) with the real form page body (not a WAF block). This means the current `isReachable()` implementation's `Http::head(...)` call would make the reconciliation job believe the live source is *always* down, even though it's up — the fix is not just swapping the dead domain for the live one, but also switching the HTTP verb from `HEAD` to `GET`. **D-02 could not be resolved**: despite exhausting every available discovery avenue within this research pass's no-live-spend constraint (repo-wide search for any fuller captured response, `service.log` inspection, live-fetching the wsp page and its `functions.js` for any embedded sample/DataTables column config, and reviewing this project's own prior — different-site — HTML/text parser as circumstantial vocabulary evidence), the exact column structure of the wsp `<table id="consulta">` response remains genuinely unknown beyond its first ~130 characters (`<thead><tr><th class='text-center'>NUIP</th><th class='text-cente...`). This is a hard, real gap — not researcher laziness — and the plan MUST schedule a dedicated first task to capture one full untruncated response before the parser can be written against real data (see Open Questions).

D-03 (where the parser lives) has a clear, evidence-backed recommendation: **parse in PHP** using the bundled `ext-dom`/`DOMDocument`+`DOMXPath` (confirmed present with zero Composer changes, `php -m | grep dom` → `dom`), not in Python (which has no HTML-parsing library installed and would require a new pip dependency CLAUDE.md's precedent treats as needing approval).

**Primary recommendation:** (1) Fix `isReachable()` to `GET` `wsp.registraduria.gov.co/censo/consultar/` (not `HEAD`), update `REGISTRADURIA_PROBE_URL` and `config/services.php`'s fallback default. (2) Schedule a dedicated first task/plan step that spends exactly one real 2captcha-budgeted lookup to capture and log the full, untruncated `#consulta` HTML response before writing the parser — do not guess the schema. (3) Parse in PHP via `DOMDocument`/`DOMXPath` (zero new dependencies), tested with Pest against a saved fixture of the real captured HTML. (4) Build the reconciliation job as a thin `ShouldQueue` Job (mirroring `FinalizeElectionEvent`) dispatched by a thin Artisan Command (mirroring `DispatchBirthdayWebhooks`), scheduled hourly with `->withoutOverlapping(10)` (minutes, not seconds), processing a `limit(50)` query ordered oldest-resolved-first, calling `isLiveReachable()` once per run as the circuit breaker, and treating any `resolveAutomated()` return whose `source !== PollingPlaceSource::LIVE` (including `null` and including a SNAPSHOT re-confirmation) as a failed attempt against the new `reconciliation_attempts`/`reconciliation_exhausted_at` columns.

## Standard Stack

### Core (already installed — no new dependencies for this phase)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 12.36.1 (verified via `composer show laravel/framework`) | `ShouldQueue` job, `Schedule::command()`, `withoutOverlapping()` | Already the project's job/scheduling framework |
| PHP `ext-dom` (`DOMDocument`, `DOMXPath`) | Bundled with PHP 8.4.14 (verified: `php -m \| grep dom` → `dom`; `class_exists('DOMDocument')` → `true`) | Parse the wsp `<table id="consulta">` HTML into structured fields (D-02/D-03) | Zero Composer changes needed — decisive tiebreaker for D-03 (see Don't Hand-Roll) |
| Flask / Playwright / aiohttp (existing, `registraduria-service/requirements.txt`: `flask==3.1.1`, `playwright==1.50.0`, `aiohttp==3.11.18`) | current | Existing Python microservice, unchanged by this phase's recommendation | No new pip dependency needed if parsing happens PHP-side (D-03 recommendation) |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None new required | — | — | Every capability this phase needs (queue jobs, scheduling, HTML parsing) is already available in the existing stack |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP-side `DOMDocument`/`DOMXPath` parsing (recommended) | Python-side parsing with BeautifulSoup4/lxml | Requires a new pip dependency (against CLAUDE.md precedent per Phase 9's own treatment of the Python service); also splits the "what does a result look like" contract across two languages/processes instead of keeping it entirely at the PHP boundary where `RegistraduriaService::getResult()`'s docblock already defines it |
| PHP-side `DOMDocument`/`DOMXPath` | Regex-based HTML scraping | `DOMDocument`/`DOMXPath` is the standard, robust choice for parsing a real (if messy) HTML table; regex against nested `<table>`/`<tr>`/`<td>` markup is exactly the kind of fragile hand-rolling CLAUDE.md's Laravel conventions and general good practice discourage — see Don't Hand-Roll |

**Installation:** None — `ext-dom` is already loaded; no `composer require` or `pip install` needed for either half of this phase.

**Version verification:** `composer show laravel/framework` → `v12.36.1` (verified this pass). `php -v` → `8.4.14` per CLAUDE.md's stated stack, `ext-dom` bundled and confirmed loaded.

## Architecture Patterns

### Recommended Project Structure (new files this phase adds)

```
app/
├── Jobs/
│   └── ReconcileFallbackPollingPlaces.php   # ShouldQueue, mirrors FinalizeElectionEvent's shape
├── Console/Commands/
│   └── ReconcileLivePollingPlaces.php       # thin, dispatches the Job — mirrors DispatchBirthdayWebhooks' shape
├── Services/
│   └── RegistraduriaService.php             # MODIFIED: isReachable() GET fix (D-01) + HTML parser (D-02/D-03, if PHP-side)
config/
└── services.php                             # MODIFIED: probe_url fallback default (D-01)
database/migrations/
└── {timestamp}_add_reconciliation_fields_to_voters_table.php   # new: reconciliation_attempts, reconciliation_exhausted_at (D-09)
registraduria-service/
└── app.py                                    # UNCHANGED if parsing lands PHP-side (D-03 recommendation); MODIFIED only if parsing lands Python-side instead
routes/
└── console.php                               # MODIFIED: new Schedule::command(...)->hourly()->withoutOverlapping(10) entry (D-05/D-10)
```

### Pattern 1: Thin Command → Queued Job (existing house style)

**What:** A thin Artisan Command registered in `routes/console.php`'s scheduler, which dispatches (or directly invokes) a `ShouldQueue` Job containing the actual bounded-query/loop logic.
**When to use:** Any scheduled, potentially-long-running batch operation — this is exactly `FinalizeElectionEvent`'s existing shape, invoked today via a controller action rather than the scheduler, but the shape (constructor takes only scalar/serializable args, `handle()` does the work, `failed()` logs) is what to mirror.
**Example (adapted from the existing `FinalizeElectionEvent` — read directly this pass):**
```php
// Source: app/Jobs/FinalizeElectionEvent.php (existing, read directly)
class FinalizeElectionEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $electionEventId,
        public int $validatedByUserId,
    ) {}

    public function handle(): void
    {
        Log::info('election_event.finalize.started', [...]);
        // ... bounded query + loop ...
        Log::info('election_event.finalize.completed', [...]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('election_event.finalize.failed', [...]);
    }
}
```
The reconciliation job needs **no constructor arguments at all** (it processes a system-wide bounded query, not a single record) — inject `PollingPlaceResolver` via `handle(PollingPlaceResolver $resolver): void` **method injection**, not the constructor, since queued jobs are serialized and the resolver (with its `iterable $liveAdapters`) is not a serializable value — Laravel resolves type-hinted `handle()` parameters from the container automatically when the job is processed, exactly like `FinalizeElectionEvent`'s convention of keeping constructor args to primitives only.

### Pattern 2: `Schedule::command()->hourly()->withoutOverlapping($minutes)` (existing house style)

**What:** Register the reconciliation command in `routes/console.php`, following the exact existing precedent.
**Example (existing, read directly from `routes/console.php`):**
```php
// Source: routes/console.php (existing)
Schedule::command('birthday:dispatch-webhooks')->everyMinute()->withoutOverlapping();
```
**Recommended for this phase (D-05/D-10):**
```php
Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10);
```
**Verified directly against `vendor/laravel/framework/src/Illuminate/Console/Scheduling/ManagesAttributes.php`:**
```php
public function withoutOverlapping($expiresAt = 1440)
```
`$expiresAt` is in **minutes**, default `1440` (24 hours). `->withoutOverlapping(10)` is therefore the correct call for D-10's "10 minutes" — **not** `->withoutOverlapping(600)` (which would silently mean 600 minutes ≈ 10 hours). `hourly()` is confirmed (read directly from `ManagesFrequencies.php`) to splice the schedule to run at minute `0` of every hour — standard, no surprises.

### Pattern 3: Bounded, ordered eligibility query (D-06, Claude's discretion resolved)

**What:** A direct `limit(50)` query, not `chunkById()`. `FinalizeElectionEvent`'s `chunkById(500, ...)` pattern exists to safely iterate a potentially-huge unbounded result set in memory-safe pages; this job's query is *already* capped at 50 by design (D-06), so a single `limit(50)->get()` is more direct and equally safe — `chunkById` would be solving a problem (unbounded memory) this query doesn't have.
**Recommended query shape**, matching the codebase's own established "fallback-sourced" definition (verified directly in `app/Filament/Widgets/FallbackSourceOverview.php`, which already defines "fallback-sourced" as `polling_place_source IS NOT NULL AND polling_place_source != 'live'`):
```php
Voter::query()
    ->whereNotNull('polling_place_source')
    ->where('polling_place_source', '!=', PollingPlaceSource::LIVE->value)
    ->whereNull('reconciliation_exhausted_at')
    ->orderBy('polling_place_resolved_at') // oldest-resolved-first: fair rotation across runs
    ->limit(50)
    ->get();
```
**Open question the plan should explicitly settle (not addressed in CONTEXT.md):** should voters with `polling_place_source IS NULL` (never resolved by *any* tier — brand new voter, no lookup ever attempted) also be eligible? The existing "fallback-sourced" definition (`FallbackSourceOverview` widget) deliberately excludes NULL. Recommendation: **keep excluding NULL** — reconciliation's job is to *upgrade* an already-fallback-resolved voter to live, not to perform first-time resolution (that is the interactive `openRegistraduriaBrowser()` flow's job, triggered by an operator). Flag this explicitly in the plan so it's a documented decision, not a silent assumption.

**Should `PollingPlaceSource::MANUAL` voters be included?** Yes, by the same established definition — and it is intentionally safe to do so: `MANUAL` has the lowest precedence (`precedence() === 3`), so a genuine `LIVE` result is never blocked by the no-downgrade guard from upgrading a `MANUAL` voter.

### Pattern 4: Reconciliation-attempt bookkeeping (D-08/D-09) — the one genuinely new logic in this job

**Critical, verified-by-existing-tests finding:** `PollingPlaceResolver::resolveAutomated()` can return three distinct shapes, and the job MUST branch on `$result->source`, not merely on null-vs-non-null:

| `resolveAutomated()` return | Verified by | What it means | Job's action on `reconciliation_attempts` |
|---|---|---|---|
| `null` | `PollingPlaceResolverTest.php` Test 17 (live unreachable, no snapshot) | Total miss — nothing resolved at all | **Increment** (failed attempt) |
| Non-null, `source === PollingPlaceSource::LIVE` | Test 15 (live succeeds first try) | Genuine live upgrade succeeded | **Reset to 0**, clear `reconciliation_exhausted_at` if set |
| Non-null, `source === PollingPlaceSource::SNAPSHOT` | **Tests 13 & 14** (live times out / gives up on `waiting_captcha` or exhausts its 5-poll backoff, cascade falls through to `resolveFromNationalSnapshot()`) | The live tier did **not** succeed — the voter is still fallback-sourced, just re-confirmed against the snapshot (which may be a true no-op if the source doesn't actually change) | **Increment** (failed attempt) — this is the resolved ambiguity flagged in the phase brief: falling through to snapshot is unambiguously a *failed* live attempt per D-08's literal wording ("5 job runs in a row where this voter's live attempt did not succeed") |

This distinction is not a hypothetical edge case — `resolveAutomated()`'s own docblock states the automated cascade is "live -> national snapshot only," meaning **every non-`LIVE` non-null return is, by construction, a live-tier failure that fell through**, never a `DB_RECONSTRUCTION` result (that tier is deliberately excluded from the automated cascade). **Getting this branch wrong (e.g., treating "non-null" as "success") would mean a voter permanently stuck on SNAPSHOT never reaches exhaustion — an infinite-retry bug that silently defeats RECON-05 while looking like it works (the job runs, "succeeds" every time, but never actually escalates to human attention).**

```php
// Illustrative — not exact plan code, but the exact branch structure the job needs:
$result = $resolver->resolveAutomated($voter->document_number, $voter);

if ($result !== null && $result->source === PollingPlaceSource::LIVE) {
    $voter->update(['reconciliation_attempts' => 0, 'reconciliation_exhausted_at' => null]);
} else {
    $attempts = $voter->reconciliation_attempts + 1;
    $voter->update([
        'reconciliation_attempts' => $attempts,
        'reconciliation_exhausted_at' => $attempts >= 5 ? now() : null,
    ]);
}
```

### Pattern 5: Migration shape (D-09), matching existing house style

**Verified directly** against `database/migrations/2026_07_24_130001_add_polling_place_source_to_voters_table.php` (the most recent, directly analogous "add columns to `voters`" migration):
```php
// Source: database/migrations/2026_07_24_130001_add_polling_place_source_to_voters_table.php (existing style)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->unsignedInteger('reconciliation_attempts')->default(0)->after('polling_place_resolved_at');
            $table->timestamp('reconciliation_exhausted_at')->nullable()->after('reconciliation_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn(['reconciliation_attempts', 'reconciliation_exhausted_at']);
        });
    }
};
```
Add both new columns to `Voter::$fillable` and to `casts()` (`'reconciliation_exhausted_at' => 'datetime'`) — `Voter.php`'s `casts()` method already lists `polling_place_resolved_at` right next to where these belong.

### Anti-Patterns to Avoid
- **Using `chunkById()` for a query that's already `limit(50)`-bounded** — solves a non-existent problem and obscures the actual bound (D-06's discretion note itself flags this).
- **Treating any non-null `resolveAutomated()` result as "success"** — see Pattern 4 above; this is the single most consequential mistake available in this phase's logic.
- **Constructing the Job with `PollingPlaceResolver` in the constructor** — breaks queue serialization; use `handle()` method injection instead (see Pattern 1).
- **Guessing the wsp HTML table schema without a real captured sample** — see Open Questions; a parser written against a guessed schema will pass unit tests against fabricated fixtures and then silently fail (return all-empty fields) against the real site, because `resolveOrCreatePollingPlace()`'s municipality lookup (`whereRaw('LOWER(name) = ?', ...)`) will simply find nothing and the voter stays fallback-sourced forever with zero visible error.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Parsing the wsp `<table id="consulta">` HTML | Regex against `<th>`/`<td>` tags, or manual string-splitting on `<td>`/`</td>` | PHP's bundled `DOMDocument` + `DOMXPath` (`$xpath->query("//table[@id='consulta']//tbody/tr[1]/td")`) | HTML (even simple table markup) is not regular; a `DOMDocument` parser handles nested tags, whitespace, and entity-encoding (`&aacute;`, etc. — Registraduría's own JS uses HTML entities for accented characters, confirmed in `functions.js`) correctly where regex will eventually break on some cédula's data (e.g., an address containing a literal `<` or unusual whitespace) |
| Reachability check | A custom TCP/socket-level "is the host up" check | `Http::get($probeUrl)` (already the codebase's pattern in `isReachable()`, just needs the verb/URL fix) | The existing `isReachable()` contract and its Pest test suite (`RegistraduriaServiceReachabilityTest.php`) already established the pattern (kill-switch check, connect/timeout limits, `Http::fake()`-testable) — only the URL and HTTP verb need to change, not the pattern |
| Reconciliation-attempt bookkeeping / exhaustion | A separate `reconciliation_state` table, a Redis counter, or a dedicated state-machine package | Two plain columns on `voters` (D-09) | This is explicitly the locked decision (D-09) — the simplest possible representation (an int counter + nullable timestamp) is sufficient and matches the existing `polling_place_resolved_at` column's plain-timestamp style |
| Campaign scoping inside the job | Manually calling `CampaignContext::setCampaignId($voter->campaign_id)` per voter, or looping per-campaign | Nothing extra — rely on the verified fact that `CampaignContextScope` no-ops without an authenticated user (see Common Pitfalls #3) | Adding manual scoping code would be *solving a problem that doesn't exist* and risks accidentally introducing an ambient-context dependency the requirement (RECON-02) explicitly wants to avoid |

**Key insight:** almost nothing genuinely new needs to be built in the job/scheduling half of this phase — the existing `PollingPlaceResolver::resolveAutomated()`, `FinalizeElectionEvent`'s job shape, and `birthday:dispatch-webhooks`'s scheduling entry are all directly reusable patterns. The only genuinely new logic is the two-column attempt/exhaustion bookkeeping (Pattern 4) and the HTML parser (D-02), and the parser's biggest risk isn't *how* to parse HTML (solved, standard tooling) but *what* to parse it into, which is still unknown (see Open Questions).

## Common Pitfalls

### Pitfall 1: Reachability probe uses `HEAD`, which this specific site always rejects with `500`
**What goes wrong:** `isReachable()` currently does `Http::...->head(config('services.registraduria.probe_url'))`. Fixing only the URL (dead domain → `wsp.registraduria.gov.co`) while keeping the `HEAD` verb would make `isReachable()` return `false` on every single call, because this specific site's backend errors on `HEAD` requests.
**Why it happens:** Verified directly, repeatedly, from this research environment: `curl -I https://wsp.registraduria.gov.co/censo/consultar/` → `HTTP_CODE:500` (4/4 attempts, including a `HEAD` to the bare domain root `https://wsp.registraduria.gov.co/`). The identical URL via plain `GET` → `HTTP_CODE:200` (5/5 attempts), serving the real form page (confirmed by grepping the body for `g-recaptcha`/`consulta` markers), even with `curl`'s default (non-browser) User-Agent — no WAF block triggered on a simple `GET`.
**How to avoid:** Change `isReachable()` to issue a `GET` (not `HEAD`) request to the probe URL. Keep the existing `connectTimeout(2)->timeout(3)` limits (verified: real response times were ~0.15-0.20s, well within these limits) — this stays "cheap" (no captcha cost, small static HTML page, no browser/Playwright launch).
**Warning signs:** If `isReachable()` is tested only with `Http::fake()` mocks (as `RegistraduriaServiceReachabilityTest.php` currently does), this bug would NOT be caught by the existing test suite at all — the fake doesn't care about verb-specific real-world backend quirks. The plan should update/add a test that asserts the code calls `Http::get()` (not `->head()`) against the configured probe URL, since that is the actual, verified, load-bearing behavior difference for this specific host.
**Confidence:** HIGH — reproduced 100% consistently (9/9 total requests: 4 HEAD→500, 5 GET→200) directly against the live production endpoint during this research pass, 2026-07-26.

### Pitfall 2: `withoutOverlapping()`'s `$expiresAt` parameter is in minutes, not seconds
**What goes wrong:** D-10 specifies a "10 minute" lock expiry. If the plan or implementation reads this as `->withoutOverlapping(600)` (mentally converting to seconds, a common habit from other timeout APIs in this same codebase, e.g. `Http::timeout()`, `Sleep::for()->milliseconds()`), the actual lock would last 600 *minutes* (10 hours) — the opposite of RECON-06's intent, and a much worse stuck-lock scenario than doing nothing.
**Why it happens:** Verified directly against `vendor/laravel/framework/src/Illuminate/Console/Scheduling/ManagesAttributes.php` (and `CallbackEvent.php`, `PendingEventAttributes.php` — all three define the same signature): `public function withoutOverlapping($expiresAt = 1440)`, default 1440 **minutes** (24 hours), confirmed by Laravel's own official docs example (`Schedule::command('emails:send')->withoutOverlapping(10);` → "sets the lock expiration to 10 minutes").
**How to avoid:** `->withoutOverlapping(10)` is the correct call for a 10-minute expiry. Do not multiply by 60.
**Warning signs:** A stuck run appearing to hold its lock for many hours instead of ~10 minutes; check via `php artisan schedule:list` or by inspecting the cache lock key's TTL.
**Confidence:** HIGH — read directly from the installed `vendor/laravel/framework` source (v12.36.1), cross-confirmed by Laravel's official docs example.

### Pitfall 3: Assuming the job needs manual campaign-scoping code (it doesn't — but this must be tested, not assumed)
**What goes wrong:** A developer might defensively add `CampaignContext::setCampaignId(...)` calls per voter, or wrap the query per-campaign, "just to be safe" about RECON-02 — this is unnecessary and could actually reintroduce the exact ambient-context risk RECON-02 warns against (a static override left set across the job's lifetime, e.g. if the job somehow runs on a long-lived worker process that later handles something else).
**Why it happens (verified, not assumed):** Read directly: `Voter` uses `HasCampaignContext`, which registers `CampaignContextScope` as a global scope. `CampaignContextScope::apply()`'s very first behavior is `$campaignId = CampaignContext::currentCampaignId(); if (! $campaignId) { return; }` — i.e., **no filter is applied at all** when there's no resolvable campaign ID. `CampaignContext::currentCampaignId()`'s very first line is `$user = $user ?? Auth::user(); if (! $user) { return null; }` — with `QUEUE_CONNECTION=database` (confirmed in `.env`), queued jobs run in a separate `queue:work` process with no HTTP session and no authenticated user, so `Auth::user()` is `null`, so the scope no-ops, so the job's query naturally sees voters across every campaign with zero extra code.
**How to avoid:** Write the job's query as a plain `Voter::query()->...` with no campaign-context manipulation at all. Add an explicit **regression test**: create voters in two different `Campaign` records (no `actingAs()` in the test), run the job, and assert voters from both campaigns were considered/updated — this converts a currently-implicit, easily-broken-by-refactor invariant into an explicit, enforced one.
**Warning signs:** If a future refactor changes `CampaignContextScope` to have a different fallback behavior (e.g., "if no campaign, show nothing" instead of "if no campaign, show everything"), this job would silently stop reconciling any voters at all with no error — the regression test above is what would catch that.
**Confidence:** HIGH — verified by reading `CampaignContextScope.php`, `CampaignContext.php`, `.env`'s `QUEUE_CONNECTION=database`, and `Voter.php`'s trait usage directly, not inferred.

### Pitfall 4: A SNAPSHOT-sourced fallthrough from `resolveAutomated()` is a *failed* attempt, not a success
Covered in full in Architecture Pattern 4 above — restated here because it's the single highest-consequence pitfall in this phase's own new logic (not inherited from existing code): getting this wrong produces a reconciliation job that runs "successfully" forever without ever exhausting a permanently-unresolvable voter, silently defeating RECON-05 while all surface-level signals (job completes, no errors logged) look healthy.

### Pitfall 5: `attemptLiveAutomated()`'s `waiting_captcha` branch is effectively dead code against the current wsp/Python backend — do not rely on it as RECON-05's "human captcha interaction" trigger
**What goes wrong:** RECON-05's wording ("or requires human captcha interaction the job can't complete") reads as if it maps directly to `attemptLiveAutomated()`'s existing `$result['status'] === 'waiting_captcha'` give-up branch. Read directly, `registraduria-service/app.py`'s session status vocabulary (`pending`, `solving_captcha`, `waiting_result`, `done`, `error`) **never emits `waiting_captcha`** — the wsp flow's captcha is solved entirely automatically via the 2captcha API (no human-in-the-loop state at all for this specific adapter). The `waiting_captcha` branch appears to be defensive/inherited code (possibly relevant to a future different adapter, or a leftover concept from the old interactive-modal flow), not something the wsp adapter will ever actually return.
**Why it happens:** The phase's own wording assumes a scenario (human captcha interaction) that doesn't apply to *this* live adapter's actual automated (2captcha-solved) design — RECON-05's terminal-state requirement is still fully satisfied by D-08's 5-strike counter regardless of *which* failure mode caused each attempt to fail (timeout, `denied_by_score`, `not_found`, `source_unreachable`, or a hypothetical future `waiting_captcha`), so this doesn't block anything — it's purely a documentation/understanding gap worth flagging so the plan doesn't over-invest in `waiting_captcha`-specific handling as if it were the primary exhaustion trigger.
**How to avoid:** Treat D-08's 5-consecutive-non-LIVE-attempts counter as the *only* mechanism needed for RECON-05 — it already covers every failure shape uniformly (Pattern 4 above), including a hypothetical future `waiting_captcha` state if a different adapter ever introduces one.
**Confidence:** HIGH — read directly from `registraduria-service/app.py`'s `_lookup_async()` (the only status values ever `_set()`: `"pending"` implicitly at session creation, `"solving_captcha"`, `"waiting_result"`, `"done"`, `"error"` — no `"waiting_captcha"` anywhere in the file).

### Pitfall 6: The wsp `#consulta` HTML table's real column structure is unknown — do not guess it
**What goes wrong:** Writing a parser (D-02) against an assumed/guessed column order (e.g., assuming `NUIP, Puesto, Mesa, Zona, Departamento, Municipio, Dirección` in that order, by analogy with the old, *different-site* label-value parser) risks silently parsing wrong columns into wrong fields — this would not throw an error; it would populate `municipio` with, say, a mesa number, and `resolveOrCreatePollingPlace()`'s municipality lookup would simply fail to match anything, silently falling through with a `null` `pollingPlaceId` while `polling_place_source` still gets set to `LIVE` (a worse outcome than staying on SNAPSHOT — a confidently-wrong "verified" flag).
**Why it happens:** Every available non-live-spend discovery avenue was exhausted this pass (see Open Questions) and none revealed the real column order/count.
**How to avoid:** The plan's first task must spend one real, budgeted 2captcha lookup specifically to capture and log the full HTML response before the parser is written — see Open Questions for the concrete recommendation.
**Confidence:** HIGH on the fact that this is unknown; N/A on the actual structure (that's the point).

## Code Examples

### Reachability fix (D-01) — verb change only, pattern preserved
```php
// Source: app/Services/RegistraduriaService.php (existing, to be modified)
public function isReachable(): bool
{
    if (! config('services.registraduria.live_enabled')) {
        return false;
    }

    try {
        // CHANGED: ->head() -> ->get() — this specific host (wsp.registraduria.gov.co)
        // returns a bare HTTP 500 on every HEAD request (verified directly, 2026-07-26,
        // 4/4 repeated attempts including the bare domain root), while GET reliably
        // returns 200 with the real page (5/5 attempts). See 11-RESEARCH.md Pitfall 1.
        $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->get(config('services.registraduria.probe_url'));

        return $response->successful() || $response->redirect();
    } catch (ConnectionException) {
        return false;
    }
}
```
```php
// Source: config/services.php (existing, to be modified) — update BOTH the .env value
// AND this fallback default, so tests/environments without an explicit .env entry
// don't silently fall back to the dead domain.
'registraduria' => [
    'url' => env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757'),
    'live_enabled' => env('REGISTRADURIA_LIVE_ENABLED', true),
    'probe_url' => env('REGISTRADURIA_PROBE_URL', 'https://wsp.registraduria.gov.co/censo/consultar/'),
],
```
```bash
# .env — update the actual running value too
REGISTRADURIA_PROBE_URL=https://wsp.registraduria.gov.co/censo/consultar/
```

### Existing "fallback-sourced" definition to reuse verbatim (D-06 query, verified in-repo)
```php
// Source: app/Filament/Widgets/FallbackSourceOverview.php (existing, Phase 10) — reuse this
// exact predicate for consistency across the codebase's notion of "fallback-sourced":
->whereNotNull('polling_place_source')
->where('polling_place_source', '!=', PollingPlaceSource::LIVE->value)
```

### Illustrative PHP-side parser skeleton (D-02/D-03) — SCHEMA PLACEHOLDER, do not implement until Task 1 captures a real sample
```php
// ILLUSTRATIVE ONLY — column indices/selectors below are UNVERIFIED placeholders.
// Do not write this for real until a genuine wsp success response has been captured
// (see Open Questions). Shown here only to demonstrate the DOMDocument/DOMXPath approach.
$dom = new \DOMDocument();
libxml_use_internal_errors(true); // Registraduría's HTML may not be strictly well-formed
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $rawMessageHtml);
libxml_clear_errors();

$xpath = new \DOMXPath($dom);
$rows = $xpath->query("//table[@id='consulta']/tbody/tr");
// UNVERIFIED: which <td> index maps to which field — must be confirmed against a real
// captured response (see Open Questions) before this can be written for real.
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| `apiweb-eleccionescolombia.infovotantes.com` — plain-text label→value page layout (`Puesto` / `02 - IE SAN JOSE C I P` / `Mesa` / `13` / ... on consecutive lines), parsed by a hand-rolled positional text parser (`_parse_result_text`, commit `2ceb148`, 2026-05-08) | `wsp.registraduria.gov.co/censo/consultar/` — reCAPTCHA-gated, returns an HTML `<table id="consulta">` fragment | Phase 9 (2026-07-25), per the domain going dark (both old endpoints found decommissioned, per `.planning/STATE.md`'s 2026-07-23 note) | The **field vocabulary** (Puesto/Mesa/Zona/Departamento/Municipio/Dirección) is corroborated as Registraduría's standing convention across two different systems — useful circumstantial evidence for what the new table's column *labels* likely resemble — but the **document shape** (structured HTML table vs. plain interleaved text) is completely different, so the old parser's code cannot be reused, only its field-name vocabulary as a hint |

**Deprecated/outdated:**
- The old `infovotantes` label/value text parser (`_parse_result_text` in `app.py`'s pre-Phase-9 history) — fully superseded, target site is dead (no DNS record, confirmed 2026-07-23 per STATE.md).
- `REGISTRADURIA_PROBE_URL` currently still pointing at the old dead domain — this phase's D-01 is exactly this update.

## Open Questions

1. **What is the exact column structure of the wsp `<table id="consulta">` success response?**
   - What we know: The table exists (`id="consulta"`, classes `display responsive table table-bordered table-striped` — a DataTables-styled table, though no DataTables JS initialization was found in `functions.js`, so this is purely server-rendered static markup, not client-enhanced). The first header cell is `NUIP` (matches the submitted `nuip` field name). Phase 9's spike log captured only the first ~130-200 characters of 29 real success responses — all cut off mid-second-`<th>` (`<th class='text-cente...`).
   - What's unclear: The full column count, all header labels, whether `mesa`/`zona` are separate columns or combined, whether there's one `<tr>` per result or multiple (e.g., if a cédula has voted in more than one historical election, could there be multiple rows?), and the exact HTML entity/whitespace formatting of values.
   - What was tried this pass (all zero-cost, no live spend): (a) repo-wide grep for any other captured sample/log — none found (`service.log` contains only access-log lines with no response bodies); (b) `WebFetch` of `https://wsp.registraduria.gov.co/censo/consultar/` directly — confirmed only the search *form*, no sample result table is rendered on the bare page; (c) fetched `functions.js` directly — confirmed there is no DataTables `.DataTable({columns: [...]})` initialization anywhere in that file that would reveal a column-to-field mapping; (d) reviewed this project's own prior (different-site) label/value parser (git history, commit `2ceb148`) as circumstantial vocabulary evidence only.
   - Recommendation: **the plan's first task must be a dedicated, budgeted spend of exactly one real 2captcha-solved lookup against `wsp.registraduria.gov.co` (reusing the already-running `registraduria-service/app.py`, which already returns `raw_message_html` unmodified), with the *sole* purpose of logging the complete, untruncated HTML response to a file** (e.g., append it to a new `registraduria-service/samples/consulta-sample.html` fixture, or a dedicated debug log line with no truncation) before writing a single line of parser code. Only after this sample is captured should the parser's `DOMXPath` selectors be written and unit-tested against it as a Pest fixture. Do not let the plan schedule the parser-writing task before this capture task, and do not let the parser task's acceptance criteria be satisfied by tests against a fabricated/guessed fixture alone.

2. **Should `PollingPlaceSource::MANUAL`-sourced and never-resolved (`NULL`-sourced) voters be included in the reconciliation job's eligibility query?**
   - What we know: CONTEXT.md is silent on this. The existing "fallback-sourced" definition (`FallbackSourceOverview` widget, Phase 10) includes `MANUAL` and excludes `NULL`.
   - What's unclear: Whether the user/planner wants NULL-sourced (never-attempted) voters swept into automated reconciliation too, expanding the job's scope beyond "upgrade existing fallback data" to "also perform first-time resolution for voters no one has ever looked up."
   - Recommendation: Default to reusing the exact existing `FallbackSourceOverview` predicate (include MANUAL, exclude NULL) for consistency and the narrowest reading of RECON-01's "currently on fallback-sourced data" wording — but the plan should state this explicitly as a decision, not leave it implicit.

3. **Does the WAF ever affect a plain, infrequent (hourly) `GET` reachability probe over time, even though it did not during this research pass's testing?**
   - What we know: 9/9 direct requests during this research pass (4 HEAD, 5 GET) behaved consistently and predictably; no WAF block page was triggered by any GET.
   - What's unclear: Whether an hourly cadence over weeks/months could ever trip WAF-side rate-limiting or behavioral heuristics tuned against the *specific traffic pattern* of one prod server GET-ing this URL every hour indefinitely (as opposed to Phase 9's short, bursty spike traffic).
   - Recommendation: Not a blocker for this phase — but worth a one-line defensive note in the job's `isLiveReachable()`/circuit-breaker logging: log the raw response status/body-shape (JSON vs HTML) on every reachability check at `debug` level, so a future WAF-posture change would show up in logs rather than as a silent, confusing "always unreachable" regression.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP `ext-dom` | D-02/D-03 parser (if PHP-side, recommended) | Yes | Bundled w/ PHP 8.4.14 | — |
| `registraduria-service` Flask app (port 5757) | The job's live-tier attempts (via `RegistraduriaService`) | Not confirmed running at research time — `service.log`'s last entries are from `2026-07-25 12:24` (a prior session); no live check performed this pass to avoid an unnecessary/costly lookup | — | Must be running (`python app.py` or the Dokploy-deployed container) for the live tier to ever succeed; if down, `startLookup()` throws and `attemptLiveAutomated()`'s catch already handles this gracefully (falls through to snapshot) — no code change needed, this is existing, already-tested behavior |
| `wsp.registraduria.gov.co` reachability | Everything in D-01/D-02 | Yes — verified directly this pass (GET: 5/5 succeeded, HTTP 200) | — | None needed — confirmed live and responding correctly to GET |
| 2captcha account/balance | Any real live-tier attempt, including the Open-Question-1 capture task | Assumed available (per Phase 9's confirmed working key) — not re-verified this pass (would require live spend) | — | If balance is exhausted, `in.php`'s submit response (`status=0`) is already how `app.py` detects and surfaces this — the capture task (Open Question 1) will discover this immediately if it's an issue |
| Production `sigma-registraduria` container (korserver/Dokploy) | Eventual production deployment of this phase's work | Per `.planning/STATE.md`'s Blockers/Concerns: still running OLD hardcoded-2captcha-key code, needs a redeploy to pick up commit `ac1dd5a` + `TWO_CAPTCHA_KEY` env var | — | Out of scope for this phase's planning (a deployment/ops concern), but the plan should note that this phase's changes will need that same redeploy to take effect in production |

**Missing dependencies with no fallback:**
- None identified that would block *planning* this phase. The one open item (Open Question 1's real captured HTML sample) is a research/discovery gap to be closed by the plan's first task, not a missing tool/library.

**Missing dependencies with fallback:**
- `registraduria-service` process state (running or not) — trivial to (re)start, not a capability gap.

## Sources

### Primary (HIGH confidence — read/tested directly this pass)
- `app/Services/RegistraduriaService.php`, `app/Services/PollingPlaceResolver.php`, `app/Services/LiveSourceAdapter.php`, `app/Services/PollingPlaceResolutionResult.php` — read directly.
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` — read directly.
- `app/Jobs/FinalizeElectionEvent.php`, `app/Console/Commands/DispatchBirthdayWebhooks.php`, `routes/console.php` — read directly (existing house patterns).
- `app/Models/Voter.php`, `app/Models/Concerns/HasCampaignContext.php`, `app/Models/Scopes/CampaignContextScope.php`, `app/Services/CampaignContext.php` — read directly; the RECON-02-satisfying-by-construction finding is derived entirely from these four files.
- `app/Filament/Widgets/FallbackSourceOverview.php` — read directly; source of the reused "fallback-sourced" query predicate.
- `tests/Feature/Services/PollingPlaceResolverTest.php`, `tests/Feature/Services/RegistraduriaServiceReachabilityTest.php` — read directly; Tests 13, 14, 15, 17 are the direct evidence for Pattern 4's branch logic.
- `registraduria-service/app.py`, `registraduria-service/requirements.txt`, `registraduria-service/service.log` — read directly.
- `config/services.php`, `.env` (`REGISTRADURIA_*`, `QUEUE_CONNECTION`) — read directly.
- `database/migrations/2026_07_24_130001_add_polling_place_source_to_voters_table.php`, `.../2026_07_24_130002_create_polling_place_resolutions_table.php` — read directly (migration style precedent).
- `vendor/laravel/framework/src/Illuminate/Console/Scheduling/{Event,ManagesAttributes,ManagesFrequencies,CallbackEvent,PendingEventAttributes}.php` — read directly, v12.36.1 (verified via `composer show laravel/framework`); source of the `withoutOverlapping($expiresAt = 1440)`-is-minutes finding.
- Direct live `curl` testing against `https://wsp.registraduria.gov.co/censo/consultar/` and its bare domain root, 2026-07-26 (this research session): 4/4 `HEAD` → `500`; 5/5 `GET` → `200` with real page content, confirmed via `curl -A` with both a spoofed and the default `curl` User-Agent (no WAF block triggered either way for a plain GET).
- `git log`/`git show` on `registraduria-service/app.py`'s history (commits `029345a`, `2ceb148`) — the old `infovotantes`-targeting label/value parser, reviewed as circumstantial vocabulary evidence for Registraduría's field-naming conventions (Puesto/Mesa/Zona/Departamento/Municipio/Dirección), explicitly NOT as proof of wsp's table structure.
- `php -m`, `php -r "var_dump(class_exists('DOMDocument'));"` — confirmed `ext-dom` bundled, zero new Composer dependency needed.
- `.planning/phases/09-live-source-feasibility-spike/09-SPIKE-RESULTS.md`, `09-RESEARCH.md` — read directly (existing WAF/session/contract findings, and the truncated HTML sample that motivated Open Question 1).
- `.planning/REQUIREMENTS.md`, `.planning/STATE.md`, `.planning/phases/11-scheduled-reconciliation-job/11-CONTEXT.md` — read directly per this task's mandatory initial read.

### Secondary (MEDIUM confidence)
- `WebFetch` of `https://wsp.registraduria.gov.co/censo/consultar/` and `.../public/js/functions.js` this pass — confirmed no sample result table or DataTables column config is present anywhere in the live, public-facing form page or its JS, but this is a negative result (absence of evidence), not a definitive proof that no such documentation exists anywhere; treated as MEDIUM since it's a live fetch of the actual real target, not a general web search.
- Laravel's official 12.x scheduling docs (`laravel.com/docs/12.x/scheduling`, via WebSearch) — corroborates the vendor-source-verified `withoutOverlapping($expiresAt)`-in-minutes finding with an official documented example.

### Tertiary (LOW confidence / not found)
- No public documentation of `wsp.registraduria.gov.co`'s exact result-table schema was found anywhere (expected — this is an internal government system's rendered output, not a documented public API).

## Metadata

**Confidence breakdown:**
- Job/scheduling/migration mechanics (RECON-02 through RECON-06): HIGH — directly precedented by existing, working code in this exact codebase, cross-verified against installed Laravel vendor source.
- D-01 reachability fix: HIGH — reproduced via direct, repeated live testing against the actual production endpoint during this research pass.
- D-02 HTML parser: LOW on the target schema (genuinely unknown, not guessable responsibly), HIGH on the parsing *technique* recommendation (DOMDocument/DOMXPath, zero new dependencies) once a real sample exists.
- D-03 parser location: HIGH — decisively supported by the verified absence of any HTML-parsing dependency in `registraduria-service/requirements.txt` versus PHP's confirmed-bundled `ext-dom`.

**Research date:** 2026-07-26
**Valid until:** The job/scheduling/migration findings are stable (versioned framework code, ~90+ days). The D-01 reachability finding and D-02's "still unknown" status are tied to a live, unversioned government endpoint and its current WAF/backend posture — treat as valid for **this specific planning/execution window only**; if execution is delayed more than 1-2 weeks, re-verify the HEAD/GET behavior with a fresh `curl` check before finalizing the plan's `isReachable()` task.

---
*Research for: Phase 11 - Scheduled Reconciliation Job*
*Researched: 2026-07-26*
