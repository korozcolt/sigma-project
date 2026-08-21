# Phase 21: Migrate Existing Charts to React/Recharts - Context

**Gathered:** 2026-08-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 21 migrates the 3 existing `ChartWidget`s (`ValidationProgressChart`, `TerritorialDistributionChart`, `SurveyResultsWidget`) and the 3 embedded sparklines (`CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`) off Chart.js and onto the React/Recharts island infrastructure built in Phase 20 — with each widget's existing campaign/role-scoped `getData()` query completely unchanged. This is the first phase to apply real MonoCharts visual composition (deliberately deferred by Phase 20's D-01). It is NOT about any new chart type, new data, or new business logic — that belongs to Phase 22 onward.

</domain>

<decisions>
## Implementation Decisions

### Sparkline migration strategy

- **D-01:** The 3 embedded sparklines are migrated as **dedicated small `ChartWidget`-based widgets** placed next to their parent `StatsOverviewWidget` in the panel's `->widgets([...])` array — not as a custom `Stat`-shaped Blade partial embedded inside the Stat cell itself. The parent `StatsOverviewWidget`'s numeric `Stat::make()` cells stay exactly as they are today (including their existing `->chart()` Chart.js sparkline as a fallback until the new widget replaces it visually, or removed once replaced — Claude's discretion on the exact swap mechanics). Rationale: reuses the exact same proven Phase 20 pattern (Blade view, Alpine bridge, `dispatch()`/checksum plumbing) as the 3 big charts, since `Stat` has no `wire:poll`-per-item primitive of its own to fight with. Matches research's own recommendation (ARCHITECTURE.md line 208).

### Visual fidelity level

- **D-02:** All 6 migrated widgets (3 big charts + 3 sparklines) adopt **full MonoCharts visual composition now** — nested card shell, rounded/monochrome bars, header/footer chrome, staggered entrance animation via Motion — not a minimal "swap the rendering engine only" re-skin. Rationale: Phase 20's D-01 explicitly framed this as deferred *to* Phase 21 ("Full MonoCharts visual composition is layered on starting Phase 21"), and doing a minimal re-skin now would mean a second visual pass later once Phase 22+ ships new charts with full MonoCharts styling — these 3 charts would look inconsistent/dated by comparison in the meantime.

### Color palette

- **D-03:** These 6 widgets adopt the **MonoCharts monochrome/rounded palette**, replacing the current ad hoc hardcoded hex colors (e.g. `#3b82f6` blue / `#10b981` green on `ValidationProgressChart`, the 10-color rotation on `TerritorialDistributionChart`). Rationale: keeping old ad hoc colors under new MonoCharts chrome (D-02) would look mismatched — one consistent palette starts now, not partway through the milestone.

### SurveyResultsWidget's dynamic chart type

- **D-04:** `SurveyResultsWidget`'s runtime chart-type switching (pie for `YES_NO` questions, bar for `SCALE`/`SINGLE_CHOICE`/`MULTIPLE_CHOICE`, decided by `getType()`/`getChartKind()` reading `question_type`) is preserved unchanged. The new `ChartRouter` must support a widget instance whose `chartKind` varies per-render rather than being fixed per widget class — this is a plumbing requirement, not a product/UX change. No behavior change for end users.

### Claude's Discretion

- Exact naming/placement of the new small sparkline `ChartWidget` subclasses (e.g. `CampaignVotersSparklineWidget`) and whether/how the old `Stat::chart()` Chart.js sparkline is removed from the parent `StatsOverviewWidget` vs. left temporarily dormant during the swap.
- `ChartRouter.tsx` internal component structure (per-kind file layout under `resources/js/charts/`) mapping `chartKind` → `MonoRounded*`-style Recharts component, as long as it supports `line`, `bar` (including stacked/grouped variants used by `TerritorialDistributionChart`), and `pie` kinds plus `SurveyResultsWidget`'s dynamic kind requirement (D-04).
- Exact MonoCharts color tokens/CSS variables adopted for the new palette (D-03) — pull directly from MonoCharts' actual source per FEATURES.md, not invented independently.
- Whether legend/tooltip/interaction-mode parity (e.g. `ValidationProgressChart`'s `interaction.mode = 'index'`) is replicated via Recharts' own `Tooltip`/`Legend` props or a MonoCharts-style custom tooltip component — pick whichever keeps the visual fidelity decision (D-02) coherent.
- Which panel(s) register the new small sparkline widgets, mirroring wherever their parent `StatsOverviewWidget` is already registered today.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this milestone's primary technical grounding)
- `.planning/research/ARCHITECTURE.md` — the verified bridge mechanism, `ChartRouter` concept (picks Recharts component by `chartKind` prop), `getType()`→`getChartKind()` renaming rationale (line 301), and the sparkline migration-strategy analysis this phase's D-01 is based on (line 208)
- `.planning/research/FEATURES.md` — MonoCharts source reusability findings for line/bar/pie/sparkline (`MonoRoundedLineChart`, `MonoRoundedStackedBarChart`, `MonoRoundedDonutChart`, `MonoRoundedSparklineChart` — all directly reusable per the table-stakes section), and the "Critical Finding" section on which MonoCharts components are real vs. hardcoded demos (relevant context even though Sankey/Treemap/Heatmap belong to Phase 23, not this phase)
- `.planning/research/PITFALLS.md` — the 6 critical pitfalls already centrally solved by Phase 20's bridge (stale/orphaned roots, leaked roots on navigation, event-delegation conflicts, missing per-panel registration, coverage-theater tests, false-hydration confusion) — this phase's widgets inherit that solved plumbing, no new pitfall work expected here
- `.planning/research/STACK.md` — exact verified package versions already installed in Phase 20 (`react@19.2.8`, `recharts@3.10.1`, `motion@13.1.1`)
- `.planning/research/SUMMARY.md` — Phase 21 delivery framing (line 79): `react-chart.blade.php` shared view generalized, `ChartRouter.tsx` skeleton built, 3 `ChartWidget`s migrated, sparkline strategy decided

### Prior phase context
- `.planning/phases/20-react-island-infrastructure/20-CONTEXT.md` — D-01 (visual fidelity deferred to this phase), D-02 (theme-flexible card shell, light-only default today), D-03 (explicit visible error state on load/bridge failure — must carry forward to all 6 migrated widgets), D-04 (human browser-checkpoint verification pattern before considering chart work done)

### Requirements and roadmap
- `.planning/REQUIREMENTS.md` — MIGR-01, MIGR-02 (this phase's exact requirement text)
- `.planning/ROADMAP.md` — Phase 21 section (goal, success criteria, dependencies on Phase 20)

### Project constraints
- `.planning/PROJECT.md` — Current Milestone section (v1.3 scope) and Constraints section (Architecture: harden Laravel/Filament/Livewire in place; Operations: reporting/widgets must reflect campaign reality — inaccurate numbers unacceptable, directly informs the error-state carryover from Phase 20 D-03)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Filament/Widgets/ReactIslandPocWidget.php` + `resources/views/filament/widgets/react-chart.blade.php` — Phase 20's proven `ChartWidget`-subclass-with-custom-view pattern; the shared Blade view needs generalizing from PoC-only to serve all migrated widgets (per SUMMARY.md's "one shared view" framing)
- `resources/js/charts/main.jsx` — the Alpine bridge (`reactChartBridge`), poll→`dispatch('updateChartData')`→`root.render()` cycle, and the `livewire:navigate` cleanup registry — all reusable as-is, no changes expected
- `resources/js/charts/components/ChartCard.jsx` — currently a PoC-only hardcoded bar chart; must be replaced/generalized into the `ChartRouter.tsx` concept described in ARCHITECTURE.md, dispatching to real MonoCharts-style components per `chartKind`
- `app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php`, `SurveyResultsWidget.php` — the 3 widgets to migrate; each has a `getData()`/`getType()`/`getOptions()` shape from `ChartWidget` that needs re-pointing to the new `$view` + `getChartKind()` pattern, `getData()` bodies untouched (MIGR-01)
- `app/Filament/Widgets/CampaignStatsOverview.php`, `CallCenterStatsWidget.php`, `SurveyStatsOverview.php` — each has exactly one `Stat::make(...)->chart([...])` call carrying a sparkline (confirmed via code scan); the rest of their `Stat`s are plain numeric cells unaffected by this migration

### Established Patterns
- `ValidationProgressChart` and `TerritorialDistributionChart` both use `pollingInterval = '120s'` — same cadence the Phase 20 bridge is proven against
- `SurveyResultsWidget::getType()` already dynamically branches on `question_type` (`QuestionType::YES_NO` → pie, others → bar) — the one widget in this phase with non-fixed chart kind

### Integration Points
- Panel `->widgets([...])` arrays (`app/Providers/Filament/*PanelProvider.php`) where `CampaignStatsOverview`/`CallCenterStatsWidget`/`SurveyStatsOverview` are currently registered — new sparkline widgets get added alongside them in the same arrays
- `tests/Browser/` + `loginRealBrowserUser()` helper (`tests/Pest.php`) — reuse for the 6 new/updated Pest 4 Browser tests this phase requires (one per migrated widget, per Success Criteria #3)

</code_context>

<specifics>
## Specific Ideas

No new specific visual reference beyond "adopt MonoCharts' real composition now" (D-02) — the exact chrome/animation/palette details should be pulled directly from MonoCharts' actual source (already catalogued in FEATURES.md), not invented. The Phase 20 error-state requirement ("no debe verse como un chart roto mostrando ceros") carries forward unchanged to all 6 widgets in this phase.

</specifics>

<deferred>
## Deferred Ideas

- Any new chart type, new data source, or new business insight — explicitly out of scope for this phase (belongs to Phase 22+, this phase is migration-only per MIGR-01/MIGR-02's "existing" framing).
- Simplifying/changing `SurveyResultsWidget`'s dynamic pie/bar switching behavior — flagged during discussion but explicitly kept as-is (D-04); any future simplification would be a product/UX decision outside this migration phase.

### Reviewed Todos (not folded)

None — no pending todos matched Phase 21 during `cross_reference_todos`.

</deferred>

---

*Phase: 21-migrate-existing-charts-to-react-recharts*
*Context gathered: 2026-08-20*
