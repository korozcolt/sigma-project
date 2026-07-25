# Phase 8: Resilient PollingPlaceResolver Service - Research

**Researched:** 2026-07-24
**Domain:** Laravel service-layer extraction (adapter/strategy pattern), resilient outbound HTTP with bounded backoff, source-precedence guards, Pest-testable async polling
**Confidence:** HIGH (grounded directly in the SIGMA codebase + verified current Laravel 12.x docs)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions


### Tier Order — Interactive Path
- **D-01:** For the interactive (Filament UI, operator-driven) lookup: **Cache → Live → DB reconstruction (campaign `census_records`) → National snapshot**. Cache is checked first purely to avoid re-paying for a cédula already resolved before (30-day TTL, unchanged from today). If no cache hit, the resolver attempts **live first** — the user explicitly prioritized data reliability/freshness over cost ("ya sabemos que el costo es mayor pero tenemos la fiabilidad de que la información va correcta"). Only when live fails/is unreachable does it fall through to DB reconstruction, then national snapshot.
- **D-02:** No change to the existing 30-day cache TTL — sufficient freshness margin; no additional expiration/re-verification logic needed this phase.

### Tier Order — Automated/Headless Path
- **D-03:** For automated/headless callers (what Phase 11's reconciliation job will call through this resolver): **Live → snapshot** (DB reconstruction and national snapshot collapse into "the non-live fallback" in this direction — the whole point of reconciliation is upgrading already-snapshot-flagged voters to live, so live must be attempted first, unconditionally, every time).

### Reachability & Cost Guard
- **D-04:** Before attempting any real (paid) live lookup, the resolver performs a cheap reachability probe (DNS/HTTP HEAD, no captcha cost) against the configured Registraduría service. If unreachable, skip straight to fallback — never pay/wait on a call that's guaranteed to fail. Both current live domains are confirmed DNS-dead as of this milestone.
- **D-05:** Add a config-level kill switch (e.g. `config('services.registraduria.live_enabled')`, backed by a new `REGISTRADURIA_LIVE_ENABLED` env var, default `true`) that fully disables the live tier — skip straight to DB/snapshot — when set to `false`. Lets ops flip live attempts back on the moment Phase 9 ships a working adapter, without a code deploy.

### Automated "Never Blocks" Behavior (LIVE-03)
- **D-06:** In automated mode, poll the live service **3–5 times with short backoff** before giving up and treating the source as unavailable.
- **D-07:** If a poll returns `waiting_captcha` (a human needs to click through), the automated path treats this as "not automatable right now" and falls back to snapshot/DB **immediately** — it does not keep polling hoping a human intervenes. This is a hard rule, not a timeout-driven one: `waiting_captcha` itself is the give-up signal in automated mode.
- **D-08:** Maximum total wall-clock time for a single automated live attempt (probe + polls) is a few seconds (target: well under 10s) — the lookup must feel instant to an interactive caller and must never stall a queue worker.

### Operator-Visible Behavior (Scope Guard)
- **D-09:** The Filament voter form's existing two actions — auto lookup (`openRegistraduriaBrowser`) and force-refresh (`forceRefreshFromRegistraduria`, the "Actualizar datos" button) — keep **pixel-identical behavior and wording** after the refactor. Only the internal implementation changes (delegating to `PollingPlaceResolver` instead of owning the cascade). Any operator-visible source badges, filters, or notification wording changes belong to Phase 10.
- **D-10:** The "Actualizar datos" force-refresh action continues to go **straight to live**, bypassing cache/DB/snapshot, exactly as today — and it is **not** subject to the new no-downgrade guard (SRC-02). The guard exists to stop *automatic* silent downgrades (Pitfall 10); it must never block a human's deliberate, explicit refresh request, even one that could in principle re-confirm an already-`live`-sourced record.

### Audit-Row Write Granularity
- **D-11:** The resolver writes a `PollingPlaceResolution` audit row **only when the resolved source or polling place actually changes** (a real transition) — not on every `resolve()` call. A cache hit or a live lookup that re-confirms the exact same source/place produces **no new audit row**.
- **D-12:** Re-verification with no change still updates `voters.polling_place_resolved_at` (the "last confirmed" timestamp) even though it does not append a new `polling_place_resolutions` row — the current-state column reflects freshness; the audit table reflects transitions only.

### No-Downgrade Guard (SRC-02)
- **D-13:** Source precedence is `live` > `db_reconstruction` > `snapshot` > `manual` (per Phase 7's `PollingPlaceSource` enum ordering and ARCHITECTURE.md Pitfall 1/10 guidance). An **automatic** resolver call must never overwrite a higher-precedence existing source with a lower-precedence result — e.g. a snapshot/DB-tier result must never silently replace an already-`live`-flagged voter. This guard applies to the automatic cascade only (see D-10 for the explicit-operator-override exception).


### Claude's Discretion
- Exact shape of the `PollingPlaceResolution` value object (fields beyond `source`, `pollingPlaceId`, `tableNumber`, `resolvedAt` returned by `PollingPlaceResolver::resolve()`) — follow ARCHITECTURE.md's recommended VO shape.
- Exact backoff timing between the 3–5 automated polls (D-06) — any short, monotonic or capped-exponential backoff that keeps total wall-clock under the D-08 ceiling is acceptable.
- Whether the reachability probe (D-04) is a raw DNS resolution check, a lightweight HTTP HEAD/GET against the Python service's health endpoint, or reuses `RegistraduriaService`'s existing HTTP client with a short timeout — pick whichever fits Laravel conventions cleanly and is fast/cheap.
- Exact refactor mechanics of `HasRegistraduriaPolling` (how much logic physically moves into the new service vs. stays as a thin delegating call) — as long as D-09's behavior-identical requirement holds and the cascade is expressed exactly once in the new service.

### Deferred Ideas (OUT OF SCOPE)


None — discussion stayed within phase scope. The reconciliation job's budget/backoff/terminal-state mechanics (Pitfalls 6/7/11), the `wsp.registraduria.gov.co` feasibility spike (Pitfall 8), and any operator-visible source badge/filter UI (Phase 10) were not re-litigated here; they remain scoped to Phases 9/10/11 respectively.
</user_constraints>

## Summary

This phase is a **refactor + hardening** job, not new-technology adoption. Every piece needed already exists in the codebase in some form — the job is to (1) extract the cascade from `HasRegistraduriaPolling` into `app/Services/PollingPlaceResolver.php`, (2) add a reachability probe + kill switch gate before any live attempt, (3) add a bounded automated-poll mode that never hangs on `waiting_captcha`, and (4) add a no-downgrade precedence guard using the `PollingPlaceSource` enum that Phase 7 already shipped.

**The single most important finding of this research**: the reachability probe (D-04) **cannot be satisfied by calling `RegistraduriaService::startLookup()` and catching failure**, and it also **cannot be satisfied by probing the Python microservice's base URL**. Reading `registraduria-service/app.py` directly shows `/lookup` returns `{"session_id": ...}` with HTTP 200 **immediately** (it spawns a background thread and returns), regardless of whether the real Registraduría domain is reachable. The 2captcha solve (the paid step) happens **first**, independent of whether the target site is up — the actual site is only contacted *after* the token is solved, deep inside the async thread. So probing `/lookup`, or even calling it, tells you nothing about reachability and does not avoid the cost. The two real domains this is meant to detect (`eleccionescolombia.registraduria.gov.co`, `apiweb-eleccionescolombia.infovotantes.com`) are **hardcoded inside the Python service** and not present anywhere in Laravel's `config/services.php`. The resolver's reachability probe must therefore check one of these targets directly (DNS or HTTP HEAD from PHP against a newly-added config value), not delegate to `RegistraduriaService`. This is flagged in detail below and must be resolved in the plan.

**Primary recommendation:** Extract the cascade into `app/Services/PollingPlaceResolver.php` as a plain constructor-injected service (no new base folder), define a small `LiveSourceAdapter` interface living alongside it in `app/Services/` (not a new `Contracts/` directory — avoids the "no new base folders without approval" constraint), add a `precedence(): int` method to the existing `PollingPlaceSource` enum for the no-downgrade guard, and use Laravel's `Sleep` facade (fakeable, assertable) for the bounded automated-poll backoff so tests run in milliseconds with zero real waiting.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| CENSO-01 | Resolve a voter's polling place from the national census snapshot when live is unavailable | `NationalCensusRecord` (Phase 6) is the read target for the resolver's final tier; ARCHITECTURE.md Decision 3 confirms tier order; ordering already locked in CONTEXT.md D-01/D-03 |
| SRC-02 | Never silently downgrade a live-verified result with an older snapshot result | `PollingPlaceSource` enum precedence pattern (below) + guard placement inside `PollingPlaceResolver::resolve()`, gated on D-10's explicit-override exception |
| LIVE-01 | Multiple interchangeable live-source adapters tried in priority order, addable without redesign | `LiveSourceAdapter` interface + array-of-adapters constructor injection pattern (below); config-driven adapter list |
| LIVE-03 | Lookup workflow never blocks on an unreachable live source | Reachability-probe-must-be-independent finding (Summary above) + bounded `Sleep`-based polling with `waiting_captcha` as an immediate give-up signal (D-06/D-07/D-08) |
</phase_requirements>

## Standard Stack

### Core (already in the project — no new dependencies)

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|---------------|
| `Illuminate\Support\Facades\Http` | Laravel 12.x | Reachability probe (HTTP HEAD/GET with short `timeout`/`connectTimeout`), and the existing `RegistraduriaService` HTTP client | Already used by `RegistraduriaService`; `Http::fake()` with sequences is the established test pattern (`RegistraduriaControllerTest.php`) |
| `Illuminate\Support\Sleep` | Laravel 12.x (stable since 10.x) | Bounded backoff between automated polls (D-06/D-08) | Purpose-built replacement for `usleep()`/`sleep()` — `Sleep::fake()` makes backoff tests instant and assertable; native PHP `sleep()` would make every test actually pause for real seconds |
| `Illuminate\Support\Facades\Cache` (Redis) | Laravel 12.x | Existing 30-day cédula cache tier, unchanged (D-02) | Already established, `registraduria:cedula:{cedula}` key pattern |
| Backed enum + Filament contracts (`HasColor`, `HasLabel`, etc.) | PHP 8.4 / Filament 4 | `PollingPlaceSource` precedence source of truth | Already shipped in Phase 7; this phase only adds a `precedence()` method |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP native `checkdnsrr()` | PHP 8.4 (built-in) | Cheapest possible reachability signal — pure DNS resolution check, no HTTP round trip | First-line probe if you want sub-network-round-trip speed; wrap in an injectable class so it's mockable in tests (raw global functions can't be faked directly) |
| `Illuminate\Http\Client\ConnectionException` / `Http::failedConnection()` | Laravel 12.x | Simulates a DNS-dead/unreachable host in tests | Use in Pest tests to fake the probe's negative case without touching real DNS |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `Sleep::for(...)->milliseconds()` backoff | Laravel `retry()` helper / `Http::retry()` | `Http::retry()` only wraps a single HTTP call's retry loop and doesn't fit "poll a session_id up to 5 times with a give-up-on-waiting_captcha rule" — the domain logic (inspecting `status` between polls) doesn't fit `retry()`'s exception-driven retry model. `Sleep` gives full control and is still fakeable. |
| New `app/Contracts/LiveSourceAdapter.php` folder | Interface file inside existing `app/Services/` | CLAUDE.md: "No new base folders without approval." Placing the interface next to `RegistraduriaService.php` in `app/Services/` avoids that friction entirely; Laravel has no folder convention that requires a `Contracts/` directory. |
| PHP `checkdnsrr()` DNS-only probe | `Http::connectTimeout(2)->head($url)` | DNS check is faster and doesn't need a listening HTTP server, but tells you *less* (DNS could resolve while the app behind it is down — which is plausible for `apiweb-eleccionescolombia.infovotantes.com`, a separate infra target from the page domain). An HTTP HEAD/GET with a short `connectTimeout` catches both DNS-dead and connection-refused/timeout cases in one check. Recommend HTTP HEAD as primary, since it subsumes the DNS-dead case. |

**Installation:** None — everything needed ships with the existing Laravel 12 / PHP 8.4 stack already installed in this project.

**Version verification:** No new packages. Confirmed via direct codebase read that `composer.json`'s `laravel/framework` is pinned appropriately for `Sleep` (introduced Laravel 10, stable and unchanged in 12.x per `raw.githubusercontent.com/laravel/docs/12.x/helpers.md`, fetched live during this research).

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Services/
│   ├── PollingPlaceResolver.php          # NEW — the orchestrator; ONLY place the cascade is expressed
│   ├── LiveSourceAdapter.php              # NEW — interface: startLookup(), getResult(), reachability contract
│   ├── RegistraduriaService.php           # EXISTING — implements LiveSourceAdapter, stays a pure HTTP client
│   └── ...
├── ValueObjects/                          # OPTIONAL new small folder, or keep VO as a plain readonly class in Services/
│   └── PollingPlaceResolution.php         # NEW — VO returned by resolve(), NOT to be confused with the
│                                           #        existing Eloquent model App\Models\PollingPlaceResolution
├── Enums/
│   └── PollingPlaceSource.php             # EXISTING (Phase 7) — extend with precedence(): int
├── Filament/Resources/Voters/Concerns/
│   └── HasRegistraduriaPolling.php        # REFACTOR — becomes a thin delegator to PollingPlaceResolver
config/
└── services.php                           # ADD: 'registraduria' => ['live_enabled' => ..., 'probe_url' => ...]
```

**Naming collision warning:** `App\Models\PollingPlaceResolution` (the Phase 7 Eloquent audit-row model) already exists. CONTEXT.md's "Claude's Discretion" section calls the resolver's return type `PollingPlaceResolution` too (per ARCHITECTURE.md Decision 3). **These must not be the same class.** Recommend naming the value object something distinguishable in code — e.g. `PollingPlaceResolutionResult` or namespacing it under `App\ValueObjects\PollingPlaceResolution` (different namespace than `App\Models\PollingPlaceResolution` — legal in PHP, but genuinely easy to `use` the wrong one by accident since both are called `PollingPlaceResolution`). Flag this explicitly in the plan so the implementer picks one clear name and never imports the wrong class.

### Pattern 1: Interface-bound adapter list (LIVE-01), sized for exactly one implementation today

**What:** A minimal `LiveSourceAdapter` interface capturing the async contract `RegistraduriaService` already exposes, with the resolver accepting an ordered array/collection of adapters rather than a single hardcoded class.

```php
<?php

namespace App\Services;

interface LiveSourceAdapter
{
    /** @throws \Exception if the service is unreachable or returns an error */
    public function startLookup(string $cedula): string;

    /**
     * @return array{status: string, data: array<string,string>|null, error: string|null}
     */
    public function getResult(string $sessionId): array;

    /** Cheap reachability check, no captcha cost. */
    public function isReachable(): bool;
}
```

```php
<?php

namespace App\Services;

class RegistraduriaService implements LiveSourceAdapter
{
    // existing startLookup()/getResult() unchanged

    public function isReachable(): bool
    {
        // see Pattern 2 — do NOT delegate to startLookup()
    }
}
```

```php
<?php

namespace App\Services;

class PollingPlaceResolver
{
    /** @param iterable<LiveSourceAdapter> $liveAdapters tried in array order until one is reachable */
    public function __construct(
        private readonly iterable $liveAdapters,
    ) {}
}
```

**When to use:** Bind the adapter list in `AppServiceProvider::register()`:

```php
$this->app->bind(PollingPlaceResolver::class, fn ($app) => new PollingPlaceResolver(
    liveAdapters: [$app->make(RegistraduriaService::class)], // add wsp adapter here later, no resolver changes
));
```

**Why this fits "don't over-engineer for one adapter":** No new folder, no service-provider tagging machinery, no factory abstraction beyond a plain array. Adding a second adapter (`WspRegistraduriaService implements LiveSourceAdapter`) later is a one-line addition to this array — exactly what LIVE-01 requires, without building infrastructure for a hypothetical third or fourth source.

**Source:** Pattern verified against current Laravel service-container binding conventions (Ash Allen Design, "Using the Strategy Pattern in Laravel" — MEDIUM confidence, cross-checked against Laravel's own `Cache`/`Storage` manager pattern which resolves an interface from a config-driven binding, HIGH confidence via training + the project's own `config('services.registraduria.url')` precedent).

### Pattern 2: Reachability probe as an independent, injectable check — NOT delegated to `RegistraduriaService::startLookup()`

**What goes wrong if you get this wrong:** Calling `startLookup()` to "check" reachability always returns `{session_id: ...}` with HTTP 200 in ~milliseconds (confirmed by reading `registraduria-service/app.py::lookup()` — it only enqueues a background thread). The captcha-solving cost is paid inside that thread regardless of whether the real Registraduría/infovotantes domain is up. So a probe built on `startLookup()` provides **zero** cost or latency protection — it defeats the entire purpose of D-04.

**Correct approach:** Probe a target Laravel controls directly, independent of the Python microservice:

```php
// config/services.php
'registraduria' => [
    'url' => env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757'),
    'live_enabled' => env('REGISTRADURIA_LIVE_ENABLED', true),
    'probe_url' => env('REGISTRADURIA_PROBE_URL', 'https://eleccionescolombia.registraduria.gov.co'),
],
```

```php
public function isReachable(): bool
{
    if (! config('services.registraduria.live_enabled')) {
        return false;
    }

    try {
        $response = Http::connectTimeout(2)->timeout(3)->head(config('services.registraduria.probe_url'));

        return $response->successful() || $response->redirect(); // gov.co sites often 30x redirect on HEAD
    } catch (\Illuminate\Http\Client\ConnectionException) {
        return false;
    }
}
```

**Why HTTP HEAD over raw `checkdnsrr()`:** the two known targets are on different infrastructure (`eleccionescolombia.registraduria.gov.co` — the page host — vs. `apiweb-eleccionescolombia.infovotantes.com` — the API host). A DNS-only check on one doesn't guarantee the other is reachable. An HTTP HEAD against the page host catches both DNS-dead (throws `ConnectionException`) and connection-refused/timeout cases in a single ~2-3s worst-case check, well within the D-08 ceiling.

**Open question flagged for the plan:** should the probe target be the page host or the API host, or both (probe both, AND them together)? Both are "confirmed DNS-dead as of this milestone" per CONTEXT.md D-04, so either alone currently gives the same answer — but they could diverge in the future (page host recovers, API host doesn't, or vice versa). Recommend probing the **API host** (`apiweb-eleccionescolombia.infovotantes.com`) since that's the one the Python service actually calls for data (the page host is only used for `pageurl`/`Referer`/`Origin` headers in the captcha-solve step, not for the actual lookup). This is a LOW-confidence recommendation (no official source describes SIGMA's specific infra split) — flag for human confirmation during planning, or simply probe both and require both to answer before treating live as reachable.

**Testability:** Wrap `checkdnsrr()`/`Http::head()` behind `RegistraduriaService::isReachable()` (an instance method, not a static helper) so Pest tests can `$this->mock(RegistraduriaService::class)->shouldReceive('isReachable')->andReturn(false)` without touching real DNS or `Http::fake()` — both approaches work; the mock approach is faster/simpler for resolver-level tests, `Http::fake(['*/registraduria.gov.co/*' => Http::failedConnection()])` is better for a dedicated `isReachable()` unit test.

### Pattern 3: No-downgrade precedence guard as an enum method (SRC-02)

**What:** Add a `precedence(): int` method to the existing `PollingPlaceSource` enum (Phase 7). Lower number = more trusted. This is the single reusable comparison point the resolver's write path calls before ever persisting a lower-tier result.

```php
// app/Enums/PollingPlaceSource.php — ADD to the existing enum, do not change existing cases/order
public function precedence(): int
{
    return match ($this) {
        self::LIVE => 0,
        self::DB_RECONSTRUCTION => 1,
        self::SNAPSHOT => 2,
        self::MANUAL => 3,
    };
}

public function outranks(self $other): bool
{
    return $this->precedence() < $other->precedence();
}
```

```php
// app/Services/PollingPlaceResolver.php — inside the write path
private function shouldWrite(?PollingPlaceSource $existing, PollingPlaceSource $incoming, bool $isExplicitOverride): bool
{
    if ($isExplicitOverride) {
        return true; // D-10: "Actualizar datos" bypasses the guard entirely
    }

    if ($existing === null) {
        return true;
    }

    return ! $existing->outranks($incoming); // never let a worse source replace a better one automatically
}
```

**When to use:** Call `shouldWrite()` immediately before any `Voter::update([...])` inside the resolver's persistence step, for every tier except the explicit `forceRefreshFromRegistraduria()` path (which passes `isExplicitOverride: true` per D-10). A cache-tier "hit" that re-confirms the same source is not a downgrade (equal precedence, not lower) and is handled by D-11's separate "only write audit row on real transition" rule — the two rules are independent and both apply.

### Pattern 4: Bounded automated polling with `Sleep`, immediate give-up on `waiting_captcha`

```php
// app/Services/PollingPlaceResolver.php
private function attemptLiveAutomated(LiveSourceAdapter $adapter, string $cedula): ?array
{
    if (! $adapter->isReachable()) {
        return null; // LIVE-03 — never even start if we already know it's dead
    }

    $sessionId = $adapter->startLookup($cedula);

    $backoffMs = [200, 400, 800, 1200, 1600]; // D-06: 3-5 polls, monotonic short backoff, D-08: well under 10s total

    foreach ($backoffMs as $i => $delayMs) {
        $result = $adapter->getResult($sessionId);

        if ($result['status'] === 'done') {
            return $result['data'];
        }

        if ($result['status'] === 'waiting_captcha') {
            return null; // D-07 — hard give-up signal, not a timeout-driven decision
        }

        if ($result['status'] === 'error') {
            return null;
        }

        // status === 'pending' — keep polling unless this was the last attempt
        if ($i < count($backoffMs) - 1) {
            Sleep::for($delayMs)->milliseconds();
        }
    }

    return null; // exhausted attempts, still pending — give up (D-06)
}
```

**Testing this without real delays:**

```php
use Illuminate\Support\Sleep;

it('gives up immediately on waiting_captcha without exhausting all polls', function () {
    Sleep::fake();

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldReceive('isReachable')->andReturn(true);
        $mock->shouldReceive('startLookup')->andReturn('session-1');
        $mock->shouldReceive('getResult')
            ->once() // only called once — waiting_captcha ends the loop immediately, no further polls
            ->andReturn(['status' => 'waiting_captcha', 'data' => null, 'error' => null]);
    });

    $result = app(PollingPlaceResolver::class)->resolveAutomated('1234567890');

    expect($result->source)->toBe(PollingPlaceSource::SNAPSHOT); // fell through to fallback
    Sleep::assertNeverSlept(); // confirms the give-up was immediate, not timing-based
});

