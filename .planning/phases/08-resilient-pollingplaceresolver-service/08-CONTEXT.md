# Phase 8: Resilient PollingPlaceResolver Service - Context

**Gathered:** 2026-07-24
**Status:** Ready for planning

<domain>
## Phase Boundary

Extract the polling-place fallback cascade out of `HasRegistraduriaPolling` into a single shared `PollingPlaceResolver` service, expressed exactly once, so both the interactive Filament UI and future headless callers (Phase 11's reconciliation job) share the same cascade logic. The resolver must never hang waiting on a dead/unreachable live source and must never silently downgrade a live-verified result with older snapshot data. This phase delivers CENSO-01, SRC-02, LIVE-01, LIVE-03 only. It does NOT build the reconciliation job (Phase 11), does NOT validate `wsp.registraduria.gov.co` (Phase 9's feasibility spike), and does NOT change any operator-visible UI/wording (Phase 10).

</domain>

<decisions>
## Implementation Decisions

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

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this phase's primary technical grounding)
- `.planning/research/SUMMARY.md` — executive summary, Phase 8 rationale
- `.planning/research/ARCHITECTURE.md` §"Decision 3: Fallback decision lives in a NEW `PollingPlaceResolver`" — the resolver's exact responsibilities, the async/live-mode caveat, and the "two census tables" resolution order; §"Recommended Build Order" step 3 — what this phase must deliver vs. what's deferred
- `.planning/research/PITFALLS.md` — Pitfall 1 (stale snapshot treated as authoritative — source flag must be set in the same write), Pitfall 5 (job/automated path must never block on a human captcha step — governs D-06/D-07/D-08), Pitfall 6 (captcha budget — governs the D-04 reachability probe), Pitfall 10 (fallback must never clobber fresher data — governs D-13's no-downgrade guard)

### Existing code precedents to reuse
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` — the exact 3-tier cascade (cache → `resolveFromDatabase()` → 2captcha) being extracted into the resolver; `openRegistraduriaBrowser()`, `forceRefreshFromRegistraduria()`, `resolveFromDatabase()`, and `fillPollingPlaceFields()` are the methods whose logic moves/delegates
- `app/Services/RegistraduriaService.php` — the live HTTP adapter (`startLookup()` / `getResult()`); stays a pure live-source client per ARCHITECTURE.md Decision 3 — cascade logic must NOT be added here
- `config/services.php` (`'registraduria' => ['url' => ...]`) — where the new `live_enabled` kill-switch config key (D-05) attaches, following the existing `env()`-backed pattern
- `app/Models/PollingPlaceResolution.php` + `app/Enums/PollingPlaceSource.php` (Phase 7) — the audit table/model and source enum this resolver writes to and casts against; enum precedence order (`LIVE`, `DB_RECONSTRUCTION`, `SNAPSHOT`, `MANUAL`) is what D-13 enforces
- `app/Models/Voter.php` — `polling_place_source`, `polling_place_resolved_at` columns (Phase 7) and `pollingPlaceResolutions(): HasMany` relation this resolver updates
- `app/Models/NationalCensusRecord.php` (Phase 6) — the national snapshot tier's read source, keyed on `document_number`
- `app/Models/CensusRecord.php` — the campaign-scoped DB-reconstruction tier's read source (via `resolveFromDatabase()`'s existing join logic)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `HasRegistraduriaPolling::resolveFromDatabase()` — the exact DB-reconstruction join logic (census_records → Municipality → PollingPlace) to lift into the resolver's DB-reconstruction tier.
- `RegistraduriaService::startLookup()` / `getResult()` — the exact async live-adapter contract (`session_id` → poll → `{status: pending|waiting_captcha|done|error, data, error}`) the resolver's automated mode must poll against.
- Redis `Cache::get/put` with the `registraduria:cedula:{cedula}` key pattern and 30-day TTL — reused as-is per D-02.

### Established Patterns
- Services live in `app/Services/`, are plain classes (not Filament-coupled), constructor-injected.
- Config values are read via `config('services.x.y')`, backed by `env()` only inside `config/*.php` files (never inline `env()` calls elsewhere) — per CLAUDE.md Laravel conventions.
- Enum-cast columns + `HasMany`/`BelongsTo` relations follow the `ValidationHistory`/`PollingPlaceSource` precedent already established in Phase 7.

### Integration Points
- `PollingPlaceResolver::resolve(string $cedula, ?Voter $voter = null): PollingPlaceResolution` is the single entry point both `HasRegistraduriaPolling` (interactive) and Phase 11's future job (headless) will call — the cascade must be expressed exactly once here.
- `HasRegistraduriaPolling` becomes a thin delegator: its Filament-specific concerns (notifications, `$this->data[...]` fill, the async browser modal state) stay in the trait; the cascade decision and DB reconstruction logic move to the resolver.
- The reachability probe (D-04) and kill switch (D-05) both gate the "attempt live" step before any call reaches `RegistraduriaService::startLookup()`.

</code_context>

<specifics>
## Specific Ideas

- User's own words on the ordering decision: reliability/freshness of the data matters more than cost for the interactive path — cache exists only to avoid re-paying for an already-resolved cédula, not to avoid live lookups in general.
- No specific UI/visual references — this phase has no user-facing surface change (D-09).

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. The reconciliation job's budget/backoff/terminal-state mechanics (Pitfalls 6/7/11), the `wsp.registraduria.gov.co` feasibility spike (Pitfall 8), and any operator-visible source badge/filter UI (Phase 10) were not re-litigated here; they remain scoped to Phases 9/10/11 respectively.

</deferred>

---

*Phase: 08-resilient-pollingplaceresolver-service*
*Context gathered: 2026-07-24*
