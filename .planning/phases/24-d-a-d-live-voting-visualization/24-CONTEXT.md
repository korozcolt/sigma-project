# Phase 24: Día D Live Voting Visualization - Context

**Gathered:** 2026-08-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Admins/operators watch live Día D voting progress update in real time via a line chart of `VoteRecord.voted_at` accumulated hourly, without imposing expensive per-tick `COUNT` query load on the campaign database during high-concurrency election-day polling. This phase delivers exactly one new widget backed by a cached/pre-aggregated endpoint — it does not add territorial/coordinator breakdowns (already covered by `DiaDTerritorialProgressTable`) or any other new capability.

</domain>

<decisions>
## Implementation Decisions

### Caching / Pre-Aggregation Strategy
- **D-01:** Use `Cache::remember()` with on-demand recalculation, not a scheduled job and not event-driven invalidation via a `VoteRecord` observer. Rationale: no new scheduler infrastructure needed, and it avoids coupling the vote-marking hot path (already treated as a high-risk flow per project constraints) to cache invalidation for a read-only widget. Only the first request after expiry pays the query cost; concurrent admins during the same window hit cache.
- **D-02:** Cache TTL is **30 seconds**. Chosen to feel "live" for a Día D dashboard while absorbing concurrent-polling bursts from multiple admins/operators watching simultaneously. The roadmap's own "accumulated hourly" bucketing means a few seconds of staleness is imperceptible at the chart's granularity.
- **Cache scope note (Claude's discretion for exact key format):** The cache key must be campaign-scoped (per the project's strict campaign-isolation constraint) and should account for the active `ElectionEvent` (via `campaign_id` + `is_active` election event, per `app/Models/ElectionEvent.php`) so results don't bleed across election events/simulations within the same campaign.

### Widget Polling Interval
- **D-03:** The widget's `pollingInterval` is **30s**, matching the cache TTL exactly. Rationale: every poll tick aligns with a real possible cache refresh — no polls are "wasted" re-requesting data known not to have changed. This deliberately diverges from `DiaDStatsOverview`'s existing `10s` polling (that widget still does direct uncached counts and is out of scope for this phase).

### Placement
- **D-04:** The chart lives on the dedicated **`DiaD` Filament page** (`app/Filament/Pages/DiaD.php`), alongside the existing `DiaDStatsOverview` and `DiaDTerritorialProgressTable` widgets — not on the general Admin dashboard. Rationale: this is where admins/operators already look during the election-day operational window; keeps all Día D context in one place.

### Time-Series Scope
- **D-05:** The chart shows exactly **one line**: campaign-wide cumulative vote count accumulated hourly (`VoteRecord.voted_at` bucketed by hour, running total). No per-territory, per-coordinador, or per-leader breakdown in this chart — that would be a new capability duplicating `DiaDTerritorialProgressTable`'s existing purpose and is out of this phase's scope (see Deferred Ideas).

### Claude's Discretion
- Exact cache key naming/format (must include campaign_id + active election_event_id per the scope note above)
- Exact chart kind/component reuse: `resources/js/charts/components/LineChart.jsx` already exists (used by `ValidationProgressChart`) — reuse it rather than building a new chart component unless it's structurally incompatible with an hourly-cumulative time series
- Empty/pre-Día-D state copy (no vote records yet — before polls open)
- Exact aggregation query shape (e.g., whether hours with zero new votes still appear as a flat continuation of the running total)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Chart infrastructure (Phase 20/21 precedent)
- `.planning/research/PITFALLS.md` — the 6 critical pitfalls the React-island bridge must avoid (stale/orphaned roots on poll, leaked roots on SPA navigation, event-delegation conflicts, missing per-panel asset registration, coverage-theater tests, false-hydration confusion)
- `resources/js/charts/ChartRouter.jsx` — kind-to-component dispatch map; a `line` kind and `LineChart.jsx` component already exist and should be reused
- `resources/js/charts/lib/chartjs-adapter.js` — `isChartDataEmpty()` must recognize this widget's real data shape (existing `line`/`labels`+`datasets` shape likely already covered — confirm during research)

### Existing Día D surfaces (do not duplicate)
- `app/Filament/Pages/DiaD.php` — the target page this widget registers on
- `app/Filament/Widgets/DiaDStatsOverview.php` — existing uncached `10s`-polling stats widget on the same page; pattern reference for polling widget structure, NOT for caching (this phase introduces the first cached widget)
- `app/Filament/Widgets/DiaDTerritorialProgressTable.php` — existing territorial breakdown; this phase's chart must NOT duplicate this table's per-territory scope

### Campaign/election-event scoping
- `app/Models/ElectionEvent.php` — `campaign_id`, `is_active` fields; cache key and query must scope by both campaign and active election event
- `app/Models/VoteRecord.php` — `election_event_id`, `voted_at` fields (the data source for hourly accumulation)

No external specs/ADRs beyond the roadmap and requirements entries below — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/js/charts/components/LineChart.jsx` — already built and used by `ValidationProgressChart`; the natural fit for an hourly-cumulative time series, no new chart component needed
- `app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php` — both use `pollingInterval = '120s'` as the established Filament polling pattern to follow (adjusted to `30s` per D-03)

### Established Patterns
- No widget in the codebase currently uses `Cache::` — this phase introduces the first cached widget. `config/cache.php` default driver is `database` (`CACHE_STORE=database` in `.env`), so `Cache::remember()` will use the database cache table with no additional setup.
- Every existing chart widget scopes its query through `CampaignContext::currentCampaign()` — the new cached aggregation must follow the same pattern, just with the cache key incorporating the resolved campaign (and active election event) so isolation holds even through the cache layer.

### Integration Points
- New widget registers on `app/Filament/Pages/DiaD.php` per D-04, alongside `DiaDStatsOverview` and `DiaDTerritorialProgressTable`
- Reuses `ChartRouter.jsx` → `LineChart.jsx` dispatch, the same React-island bridge proven in Phases 20-23 — no new frontend infrastructure needed

</code_context>

<specifics>
## Specific Ideas

No specific visual references beyond reusing the existing `LineChart.jsx` component and the established `ChartCard` wrapper from prior phases.

</specifics>

<deferred>
## Deferred Ideas

- **Per-territory/per-coordinador breakdown within the live line chart** — suggested during discussion as an alternative scope, explicitly declined (D-05) in favor of matching the roadmap's simple single-line scope. `DiaDTerritorialProgressTable` already serves this need. Could become its own future phase/idea if a multi-line comparative view is ever requested.

</deferred>

---

*Phase: 24-d-a-d-live-voting-visualization*
*Context gathered: 2026-08-21*
