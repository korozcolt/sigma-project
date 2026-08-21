# Phase 23: Differentiator Visualizations - Context

**Gathered:** 2026-08-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 23 adds 5 curated/modeled chart widgets — a happy-path funnel of the Voter lifecycle, a Sankey of `ValidationHistory` state transitions, a drill-down treemap of territorial distribution, a heatmap of call-center caller×hour effectiveness, and a stacked-area of rejection reasons over time — each requiring a genuine data-modeling/curation decision, not just a component swap onto the React/Recharts island infrastructure built in Phases 20-22. It is NOT about Phase 24's live Día D polling chart (depends on Phase 21's pipeline, not Phase 23) or any new capability beyond VIZ-06 through VIZ-10.

</domain>

<decisions>
## Implementation Decisions

### Happy-path funnel (VIZ-06)

- **D-01:** Happy-path sequence is the roadmap's 4-stage example: `PENDING_REVIEW → VERIFIED_CENSUS → CONFIRMED → VOTED`. Deliberately skips `VERIFIED_REGISTRADURIA` and `VERIFIED_CALL` — not every voter passes through those two gates, so including them would break strict monotonic narrowing for voters who reach `CONFIRMED` via a different verification path.
- **D-02:** Branch/terminal states (`REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `CENSUS_NOT_FOUND`, `DUPLICATE`, `CORRECTION_REQUIRED`, `DID_NOT_VOTE`) are NOT forced into the funnel shape. They render as a small counter row below the funnel, similar chrome to `RejectionsCountersOverview` — the funnel itself stays 100% happy-path only.
- **D-03:** `DID_NOT_VOTE` goes in the branch counter row alongside the other terminal states, not as a 5th funnel stage — a voter who reached `CONFIRMED` but didn't vote is a branch outcome, not a happy-path completion.
- **D-04:** Scope: campaign-wide, admin-only — matches VIZ-01/02's precedent (`VoterStatusDonutChart`). No leader/coordinator/area_coordinator role-scoping branch, consistent with the roadmap's "Admin sees..." framing for this chart.

### Sankey de transiciones (VIZ-07)

- **D-05:** Curation strategy is top-N by volume: `GROUP BY previous_status, new_status`, order by count, keep the top N transitions (target ~8-10), collapse the remainder into an "Otros" edge. Adapts automatically as real campaign transition patterns emerge, rather than requiring a hand-picked fixed set.
- **D-06:** `previous_status = null` (initial voter creation) renders as a synthetic "Nuevo" source node feeding into the voter's first real status — shows true entry volume into the validation pipeline instead of being dropped.
- **D-07:** Cycles/back-edges (e.g. `CORRECTION_REQUIRED ↔ PENDING_REVIEW` repeating) are NOT deduped per-voter. Each recorded `(previous_status, new_status)` pair counts every occurrence — a voter bouncing back and forth 3× contributes 3× to that edge's weight. Simplest query (flat `GROUP BY` count, no `DISTINCT`-per-voter logic), and composes naturally with D-05's top-N-by-volume ranking.
- **D-08:** Time range: all campaign history, campaign-scoped, no date filter — matches VIZ-04's message-funnel precedent (Phase 22 D-09) and keeps this milestone's historical-chart pattern consistent.

### Treemap territorial drill-down (VIZ-08)

- **D-09:** Role-scoping carries over unchanged from `TerritorialDistributionChart`: leader sees only their own registered apoyos, coordinator/area_coordinator see their team's (via `teamCoordinatorUserIds()`), admin/super_admin see the full campaign. This is a direct 1:1 replacement, not a behavior regression.
- **D-10:** The treemap **replaces `TerritorialDistributionChart` in place** — its `getData()`/`getChartKind()` swap to the new nested-tree shape and drill-down kind; same widget slot, same sort position. Matches the roadmap's literal wording ("instead of the current flat top-10 bar list").
- **D-11:** Drill-down UX: click a Departamento tile to zoom into its Municipios; click a Municipio tile to zoom into its Barrios; a breadcrumb trail (e.g. "Todos > Antioquia > Medellín") lets the admin jump back up to any level. One level rendered at a time (nest-mode), per the milestone research's explicit recommendation against a flat all-levels-at-once render.
- **D-12:** No leaf-tile cap — show all barrios in a drilled-into municipio and let Recharts' squarified `Treemap` layout handle it. Consistent with this milestone's full-fidelity precedent (Phase 22 D-01/D-06); relies on drill-down already having narrowed the dataset to one municipio's worth of barrios before rendering, which should keep leaf counts small enough to stay legible.

### Heatmap caller×hora (VIZ-09)

- **D-13:** Cell metric is contact rate (%): successful contacts ÷ total call attempts, per caller×hour cell. Uses `CallResult::isSuccessfulContact()` (the same canonical "contactado" definition established in Phase 22 D-08) as the numerator. Shows who's effective when, not just who's busy.
- **D-14:** Many-callers strategy: scroll container, show every caller as a row — no top-N truncation. Consistent with this milestone's full-fidelity precedent; the caller axis can grow arbitrarily without hiding low-volume callers.
- **D-15:** Hour axis is business-hours-only (e.g. 7am-9pm), not a full 24-column grid — a full 24h axis would be mostly empty/wasted columns since campaign calling doesn't realistically happen overnight. Exact start/end hour boundary is Claude's discretion (research existing `VerificationCall.call_date` distribution if helpful, or default to a sane campaign-calling window).
- **D-16:** A caller×hour cell with zero call attempts renders with a distinct "no data" shade, visually separate from a real 0%-effectiveness cell (attempts made, zero successes). Avoids implying a caller who simply didn't call in that hour "failed every call."
- **D-17:** Tooltip must be a real positioned React tooltip (reusing the existing `DitherChartTooltipContent`-style pattern adapted for a synthetic payload), never the native browser `title=` attribute — this is an explicit roadmap success criterion (VIZ-09 point 4), not just a nice-to-have.

### Stacked-area rejection reasons over time (VIZ-10)

- **D-18:** "Rejection reason" = `VoterStatus` rejection states only: `REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `CENSUS_NOT_FOUND`, `CORRECTION_REQUIRED` — each its own stacked-area series, driven by `ValidationHistory.new_status` transitions. Deliberately does NOT fold in `RejectionsCountersOverview`'s mixed `CallResult`-based rejection definition (`REJECTED`/`INVALID_NUMBER`/`NOT_INTERESTED` call outcomes) — keeps this chart to a single data source (`ValidationHistory`) instead of mixing `ValidationHistory` + `VerificationCall`. `DUPLICATE` is excluded, consistent with Phase 22 D-04's precedent of keeping it out of the "rechazado" bucket.
- **D-19:** Time granularity: weekly buckets, over the campaign's full history (no bounded window) — smooths day-to-day noise, shows the real trend shape over the campaign's lifetime, and matches D-08/Phase-22-D-09's "all history" pattern rather than introducing this milestone's first date-bounded chart.
- **D-20:** Each rejection event is bucketed by the `ValidationHistory` row's `created_at` (when the transition to a rejection state happened), not the voter's original registration date.

### Claude's Discretion

- Exact dashboard/resource placement for all 5 new widgets — follow existing precedent: campaign-wide charts (funnel, Sankey, heatmap, stacked-area) on the Admin dashboard alongside `VoterStatusDonutChart`/`RejectionsCountersOverview`; the treemap replaces `TerritorialDistributionChart` in its existing slot per D-10.
- New `ChartRouter.jsx` kind implementations for `funnel` (if not already added in Phase 22 for VIZ-03/04 — confirm and reuse), `sankey`, `treemap`, `heatmap`, `stacked-area` — none of these 4 new kinds exist yet in the router.
- Exact query/service structure for each new aggregation — no existing service class covers Sankey/treemap/heatmap/stacked-area aggregation; new query logic expected in each widget's `getData()`, following the established pattern.
- Business-hours boundary for the heatmap's hour axis (D-15) — pick a reasonable default, optionally grounded in real `VerificationCall.call_date` hour distribution if that's a quick check during research/planning.
- Empty-state and error-state behavior — must follow Phase 20 D-03 (explicit visible error on load/bridge failure) and Phase 21/22's carried-forward pattern; no new decision needed.
- Whether new page-scoped widgets need `AppServiceProvider::PAGE_SCOPED_WIDGETS` registration — apply the Phase 21/22 lesson if any widget attaches via a Page rather than a panel's global `->widgets([...])`.
- Recharts' native `Sankey`/`Treemap` component API specifics (node/link index wiring, `nest`-mode drill-down prop shape) — implementation detail for research/planning, not a product decision.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this milestone's primary technical grounding)
- `.planning/research/FEATURES.md` — "Differentiators" section (Sankey/Treemap/Heatmap/Stacked-area complexity table and rationale), the critical finding that MonoCharts' own Sankey/Treemap source is styling-only (hand-coded static demos, zero Recharts usage) and must be rebuilt against Recharts' real `Sankey`/`Treemap` components, the funnel-shape distinction directly informing D-01/D-02, the explicit "Not Now (Deferred to v2)" list (true streamgraph, literal 12-state funnel) that must NOT be implemented in this phase
- `.planning/research/ARCHITECTURE.md` — `ChartRouter` concept, bridge mechanism, `getType()`→`getChartKind()` pattern new widgets must follow
- `.planning/research/PITFALLS.md` — the page-scoped-widget registration pitfall (recurs across phases — see Claude's Discretion note above)
- `.planning/research/STACK.md` — exact verified package versions already installed (`react@19.2.8`, `recharts@3.10.1`, `motion@13.1.1`)

### Prior phase context
- `.planning/phases/20-react-island-infrastructure/20-CONTEXT.md` — D-02 (theme-flexible card shell), D-03 (explicit visible error state — carries forward to all new widgets), D-04 (human browser-checkpoint verification before considering chart work done)
- `.planning/phases/21-migrate-existing-charts-to-react-recharts/21-CONTEXT.md` — D-02/D-03 (full MonoCharts visual composition + palette, which all 5 new charts in this phase must also adopt)
- `.planning/phases/22-table-stakes-new-visualizations/22-CONTEXT.md` — D-04 (canonical "rechazado" VoterStatus bucket, directly reused for D-18), D-06 (top-N-vs-completeness precedent — this phase diverges deliberately for Sankey/heatmap where the research recommends curation, but stays full-fidelity for treemap leaves per D-12), D-08 (`CallResult::isSuccessfulContact()` canonical "contactado" definition, reused for D-13)

### Existing code with directly reused patterns
- `app/Filament/Widgets/RejectionsCountersOverview.php` (lines 44-48) — canonical rejection-bucket chrome reference for D-02's branch counter row
- `app/Filament/Widgets/TerritorialDistributionChart.php` — the exact widget D-10 replaces in place; its role-scoping logic (`UserRole::LEADER`/`COORDINATOR`/`AREA_COORDINATOR` branches, `teamCoordinatorUserIds()`) is what D-09 carries forward unchanged
- `app/Enums/CallResult.php` — `isSuccessfulContact()`, reused for D-13's heatmap contact-rate numerator

### Requirements and roadmap
- `.planning/REQUIREMENTS.md` — VIZ-06 through VIZ-10 (this phase's exact requirement text), Out of Scope table (explicitly excludes the literal 12-state funnel and true streamgraph — do not implement)
- `.planning/ROADMAP.md` — Phase 23 section (goal, success criteria, dependency on Phase 22)

### Project constraints
- `.planning/PROJECT.md` — Current Milestone section (v1.3 scope) and Constraints section (Operations: reporting/widgets must reflect campaign reality — inaccurate numbers unacceptable, informs D-12's full-fidelity leaf choice and D-01's precise happy-path definition)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Enums/VoterStatus.php` — 12 cases; D-01's happy path is a strict subset (`PENDING_REVIEW`, `VERIFIED_CENSUS`, `CONFIRMED`, `VOTED`), D-02's branch row covers the remaining non-DUPLICATE terminal/rejection states
- `app/Models/ValidationHistory.php` — `previous_status`/`new_status` (both cast to `VoterStatus`), `voter_id`, `created_at`. No `campaign_id` directly — must join through `voter_id` → `voters.campaign_id` for campaign scoping (same join pattern needed for Sankey D-05/D-08 and stacked-area D-18/D-20)
- `app/Filament/Widgets/TerritorialDistributionChart.php` — current flat-bar implementation D-10 replaces; already has the exact role-scoping branches (`hasRole(LEADER)`, `hasAnyRole(COORDINATOR, AREA_COORDINATOR)`) D-09 reuses verbatim
- `app/Models/VerificationCall.php` — `caller_id`, `attempt_number`, `call_date` (datetime), `call_result` (cast to `CallResult`); no `campaign_id` directly — must join through `voter_id` → `voters.campaign_id`
- `app/Enums/CallResult.php` — `isSuccessfulContact()`, canonical for D-13
- `app/Filament/Widgets/RejectionsCountersOverview.php` — chrome/pattern reference for D-02's branch counter row; note its "rechazados" definition mixes `VoterStatus` + `CallResult` and is deliberately NOT reused for D-18 (which stays `VoterStatus`-only)
- `app/Models/Department.php`, `app/Models/Municipality.php`, `app/Models/Neighborhood.php` — territorial hierarchy models; `Voter` has `municipality_id` and `neighborhood_id` directly, department comes through `Municipality`'s relation (needs confirming during research/planning)
- `resources/js/charts/lib/chartjs-adapter.js` — existing adapter file where new data-shaping helpers for Sankey/treemap/heatmap/stacked-area likely belong, following the `toSeriesRows` precedent from Phase 22

### Established Patterns
- Every existing chart widget extends Filament's `ChartWidget`, sets `protected string $view = 'filament.widgets.react-chart'`, implements `getData()`/`getType()`-`getChartKind()` — new widgets in this phase follow the same shape
- `ChartRouter.jsx` registers kinds incrementally per phase (Phase 21: `line`/`bar`/`pie`/`sparkline`; Phase 22 added `stacked-bar`/`funnel`/`gauge`/`histogram`) — this phase adds `sankey`/`treemap`/`heatmap`/`stacked-area` (funnel kind may already exist from Phase 22 VIZ-03/04, confirm before re-adding)
- No aggregation query exists yet for any of the 5 new charts — all need fresh query logic in each widget's `getData()`

### Integration Points
- Admin dashboard — natural home for funnel (VIZ-06), Sankey (VIZ-07), heatmap (VIZ-09), stacked-area (VIZ-10), alongside existing `VoterStatusDonutChart`/`RejectionsCountersOverview`
- `TerritorialDistributionChart`'s existing widget registration slot — where the treemap (VIZ-08) replaces it in place per D-10
- `AppServiceProvider::PAGE_SCOPED_WIDGETS` — must be checked/updated if any new widget attaches via a Page's `getHeaderWidgets()`/`getFooterWidgets()` rather than panel-global registration

</code_context>

<specifics>
## Specific Ideas

No new specific visual reference beyond continuing to pull MonoCharts' actual source for chrome/palette/animation (established Phase 21 D-02/D-03) for chart chrome — but for Sankey and Treemap specifically, MonoCharts' own components are explicitly NOT usable as data-shape/component-usage references (per `FEATURES.md`'s critical finding: both are hand-coded static demos with zero Recharts usage). Only their visual styling (colors, radii, chrome) transfers; the actual `Sankey`/`Treemap` component wiring must be built fresh against Recharts' real APIs.

</specifics>

<deferred>
## Deferred Ideas

- True symmetric ThemeRiver-style streamgraph for rejection reasons — explicitly out of scope (REQUIREMENTS.md v2 VIZ-11), deferred until the standard stacked-area (D-18/D-19) is judged insufficient after real usage; requires custom `d3-shape` work Recharts doesn't provide natively.
- Literal trapezoid funnel of all 12 `VoterStatus` states (superseding VIZ-06's happy-path subset) — explicitly out of scope (REQUIREMENTS.md v2 VIZ-12), deferred until a complete product definition of every branch's funnel semantics exists.
- Bounded date-range filtering for the Sankey (VIZ-07) or heatmap (VIZ-09) — not raised during discussion; both stay unbounded/all-history per D-08 and implicitly D-19's precedent, could be revisited if real usage shows old data diluting relevance.
- Click-to-drill-through from any of these 5 charts to a filtered voter/call list (beyond the treemap's own zoom-in drill-down, which is a different interaction) — not discussed, consistent with Phase 22 D-02's precedent of no drill-through on any new chart this milestone.

### Reviewed Todos (not folded)

None — `gsd-tools todo match-phase 23` returned zero matches.

</deferred>

---

*Phase: 23-differentiator-visualizations*
*Context gathered: 2026-08-21*
