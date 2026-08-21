# Phase 22: Table-Stakes New Visualizations - Context

**Gathered:** 2026-08-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 22 adds 5 new chart widgets rendering already-existing (or trivially aggregatable) operational data through the React/Recharts island infrastructure built in Phases 20-21: a donut of the 12 `VoterStatus` states, a stacked-bar comparison of registered/validated/rejected apoyos per coordinator, a funnel of call contactability by attempt number, a funnel of message delivery, and a gauge+histogram of SCALE-type survey response scores. It is NOT about Phase 23's curated/differentiator visualizations (Sankey, treemap, heatmap, stacked-area over time) or Phase 24's live Día D polling chart — those depend on Phase 22 but are out of scope here.

</domain>

<decisions>
## Implementation Decisions

### Donut de estados (VIZ-01)

- **D-01:** Show all 12 `VoterStatus` states as their own donut segment — no grouping into "Otros". User explicitly chose the full-fidelity option over the research-flagged grouping alternative, prioritizing exact operational numbers over visual tidiness (consistent with the project's standing "inaccurate operational numbers are unacceptable" constraint).
- **D-02:** Hover/tooltip only — no click-to-drill-through to a filtered voter list. This keeps Phase 22 scoped to visualization; the existing drill-through pattern (Phase 4/05.1) is not extended to any new chart in this phase. None of Phase 21's migrated charts have drill-through either, so this stays consistent.

### Barras apiladas por coordinador (VIZ-02)

- **D-03:** "Validado" = `VERIFIED_CENSUS` + `VERIFIED_REGISTRADURIA` + `VERIFIED_CALL` + `CONFIRMED`. Rationale: the voter passed some formal verification, regardless of whether they've reached Día D yet. Explicitly excludes `VOTED`/`DID_NOT_VOTE` (kept in the "registrado" residual bucket per D-04, not double-counted as a 4th category) and excludes `PENDING_REVIEW`/`CORRECTION_REQUIRED` (still in-process).
- **D-04:** "Rechazado" = `REJECTED_CENSUS` + `REJECTED_OUT_OF_SCOPE` + `CENSUS_NOT_FOUND` + `CORRECTION_REQUIRED` — reusing the exact bucket already defined in `RejectionsCountersOverview.php` (lines 44-48), not inventing a new definition.
- **D-05:** "Registrado" is the residual bucket: every voter belonging to the coordinator's team that isn't in "validado" or "rechazado" (includes `PENDING_REVIEW`, `DUPLICATE`, and anything else not explicitly mapped). The 3 stacked segments always sum to the coordinator's exact total apoyo count — no voter is silently dropped from the chart.
- **D-06:** Show **all** coordinators in the active campaign, not top-N. User explicitly rejected the top-N/legibility-first recommendation in favor of completeness. If bar width becomes a real legibility problem at high coordinator counts, that's an implementation detail (horizontal scroll, responsive sizing) — not a data-scope cut.
- **D-07:** "Team" = coordinator + their líderes, reusing the exact resolution pattern already proven in `TopCoordinatorsTable.php` (`$record->leaders()->pluck('id')->push($record->id)`).

### Funnels — contactabilidad de llamadas y entrega de mensajes (VIZ-03, VIZ-04)

- **D-08:** The call-contactability funnel measures **persistence**, not per-attempt success rate. Stage "Intento 1" = voters with at least 1 call attempt; "Intento 2" = voters who reached a 2nd attempt (not contacted on the 1st); "Intento 3+" = voters who reached a 3rd attempt or beyond; "Contactado" = voters successfully contacted on any attempt (`CallResult::isSuccessfulContact()` / `VerificationCall::scopeSuccessful()`). This produces a genuinely monotonically-decreasing funnel shape, matching Recharts' native `Funnel` component semantics — not the per-attempt-success-rate alternative, which would not be monotonic and would need a bar chart instead.
- **D-09:** The message-delivery funnel (`enviado→entregado→leído→clic`) covers **all `MessageBatch`/`Message` records historically**, campaign-scoped, with no batch/date-range filter or selector in this phase. Uses the existing `sent_at`/`delivered_at`/`read_at`/`clicked_at` timestamp columns on `Message` directly (`MessageBatch` only pre-aggregates sent/delivered/failed — read/clicked must be counted from `Message` rows, not the batch's stored counters).

### Gauge + histograma de encuestas SCALE (VIZ-05)

- **D-10:** The gauge/histogram is scoped to **one specific survey/question at a time**, not a global cross-survey normalized average. Reuses `SurveyMetricsCalculator`'s existing `calculateScaleAverage()` (gauge) and `calculateScaleDistribution()` (histogram) output — read from `SurveyMetrics.average_value`/`SurveyMetrics.distribution` for that `survey_question_id` — with zero new aggregation logic needed. Avoids the cross-question range-normalization problem entirely (SCALE ranges are per-question configurable, e.g. 1-5 vs 1-10).
- **D-11:** Placement: the gauge+histogram lives on the survey detail page (`ViewSurvey`/`EditSurvey`), one instance per SCALE question in that survey — same footer-widgets pattern Phase 21 already established for `SurveyResultsWidget`. Not a standalone admin-dashboard widget.

### Claude's Discretion

- Exact dashboard/resource placement for the donut (VIZ-01) and stacked-bar (VIZ-02) widgets — not discussed explicitly; follow the existing precedent of similar widgets (`TerritorialDistributionChart`, `TopCoordinatorsTable`) living on the Admin dashboard.
- Placement for the two funnels (VIZ-03, VIZ-04) — attach to their natural Resource context (`VerificationCallResource`/Call Center pages for the contactability funnel, `MessageBatchResource`/Messaging pages for the delivery funnel), mirroring how `SurveyResultsWidget` attaches to Survey pages rather than a generic dashboard.
- New `ChartRouter.jsx` component/kind implementation for `stacked-bar`, `funnel`, `gauge`, and `histogram` (none exist yet — only `line`, `bar`, `pie`, `sparkline` are registered today). `toSeriesRows` in `chartjs-adapter.js` already produces the multi-series shape stacked-bar needs but is currently unused by any component.
- Whether the funnel chart kind uses Recharts' native `Funnel`/`FunnelChart` component (research's recommended option for these 2 naturally-monotonic funnels) vs. MonoCharts' horizontal-bar-chart trick — pick whichever keeps visual fidelity consistent with the Phase 21 MonoCharts palette/chrome decisions.
- Exact query/service structure for each new aggregation (donut group-by-status, coordinator 3-bucket pivot, funnel counts, message funnel counts) — no existing service class covers these; new query logic is expected, following each existing widget's `getData()` pattern.
- Empty-state and error-state behavior — must follow Phase 20 D-03 (explicit visible error on load/bridge failure, never a silent blank or misleadingly-zero-looking chart) and Phase 21's carried-forward pattern; no new decision needed, just apply the existing standard.
- Whether new chart widgets are page-scoped (attached via a Page's `getHeaderWidgets()`/`getFooterWidgets()`) or panel-global — if page-scoped, remember the established `PAGE_SCOPED_WIDGETS` registration requirement (`AppServiceProvider`) that Phase 21 hit repeatedly, or the widget will throw `ComponentNotFoundException` on its first `wire:poll` tick.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this milestone's primary technical grounding)
- `.planning/research/FEATURES.md` — "Table Stakes" section (donut/stacked-bar/gauge/histogram/funnel complexity table), the funnel-shape distinction (persistence-style vs literal-monotonic vs the non-monotonic 12-state case — directly informs D-08), the "Launch With (v1.3 core — table stakes)" P1 list this phase implements
- `.planning/research/ARCHITECTURE.md` — `ChartRouter` concept, bridge mechanism, `getType()`→`getChartKind()` pattern new widgets must follow
- `.planning/research/PITFALLS.md` — the 6 critical pitfalls already centrally solved by Phase 20's bridge; the page-scoped-widget registration pitfall specifically (recurs — see Claude's Discretion note above)
- `.planning/research/STACK.md` — exact verified package versions already installed (`react@19.2.8`, `recharts@3.10.1`, `motion@13.1.1`)