it('polls up to the configured attempts with short backoff before giving up on pending', function () {
    Sleep::fake();

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldReceive('isReachable')->andReturn(true);
        $mock->shouldReceive('startLookup')->andReturn('session-2');
        $mock->shouldReceive('getResult')
            ->times(5)
            ->andReturn(['status' => 'pending', 'data' => null, 'error' => null]);
    });

    app(PollingPlaceResolver::class)->resolveAutomated('1234567890');

    Sleep::assertSleptTimes(4); // 5 polls => 4 waits between them, none after the last
});
```

**Source:** `Sleep::fake()`, `Sleep::assertNeverSlept()`, `Sleep::assertSleptTimes()` verified against `raw.githubusercontent.com/laravel/docs/12.x/helpers.md` (fetched live during this research — HIGH confidence, current for Laravel 12.x). Mockery `shouldReceive(...)->once()` / `->times(n)` pattern matches this project's existing `RegistraduriaControllerTest`/`VoterRegistraduriaRefreshTest` convention exactly.

### Anti-Patterns to Avoid

- **Probing `RegistraduriaService::startLookup()` to test reachability** — always returns 200 immediately; tells you nothing (see Pattern 2). This is the single easiest mistake to make in this phase since it looks like the "obvious" reuse of existing code.
- **Native `sleep()`/`usleep()` in the automated poll loop** — makes every test that exercises the backoff actually pause for real wall-clock time (5 polls × up to 1.6s = 8s+ per test, multiplied across the test suite). Always use `Sleep::for(...)->milliseconds()`.
- **Reusing `fillPollingPlaceFields()` verbatim inside the resolver** — per PITFALLS.md Pitfall 4, it reads `CampaignContext::currentCampaignId()` ambient state; fine for the interactive Filament caller (has real session context) but will silently no-op or misattribute campaign in Phase 11's future headless caller. The resolver's persistence method must accept an explicit `campaign_id`/`Voter` argument, never read ambient context. (Phase 8 only has the interactive caller today, but LIVE-01 explicitly requires the cascade to be "shared by both interactive and headless callers" — design the signature for both now even though Phase 11 isn't built yet.)
- **A new `app/Contracts/` folder for one interface** — violates CLAUDE.md's "no new base folders without approval." Put `LiveSourceAdapter.php` in `app/Services/` alongside `RegistraduriaService.php`.
- **Naming the resolver's return VO `PollingPlaceResolution`** — collides with the existing `App\Models\PollingPlaceResolution` Eloquent model from Phase 7. Pick a distinguishable name (see Pattern 1 structure note).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Pausing between polls in a way tests can fake | Custom `usleep()` + a test-mode flag/env check | `Illuminate\Support\Sleep` facade | Built-in fake/assert API (`Sleep::fake()`, `assertSleptTimes()`, `assertSequence()`) — reinventing this means also reinventing the test-speed problem it solves |
| HTTP retry/backoff primitives for a *single* request | A manual `for` loop with `Http::post()` and manual timing | `Http::retry($times, $ms)` for simple request-level retries; **but** this phase's poll loop is not a request retry — it's polling an existing session's *result*, so use the manual `Sleep`-based loop in Pattern 4, not `Http::retry()` | `Http::retry()` retries the *same request* on failure/exception; here every poll is a fresh, successful 200 response whose *body* dictates whether to continue — different problem shape |
| Comparing enum "trust levels" | Ad-hoc `if ($source === 'live')` string comparisons scattered across the write path | A single `precedence()`/`outranks()` method on `PollingPlaceSource` (Pattern 3) | Centralizes the ordering in one place so SRC-02's rule can never drift between call sites; this is exactly the kind of "expressed exactly once" requirement the phase goal calls for |
| Reachability checking | Rolling a custom cURL/DNS wrapper | `Http::connectTimeout()->timeout()->head()` (Laravel's existing HTTP client wrapper around Guzzle) | Already the established pattern in this codebase (`RegistraduriaService` uses `Http::timeout()`); no reason to introduce a second HTTP client library |

**Key insight:** every piece of this phase already has a first-class Laravel primitive (`Sleep`, `Http`, backed enums) — the risk in this phase is not "which library" but "which target" (see the reachability-probe finding) and "which existing method's ambient-context assumption breaks under a headless caller" (Pitfall 4).

## Common Pitfalls

(See PITFALLS.md for the full milestone-level list; the four flagged as directly relevant to this phase are elaborated below with this phase's specific code paths.)

### Pitfall A: Reachability probe built on the wrong signal (this phase's version of PITFALLS.md Pitfall 6)
**What goes wrong:** Probing `RegistraduriaService::startLookup()` or the Python microservice's base URL instead of the actual Registraduría/infovotantes hostnames. The probe always reports "reachable" because the microservice itself is up even when the site it scrapes is dead.
**Why it happens:** `RegistraduriaService` is the only Laravel-visible thing named "Registraduria" — it's the natural (but wrong) place to look for a health signal.
**How to avoid:** Add a `probe_url` config key pointing at the *actual* external hostname(s) hardcoded in `registraduria-service/app.py`; probe that directly with `Http::connectTimeout()->head()`, independent of `RegistraduriaService`'s async contract.
**Warning signs:** A reachability check that never returns `false` even when `.env`'s known-dead domain is unreachable in manual testing.

### Pitfall B: The automated give-up rule keyed on time instead of state (this phase's version of PITFALLS.md Pitfall 5)
**What goes wrong:** Treating `waiting_captcha` as "just another pending state" and continuing to poll it until the D-08 wall-clock ceiling is hit, rather than treating it as an immediate hard stop (D-07).
**Why it happens:** `pending` and `waiting_captcha` look similar (both mean "not done yet") unless the code explicitly branches on them differently.
**How to avoid:** Explicit `match`/`if` branch: `waiting_captcha` returns immediately, `pending` continues the loop, `error`/`done` both terminate (Pattern 4).
**Warning signs:** A test where `getResult()` is mocked to always return `waiting_captcha` and the mock's `shouldReceive(...)->times(n)` expectation is set to more than 1 — if that test passes, the give-up isn't actually immediate.

### Pitfall C: No-downgrade guard skipped for the explicit "Actualizar datos" refresh (this phase's version of PITFALLS.md Pitfall 10, inverted)
**What goes wrong:** Implementing SRC-02's guard so broadly that it also blocks the operator's deliberate force-refresh action (D-10 explicitly carves this out) — or the opposite mistake, forgetting the guard entirely for the *automatic* cascade path.
**Why it happens:** Both code paths (`openRegistraduriaBrowser()` → automatic cascade, `forceRefreshFromRegistraduria()` → explicit override) will call into the same `PollingPlaceResolver`; without a clear `isExplicitOverride` parameter threaded through, it's easy to apply the guard uniformly to both or neither.
**How to avoid:** Make `isExplicitOverride` an explicit, required parameter on the resolver's persistence method (Pattern 3) — never inferred from which caller happened to invoke it.
**Warning signs:** A single boolean flag missing from the resolver's public signature; a test asserting force-refresh can overwrite a `live` source with a fresh `live` result would catch this, but a test asserting force-refresh can overwrite `live` with `snapshot` data is the one that actually matters for D-10.

### Pitfall D: `HasRegistraduriaPolling` persistence logic reused verbatim, breaking a future headless caller (PITFALLS.md Pitfall 4, this phase's angle)
**What goes wrong:** The resolver's write path calls `CampaignContext::currentCampaignId()` (copied from `fillPollingPlaceFields()`) instead of deriving campaign from the `Voter` model passed in.
**Why it happens:** `fillPollingPlaceFields()` is the exact logic being extracted, and it's easiest to lift it unchanged.
**How to avoid:** The resolver's persistence step must take the `Voter` (or explicit `campaign_id`) as an argument and use `$voter->campaign_id`, never `CampaignContext`. Phase 8 only calls this from the interactive UI (which always has a `Voter`), but designing the signature this way now means Phase 11's headless job needs zero resolver changes later — directly serving LIVE-01's "shared by both interactive and headless callers" requirement.
**Warning signs:** `CampaignContext` appearing anywhere inside `app/Services/PollingPlaceResolver.php`.

## Code Examples

### Resolver skeleton showing the two tier orders (D-01 vs D-03) sharing one implementation

```php
<?php