### Prior phase context
- `.planning/phases/20-react-island-infrastructure/20-CONTEXT.md` — D-02 (theme-flexible card shell), D-03 (explicit visible error state — carries forward to all new widgets), D-04 (human browser-checkpoint verification before considering chart work done)
- `.planning/phases/21-migrate-existing-charts-to-react-recharts/21-CONTEXT.md` — D-01 (dedicated small-widget sparkline pattern — relevant precedent for how new widgets should be structured), D-02/D-03 (full MonoCharts visual composition + palette, which new charts in this phase must also adopt, not just re-skin), D-04 (dynamic-chart-kind proxy pattern, relevant if any new chart needs runtime kind switching)

### Requirements and roadmap
- `.planning/REQUIREMENTS.md` — VIZ-01 through VIZ-05 (this phase's exact requirement text)
- `.planning/ROADMAP.md` — Phase 22 section (goal, success criteria, dependency on Phase 21)

### Project constraints
- `.planning/PROJECT.md` — Current Milestone section (v1.3 scope) and Constraints section (Operations: reporting/widgets must reflect campaign reality — inaccurate numbers unacceptable, directly informs D-01/D-06's full-fidelity choices)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Enums/VoterStatus.php` — 12 cases (`PENDING_REVIEW`, `REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `CENSUS_NOT_FOUND`, `VERIFIED_CENSUS`, `VERIFIED_REGISTRADURIA`, `CORRECTION_REQUIRED`, `VERIFIED_CALL`, `CONFIRMED`, `VOTED`, `DID_NOT_VOTE`, `DUPLICATE`)
- `app/Filament/Widgets/RejectionsCountersOverview.php` (lines 44-48) — canonical "Rechazados" bucket definition, reused verbatim for D-04
- `app/Filament/Widgets/TopCoordinatorsTable.php` — canonical coordinator-team resolution pattern (`$record->leaders()->pluck('id')->push($record->id)`), reused for D-07; also the closest precedent for a per-coordinator voter aggregate query (though it only produces one count today, not a 3-way split)
- `app/Enums/CallResult.php` — `isSuccessfulContact()` (true for `ANSWERED`, `CONFIRMED`, `CALLBACK_REQUESTED`) is the canonical "contactado" definition for D-08; also exposed as `VerificationCall::scopeSuccessful()`/`isSuccessful()`
- `app/Models/VerificationCall.php` — `attempt_number` (int) field; no `campaign_id` on this model directly, must join through `voter_id` → `voters.campaign_id` for campaign scoping
- `app/Services/CallAssignmentService.php:64`, `app/Services/CallCenterService.php:36,61` — both hardcode `attempt_number < 3`, confirming "3+" as the existing business-logic attempt cap (validates D-08's bucket boundary)
- `app/Models/Message.php` — `sent_at`/`delivered_at`/`read_at`/`clicked_at` nullable timestamps, directly usable for the D-09 funnel via `count(sent_at)`/`count(delivered_at)`/etc.
- `app/Models/MessageBatch.php` — pre-aggregates `sent_count`/`delivered_count`/`failed_count` only; does NOT store read/clicked counts — those must be queried from `Message` rows directly
- `app/Services/SurveyMetricsCalculator.php` — `calculateScaleAverage()` and `calculateScaleDistribution()` already compute exactly what D-10 needs; persisted to `app/Models/SurveyMetrics.php` (`average_value`, `distribution` JSON columns, keyed by `survey_question_id` + `metric_type`)
- `app/Models/SurveyQuestion.php` — `configuration` JSON field holds per-question SCALE min/max (defaults to 1-5 if unset per `SurveyMetricsCalculator`)
- `resources/js/charts/components/PieChart.jsx` — already renders as a donut (`innerRadius=60`/`outerRadius=90`); VIZ-01 reuses this directly via `kind: 'pie'`, no new component needed
- `resources/js/charts/lib/chartjs-adapter.js` — `toSeriesRows` already produces the multi-series row shape a stacked-bar component needs, currently unused by any chart component

### Established Patterns
- Every existing chart widget extends Filament's `ChartWidget`, sets `protected string $view = 'filament.widgets.react-chart'`, implements `getData()`/`getType()`-`getChartKind()` — new widgets in this phase follow the same shape
- `ChartRouter.jsx` currently registers exactly 4 kinds: `line`, `bar`, `pie`, `sparkline` — `stacked-bar`, `funnel`, `gauge`, `histogram` all need new entries + components
- No `GROUP BY status`/coordinator-pivot/funnel-count/aggregation query exists yet for any of the 5 new charts except VIZ-05 (fully covered by `SurveyMetricsCalculator`) — all other 4 need fresh query logic in each widget's `getData()`

### Integration Points
- `app/Filament/Resources/VerificationCalls/VerificationCallResource.php` — natural attachment point for the call-contactability funnel (VIZ-03)
- `app/Filament/Resources/Messages/MessageBatchResource.php` — natural attachment point for the message-delivery funnel (VIZ-04)
- `app/Filament/Resources/Surveys/SurveyResource.php` (`Pages/ViewSurvey`/`EditSurvey`) — required attachment point for the SCALE gauge+histogram (VIZ-05, per D-11)
- `AppServiceProvider::PAGE_SCOPED_WIDGETS` — any new widget attached via a Page's `getHeaderWidgets()`/`getFooterWidgets()` (rather than a panel's global `->widgets([...])`) must be added here or it will throw `ComponentNotFoundException` on its first `wire:poll` tick (hit repeatedly in Phase 21)

</code_context>

<specifics>
## Specific Ideas

No new specific visual reference beyond continuing to pull MonoCharts' actual source for chrome/palette/animation (already established in Phase 21 D-02/D-03) for the 4 net-new chart kinds this phase introduces (stacked-bar, funnel, gauge, histogram). The Phase 20 error-state requirement ("no debe verse como un chart roto mostrando ceros") carries forward unchanged.

</specifics>

<deferred>
## Deferred Ideas

- Click-to-drill-through interaction for the donut (VIZ-01) or any other new Phase 22 chart — explicitly declined for this phase (D-02); could be revisited as a future enhancement once the base visualizations are proven useful.
- Batch/date-range filtering for the message-delivery funnel (VIZ-04) — explicitly declined for this phase (D-09); the full-history view is the starting point, filtering could be added later if operationally needed.
- Global cross-survey normalized SCALE average — explicitly declined for this phase (D-10) in favor of per-survey/question scoping; would require new normalization logic not currently justified.
- Top-N truncation for the coordinator stacked-bar (VIZ-02) — explicitly declined (D-06); if legibility becomes a real problem at scale, revisit as an implementation-detail fix (scroll/responsive sizing), not a scope change.

### Reviewed Todos (not folded)

None — `gsd-tools todo match-phase 22` returned zero matches.

</deferred>

---

*Phase: 22-table-stakes-new-visualizations*
*Context gathered: 2026-08-21*