namespace App\Services;

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\NationalCensusRecord;
use App\Models\Voter;
use Illuminate\Support\Facades\Cache;

class PollingPlaceResolver
{
    public function __construct(
        /** @var iterable<LiveSourceAdapter> */
        private readonly iterable $liveAdapters,
    ) {}

    /**
     * Interactive path (D-01): cache -> live -> db_reconstruction -> snapshot.
     * Returns null only if every tier misses.
     */
    public function resolveInteractive(string $cedula, ?Voter $voter = null): ?PollingPlaceResolutionResult
    {
        if ($cached = Cache::get("registraduria:cedula:{$cedula}")) {
            return $this->buildResult($cached, PollingPlaceSource::LIVE, fromCache: true);
        }

        foreach ($this->liveAdapters as $adapter) {
            if ($result = $this->attemptLiveInteractive($adapter, $cedula)) {
                return $this->persist($voter, $result, PollingPlaceSource::LIVE, isExplicitOverride: false);
            }
        }

        if ($fromDb = $this->resolveFromCampaignCensus($cedula)) {
            return $this->persist($voter, $fromDb, PollingPlaceSource::DB_RECONSTRUCTION, isExplicitOverride: false);
        }

        if ($fromSnapshot = $this->resolveFromNationalSnapshot($cedula)) {
            return $this->persist($voter, $fromSnapshot, PollingPlaceSource::SNAPSHOT, isExplicitOverride: false);
        }

        return null;
    }

    /**
     * Automated/headless path (D-03): live -> snapshot only. Never blocks (LIVE-03).
     */
    public function resolveAutomated(string $cedula, ?Voter $voter = null): ?PollingPlaceResolutionResult
    {
        foreach ($this->liveAdapters as $adapter) {
            if ($result = $this->attemptLiveAutomated($adapter, $cedula)) {
                return $this->persist($voter, $result, PollingPlaceSource::LIVE, isExplicitOverride: false);
            }
        }

        if ($fromSnapshot = $this->resolveFromNationalSnapshot($cedula)) {
            return $this->persist($voter, $fromSnapshot, PollingPlaceSource::SNAPSHOT, isExplicitOverride: false);
        }

        return null;
    }

    /**
     * Explicit operator "Actualizar datos" (D-10): straight to live, bypasses cache/DB/snapshot
     * AND bypasses the no-downgrade guard.
     */
    public function forceRefresh(string $cedula, Voter $voter): ?PollingPlaceResolutionResult
    {
        foreach ($this->liveAdapters as $adapter) {
            if ($result = $this->attemptLiveInteractive($adapter, $cedula)) {
                return $this->persist($voter, $result, PollingPlaceSource::LIVE, isExplicitOverride: true);
            }
        }

        return null;
    }
}
```

*(Method bodies for `attemptLiveInteractive()`, `resolveFromCampaignCensus()`, `resolveFromNationalSnapshot()`, `buildResult()`, and `persist()` are implementation detail for the planner/executor — the skeleton above exists to show how D-01/D-03/D-10 share the same private helpers without duplicating cascade logic, satisfying "expressed exactly once.")*

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| `usleep()`/`sleep()` for backoff | `Illuminate\Support\Sleep` facade | Laravel 10.0 (2023), unchanged/stable through 12.x | Directly relevant here — makes D-06's backoff testable without real delays |
| Cascade logic living in a Filament trait | Cascade logic living in a plain injectable service, trait becomes a thin delegator | This phase | Enables Phase 11's headless reconciliation job to reuse the exact same cascade (LIVE-01's "shared by both interactive and headless callers") |

**Deprecated/outdated:** Nothing in this phase's scope is deprecated — this is a straightforward extraction using current, stable Laravel 12.x primitives.

## Open Questions

1. **Which hostname should the reachability probe target — the page host or the API host (or both)?**
   - What we know: both `eleccionescolombia.registraduria.gov.co` (page) and `apiweb-eleccionescolombia.infovotantes.com` (API) are hardcoded in `registraduria-service/app.py` and both are confirmed DNS-dead as of this milestone (STATE.md blockers).
   - What's unclear: whether these two hosts' uptime is correlated (same outage) or independent (one could recover before the other), since they appear to be different infrastructure.
   - Recommendation: probe the API host (`apiweb-eleccionescolombia.infovotantes.com`) since that's what the Python service actually needs for the lookup itself; the page host is only used for `pageurl`/`Referer`/`Origin` headers during the captcha-solve step. If budget allows, probe both and require both to succeed. This should be confirmed as part of planning, not left as a silent assumption in the implementation.

2. **Should `LiveSourceAdapter::isReachable()` be part of the interface, or a separate concern the resolver owns directly (e.g., a small `RegistraduriaReachabilityProbe` class)?**
   - What we know: CONTEXT.md's "Claude's Discretion" section explicitly leaves this open ("DNS resolution check, lightweight HTTP HEAD/GET... or reuses RegistraduriaService's existing HTTP client").
   - What's unclear: whether a future `wsp` adapter (Phase 9) would have a *different* reachability signal than a plain HTTP HEAD (e.g., needing to check reCAPTCHA Enterprise assessment availability, not just host uptime) — if so, `isReachable()` belongs on the interface (each adapter defines its own cheap check). If all adapters will always just be "is this host up," a single shared probe class is simpler and avoids interface bloat.
   - Recommendation: put `isReachable()` on the `LiveSourceAdapter` interface (Pattern 1) — it costs nothing extra for the current single adapter and future-proofs correctly for Phase 9's genuinely different reachability semantics (2captcha/reCAPTCHA-Enterprise viability isn't just "is the host up").

3. **Where does the VO returned by `resolve()` live, and what should it be named to avoid colliding with `App\Models\PollingPlaceResolution`?**
   - What we know: ARCHITECTURE.md's Decision 3 calls it `PollingPlaceResolution` with fields `{source, pollingPlaceId, tableNumber, fields[], resolvedAt}`; CONTEXT.md's discretion section defers the exact shape to this research/plan.
   - What's unclear: whether the planner will notice the name collision with the existing Eloquent model before writing tasks.
   - Recommendation: name it `PollingPlaceResolutionResult` (or similar) and place it under `App\Services\` or a new lightweight `App\ValueObjects\` folder — flagging the latter as a **new base folder** that would need the "no new base folders without approval" sign-off; the safer default is keeping it in `App\Services\` as a plain readonly class alongside the resolver.

## Environment Availability

No external dependencies beyond what's already installed and configured in this project (PHP 8.4, Laravel 12, Redis for cache, the existing `registraduria-service` Python microservice for the live adapter). No new package installs, no new services to provision for this phase.

| Dependency | Required By | Available | Version | Fallback |
|------------|-------------|-----------|---------|----------|
| Redis (cache driver) | 30-day cédula cache tier (unchanged, D-02) | ✓ (already used by `HasRegistraduriaPolling`) | — | — |
| `registraduria-service` Python microservice | Live tier's `startLookup()`/`getResult()` contract | ✓ (exists, currently reachable itself — its *upstream* Registraduría/infovotantes targets are the ones that are DNS-dead, per STATE.md) | — | Kill switch (D-05) fully disables the live tier if needed |
| Outbound network access to `eleccionescolombia.registraduria.gov.co` / `apiweb-eleccionescolombia.infovotantes.com` | Reachability probe (D-04) | ✗ (confirmed DNS-dead as of this milestone per STATE.md/CONTEXT.md) | — | This is expected and exactly what the probe + fallback cascade is built to handle — not a blocker, it's the scenario this phase exists to make resilient to |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:** The live Registraduría/infovotantes domains being down is the expected current state and has a full fallback (DB reconstruction → national snapshot) by design.

## Sources

### Primary (HIGH confidence)
- Direct reads of the SIGMA codebase: `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php`, `app/Services/RegistraduriaService.php`, `registraduria-service/app.py` (confirms the reachability-probe finding — `/lookup` returns immediately, captcha cost paid before target-site contact), `app/Models/PollingPlaceResolution.php`, `app/Enums/PollingPlaceSource.php`, `app/Models/Voter.php`, `app/Models/NationalCensusRecord.php`, `app/Models/CensusRecord.php`, `config/services.php`, `database/migrations/2026_07_24_130001_add_polling_place_source_to_voters_table.php`, `database/migrations/2026_07_24_130002_create_polling_place_resolutions_table.php`, `tests/Feature/RegistraduriaControllerTest.php`, `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php`, `app/Jobs/FinalizeElectionEvent.php`, `.env` / `.env.example` (confirms `REGISTRADURIA_SERVICE_URL` is the only existing Registraduría-related env var)
- `.planning/phases/08-resilient-pollingplaceresolver-service/08-CONTEXT.md` — locked D-01 through D-13 decisions
- `.planning/REQUIREMENTS.md`, `.planning/STATE.md` — requirement text, phase dependency chain, DNS-dead confirmation
- `.planning/research/ARCHITECTURE.md` Decision 3 — resolver responsibilities, async-mode caveat, two-census-tables resolution order
- `.planning/research/PITFALLS.md` Pitfalls 1, 5, 6, 10 — stale-snapshot-as-authoritative, job-hangs-on-captcha, budget-flood, no-downgrade
- Laravel 12.x HTTP Client docs, fetched live via `raw.githubusercontent.com` proxy during this research (`Http::timeout()`, `Http::connectTimeout()`, `Http::fake()` with `Http::sequence()`, `Http::failedConnection()`) — current as of fetch date, HIGH confidence
- Laravel 12.x Helpers docs (`Sleep` section), fetched live via `raw.githubusercontent.com/laravel/docs/12.x/helpers.md` during this research — `Sleep::for()->milliseconds()`, `Sleep::fake()`, `Sleep::assertSleptTimes()`, `Sleep::assertNeverSlept()`, `Sleep::assertSequence()` — HIGH confidence, current for 12.x

### Secondary (MEDIUM confidence)
- Ash Allen Design, "Using the Strategy Pattern in Laravel" (verified conceptually against Laravel's own Cache/Storage manager pattern, which is the same shape) — informs Pattern 1's adapter-list design

### Tertiary (LOW confidence)
- The recommendation to probe the API host specifically over the page host (Open Question 1) — inferred from reading `app.py`'s comments, not confirmed against any Registraduría/infovotantes uptime documentation (none exists publicly); flagged for human confirmation during planning

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies, all primitives (`Sleep`, `Http`, backed enums) verified directly against current Laravel 12.x docs and the existing codebase
- Architecture: HIGH for the extraction/adapter/precedence patterns (directly grounded in existing code + ARCHITECTURE.md); MEDIUM for the exact reachability-probe target (Open Question 1) since it depends on infrastructure behavior not documented anywhere
- Pitfalls: HIGH — Pitfall A (reachability probe target) was discovered by direct code inspection of `registraduria-service/app.py`, not inference; Pitfalls B/C/D are directly derived from CONTEXT.md's locked decisions and PITFALLS.md's milestone-level research

## Project Constraints (from CLAUDE.md)

- Explicit `use` statements only — never namespace aliases or inline `\App\...` paths (applies to every new class in this phase: `LiveSourceAdapter`, the resolver, the VO).
- No new base folders without approval — the `LiveSourceAdapter` interface and the resolver's result VO should live inside the existing `app/Services/` directory, not a new `app/Contracts/` or `app/ValueObjects/` folder, unless explicitly approved during planning.
- No dependency changes without approval — this phase needs none; everything is built on already-installed Laravel/PHP primitives.
- Always curly braces for control structures; constructor property promotion; explicit return types and parameter type hints on every new method (`PollingPlaceResolver`, `LiveSourceAdapter`, enum additions).
- PHPDoc over inline comments; array-shape types where appropriate (already modeled in `LiveSourceAdapter::getResult()`'s docblock above, matching `RegistraduriaService::getResult()`'s existing docblock convention).
- Services live in `app/Services/`, are plain classes (not Filament-coupled), constructor-injected — exactly how `PollingPlaceResolver` and `LiveSourceAdapter` should be built.
- Config values read via `config('services.x.y')`, backed by `env()` only inside `config/*.php` files — applies to the new `live_enabled` and `probe_url` keys.
- Every change must have a test; run affected tests before finishing — `tests/Feature/Services/PollingPlaceResolverTest.php` is the correct location, following the existing `tests/Feature/Services/VoterValidationServiceTest.php` precedent.
- Run `vendor/bin/pint --dirty` before finalizing changes.
- GSD Workflow Enforcement: file-changing work must go through `/gsd:execute-phase` for this planned phase, not direct ad-hoc edits.

---
*Research for: Phase 08 - Resilient PollingPlaceResolver Service*
*Researched: 2026-07-24*
*Valid until: ~30 days (stable Laravel-ecosystem patterns; re-verify only if Phase 9's wsp spike changes the live-adapter contract shape, which would affect the `LiveSourceAdapter` interface design)*
