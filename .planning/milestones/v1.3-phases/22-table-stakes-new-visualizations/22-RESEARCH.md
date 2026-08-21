# Phase 22: Table-Stakes New Visualizations - Research

**Researched:** 2026-08-21
**Domain:** New Recharts chart-kind components (`stacked-bar`, `funnel`, `gauge`, `histogram`) + PHP `ChartWidget` aggregation queries, built on the Phase 20/21 React-island infrastructure
**Confidence:** HIGH — every finding below is grounded in direct reads of this repository's own source (widgets, models, JS chart components) plus the already-completed milestone research (FEATURES.md/ARCHITECTURE.md/PITFALLS.md/STACK.md). No external library research was needed beyond what those documents already verified (Recharts 3.10.1 native `Funnel`/`Pie`/`BarChart` APIs).

## Summary

Phase 22 does not need any new npm package, any new Vite entry, or any change to the Livewire↔React bridge — all of that is already built and proven by Phases 20-21. The entire phase is: (1) 4 new small Recharts-based `.jsx` components registered in `ChartRouter.jsx` (`stacked-bar`, `funnel`, `gauge`, `histogram`), each consuming the *same* `{labels, datasets}` PHP contract already used by every migrated widget; (2) 5 new `ChartWidget` PHP subclasses, each following the exact `$view = 'filament.widgets.react-chart'` + `getType()`→`getChartKind()` + `getData()` shape already proven by `TerritorialDistributionChart`/`SurveyResultsWidget`; and (3) widget registration in the correct panel/page location, with page-scoped widgets added to `AppServiceProvider::PAGE_SCOPED_WIDGETS`.

Four of five widgets need brand-new aggregation queries (VIZ-01 donut, VIZ-02 stacked-bar, VIZ-03 call funnel, VIZ-04 message funnel) — none has an existing service to reuse, but each has a very close precedent to copy the *shape* of scoping/query logic from (`TerritorialDistributionChart::getData()` for campaign-scoping, `TopCoordinatorsTable::table()` for the coordinator-team resolution pattern, `RejectionsCountersOverview::getStats()` for the exact rejected-bucket definition). VIZ-05 (gauge+histogram) needs **zero** new aggregation — `SurveyMetricsCalculator`'s already-computed `SurveyMetrics.average_value`/`SurveyMetrics.distribution` columns are read directly, exactly like `SurveyResultsWidget` already does.

The one genuinely novel *engineering* decision this phase must make is the funnel chart kind: Recharts ships a real `Funnel`/`FunnelChart` component (native trapezoid layout) that fits VIZ-03/VIZ-04 well because both are genuinely monotonically decreasing per CONTEXT.md's D-08/D-09 bucket definitions — this is a different, harder-to-verify component than the `Pie`/`Bar` primitives Phase 21 already exercised, since neither MonoCharts' own source nor this codebase has used it before. Recommendation below is to build it directly against Recharts' native API (not MonoCharts' bar-chart trick), because the milestone's own FEATURES.md research already concluded the bar-chart trick is the *fallback for the non-monotonic case* (the 12-state voter lifecycle, explicitly out of scope for Phase 22/deferred to Phase 23's VIZ-06), while VIZ-03/VIZ-04 are exactly the case FEATURES.md flagged as "a real trapezoid Funnel looks correct and is worth the extra effort."

**Primary recommendation:** Build all 5 widgets as thin `ChartWidget` subclasses returning the existing `{labels, datasets}` contract (stacked-bar/histogram) or a small kind-specific extension of it (funnel: `{labels, datasets: [{label, data}]}` where `data[i]` is the stage value in strictly-provided order; gauge: `{value, min, max}` alongside a `label`/`emptyReason` for the existing empty-state convention) — then add exactly 4 new presentational components under `resources/js/charts/components/` and register them in `ChartRouter.jsx`. No architecture, bridge, or Vite change of any kind is needed this phase.

## User Constraints

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Donut de estados (VIZ-01)**
- D-01: Show all 12 `VoterStatus` states as their own donut segment — no grouping into "Otros". Full-fidelity chosen over visual tidiness per the project's "inaccurate operational numbers are unacceptable" constraint.
- D-02: Hover/tooltip only — no click-to-drill-through to a filtered voter list. Consistent with all Phase 21 migrated charts (none have drill-through either).

**Barras apiladas por coordinador (VIZ-02)**
- D-03: "Validado" = `VERIFIED_CENSUS` + `VERIFIED_REGISTRADURIA` + `VERIFIED_CALL` + `CONFIRMED`. Explicitly excludes `VOTED`/`DID_NOT_VOTE` (kept in "registrado" residual, D-04) and excludes `PENDING_REVIEW`/`CORRECTION_REQUIRED` (still in-process).
- D-04: "Rechazado" = `REJECTED_CENSUS` + `REJECTED_OUT_OF_SCOPE` + `CENSUS_NOT_FOUND` + `CORRECTION_REQUIRED` — reusing the exact bucket already defined in `RejectionsCountersOverview.php` (lines 44-48), not inventing a new definition.
- D-05: "Registrado" is the residual bucket: every voter belonging to the coordinator's team not in "validado" or "rechazado" (includes `PENDING_REVIEW`, `DUPLICATE`, anything else unmapped). The 3 stacked segments always sum to the coordinator's exact total apoyo count — no voter silently dropped.
- D-06: Show **all** coordinators in the active campaign, not top-N. User explicitly rejected top-N/legibility-first recommendation in favor of completeness. Legibility problems at scale are an implementation detail (scroll/responsive sizing), not a data-scope cut.
- D-07: "Team" = coordinator + their líderes, reusing the exact resolution pattern already proven in `TopCoordinatorsTable.php` (`$record->leaders()->pluck('id')->push($record->id)`).

**Funnels — contactabilidad de llamadas y entrega de mensajes (VIZ-03, VIZ-04)**
- D-08: The call-contactability funnel measures **persistence**, not per-attempt success rate. Stage "Intento 1" = voters with at least 1 call attempt; "Intento 2" = voters who reached a 2nd attempt (not contacted on the 1st); "Intento 3+" = voters who reached a 3rd attempt or beyond; "Contactado" = voters successfully contacted on any attempt (`CallResult::isSuccessfulContact()` / `VerificationCall::scopeSuccessful()`). This produces a genuinely monotonically-decreasing funnel shape, matching Recharts' native `Funnel` component semantics — not the per-attempt-success-rate alternative (would not be monotonic, would need a bar chart instead).
- D-09: The message-delivery funnel (`enviado→entregado→leído→clic`) covers **all** `MessageBatch`/`Message` records historically, campaign-scoped, with no batch/date-range filter or selector in this phase. Uses `sent_at`/`delivered_at`/`read_at`/`clicked_at` timestamp columns on `Message` directly (`MessageBatch` only pre-aggregates sent/delivered/failed — read/clicked must be counted from `Message` rows, not the batch's stored counters).

**Gauge + histograma de encuestas SCALE (VIZ-05)**
- D-10: Scoped to **one specific survey/question at a time**, not a global cross-survey normalized average. Reuses `SurveyMetricsCalculator`'s existing `calculateScaleAverage()` (gauge) and `calculateScaleDistribution()` (histogram) output — read from `SurveyMetrics.average_value`/`SurveyMetrics.distribution` for that `survey_question_id` — zero new aggregation logic needed. Avoids cross-question range-normalization (SCALE ranges are per-question configurable, e.g. 1-5 vs 1-10).
- D-11: Placement: the gauge+histogram lives on the survey detail page (`EditSurvey` — no separate `ViewSurvey` page exists in this codebase), one instance per SCALE question in that survey — same footer-widgets pattern Phase 21 already established for `SurveyResultsWidget`. Not a standalone admin-dashboard widget.

### Claude's Discretion

- Exact dashboard/resource placement for the donut (VIZ-01) and stacked-bar (VIZ-02) widgets — follow the existing precedent of similar widgets (`TerritorialDistributionChart`, `TopCoordinatorsTable`) living on the Admin dashboard.
- Placement for the two funnels (VIZ-03, VIZ-04) — attach to their natural Resource context (`VerificationCallResource`/Call Center pages for the contactability funnel, `MessageBatchResource`/Messaging pages for the delivery funnel), mirroring how `SurveyResultsWidget` attaches to Survey pages rather than a generic dashboard.
- New `ChartRouter.jsx` component/kind implementation for `stacked-bar`, `funnel`, `gauge`, and `histogram` (none exist yet — only `line`, `bar`, `pie`, `sparkline` are registered today). `toSeriesRows` in `chartjs-adapter.js` already produces the multi-series shape stacked-bar needs but is currently unused by any component.
- Whether the funnel chart kind uses Recharts' native `Funnel`/`FunnelChart` component vs. MonoCharts' horizontal-bar-chart trick — pick whichever keeps visual fidelity consistent with the Phase 21 MonoCharts palette/chrome decisions.
- Exact query/service structure for each new aggregation (donut group-by-status, coordinator 3-bucket pivot, funnel counts, message funnel counts) — no existing service class covers these; new query logic is expected, following each existing widget's `getData()` pattern.
- Empty-state and error-state behavior — must follow Phase 20 D-03 (explicit visible error on load/bridge failure, never a silent blank or misleadingly-zero-looking chart) and Phase 21's carried-forward pattern; no new decision needed, just apply the existing standard.
- Whether new chart widgets are page-scoped (attached via a Page's `getHeaderWidgets()`/`getFooterWidgets()`) or panel-global — if page-scoped, remember the established `PAGE_SCOPED_WIDGETS` registration requirement (`AppServiceProvider`) that Phase 21 hit repeatedly, or the widget will throw `ComponentNotFoundException` on its first `wire:poll` tick.

### Deferred Ideas (OUT OF SCOPE)

- Click-to-drill-through interaction for the donut (VIZ-01) or any other new Phase 22 chart — explicitly declined for this phase (D-02).
- Batch/date-range filtering for the message-delivery funnel (VIZ-04) — explicitly declined for this phase (D-09); full-history view is the starting point.
- Global cross-survey normalized SCALE average — explicitly declined for this phase (D-10) in favor of per-survey/question scoping.
- Top-N truncation for the coordinator stacked-bar (VIZ-02) — explicitly declined (D-06).
</user_constraints>

## Phase Requirements

<phase_requirements>
| ID | Description | Research Support |
|----|-------------|------------------|
| VIZ-01 | Admin sees a donut chart of the 12 `VoterStatus` state distribution for the active campaign | `PieChart.jsx` already renders as a donut (`innerRadius=60`/`outerRadius=90`) — reuse directly via `kind: 'pie'`, no new component needed. Query pattern below (zero-filled `GROUP BY status`). |
| VIZ-02 | Admin sees a stacked-bar comparison of registered/validated/rejected apoyos per coordinator/team | New `stacked-bar` `ChartRouter` kind (Recharts `BarChart` + multiple `Bar` sharing `stackId`) + new 3-bucket pivot query using `TopCoordinatorsTable`'s team-resolution pattern. |
| VIZ-03 | Admin sees a funnel of call contactability by attempt number | New `funnel` `ChartRouter` kind (Recharts native `Funnel`/`FunnelChart`) + persistence-model query joining `VerificationCall`→`voters.campaign_id`. |
| VIZ-04 | Admin sees a funnel of message delivery for `MessageBatch`/`Message` | Same `funnel` kind, reused. Query via `Message`'s `sent_at`/`delivered_at`/`read_at`/`clicked_at` columns, campaign-scoped via `HasCampaignContext`. |
| VIZ-05 | Admin sees a gauge of average SCALE survey score + histogram of full distribution | New `gauge` kind (Recharts `Pie`-as-semicircle-arc) + new `histogram` kind (plain `BarChart`/`Bar`). Zero new aggregation — reads `SurveyMetricsCalculator`'s existing `SurveyMetrics.average_value`/`.distribution` columns. |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- Filament v4 conventions: static `make()` methods, all actions extend `Filament\Actions\Action`, layout components under `Filament\Schemas\Components`. New widgets in this phase are plain PHP `ChartWidget` subclasses — no schema/form components involved, but any header actions added must follow this.
- Explicit `use` statements always — never namespace aliases or inline `\App\...` paths. Every new PHP file (5 widgets + any query helper) must import every referenced class explicitly.
- Always curly braces, explicit return types + parameter type hints, PHPDoc over inline comments.
- Naming: `PascalCase` classes, `camelCase` methods/props, `snake_case` DB columns. All code identifiers in English (per user's global memory), all user-facing/Blade/chart-label text in Spanish — matches every existing widget's heading/description text (`'Cobertura y Ranking de Coordinadores'`, etc.).
- No `DB::` facade — prefer `Model::query()`; every existing widget in this codebase follows this (confirmed: `TerritorialDistributionChart` uses `DB::raw('COUNT(*) as total')` only inside a `select()`, never a bare `DB::table()` query — the new widgets should match this exact idiom, not introduce raw `DB::table()` calls).
- Thin controllers/widgets — but this codebase's existing precedent (`TerritorialDistributionChart`, `TopCoordinatorsTable`, `RejectionsCountersOverview`) puts aggregation queries directly in the widget's `getData()`/`table()` method, not in a separate Action/Service class, when the query is widget-specific and not reused elsewhere. Follow this precedent for VIZ-01–04 (no new Service class needed) unless a query is genuinely reused across 2+ widgets.
- Every change must have a test (project-wide `tests rules` in CLAUDE.md) — for chart widgets specifically, the established convention (Phase 20/21) is a Pest 4 Browser test per widget (rendered-content verification), in addition to standard Feature/Unit coverage of `getData()`'s data contract where useful.
- `vendor/bin/pint --dirty` before finalizing.
- GSD workflow enforcement: this phase's plans should be executed via `/gsd:execute-phase`, not direct file edits outside that flow.

## Standard Stack

No new packages. All required dependencies are already installed and verified working by Phases 20-21.

### Core (already installed — do not modify)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `react` / `react-dom` | `^19.2.8` | React runtime for the island | Already the island's runtime; Recharts 3.x peer-compatible |
| `recharts` | `^3.10.1` | Chart primitives — ships native `Funnel`/`FunnelChart`, `Pie`, `BarChart` used by all 4 new kinds | Confirmed installed via `package.json`; native `Funnel` component exists in this exact version (verified against milestone STACK.md/FEATURES.md, both HIGH confidence — official Recharts docs cross-checked 2026-08-20) |
| `motion` | `^13.1.1` | `ChartCard`'s existing entrance animation (`motion/react`) — new chart kinds render *inside* the existing `ChartCard`, so they inherit this for free, no per-component Motion code needed | Already wired into `ChartCard.jsx` |

**Installation:** None required — `npm install` is a no-op for this phase. Verify with:
```bash
npm list react recharts motion react-dom react-is
```

### Version verification

```bash
npm view recharts version   # confirm 3.10.1 or newer patch is what's on disk (package-lock.json), not registry latest
```
Do not bump any version this phase — Phase 21 already pinned and proved the exact versions in `package.json`/`package-lock.json`. Re-verifying registry `latest` is out of scope; the installed version is what matters.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Recharts native `Funnel`/`FunnelChart` | MonoCharts' horizontal-`BarChart`-as-funnel trick (`MonoRoundedFunnelChart.tsx`'s actual technique) | The bar-chart trick is simpler/lower-risk (reuses the already-built `BarChart.jsx` pattern verbatim) but produces a ranked-list visual, not a true narrowing trapezoid. FEATURES.md explicitly reserves this trick for the *non-monotonic* 12-state case (Phase 23's VIZ-06), not for VIZ-03/VIZ-04 which are genuinely monotonic per D-08/D-09. Recommendation: use native `Funnel` for VIZ-03/VIZ-04; if it proves visually inconsistent with the MonoCharts "rounded pill" chrome during implementation, the bar-chart-trick fallback is a same-day, low-risk pivot since the underlying query/data shape (`{labels, datasets}`) is identical either way. |
| Recharts `Pie`-as-semicircle for gauge | A literal `RadialBarChart` | `MonoRoundedGaugeArc.tsx` (confirmed via FEATURES.md's direct source read) is a 2-segment `Pie` with `startAngle=210`/`endAngle=-30`, not `RadialBarChart` — matching this exact technique keeps visual parity with the MonoCharts palette Phase 21 already committed to. `RadialBarChart` is a legitimate Recharts alternative but was not what the milestone's visual reference uses. |

## Architecture Patterns

### Recommended Project Structure (additions only — everything else already exists)

```
resources/js/charts/
├── ChartRouter.jsx                    # MODIFIED — register 4 new kinds
└── components/
    ├── StackedBarChart.jsx            # NEW — VIZ-02
    ├── FunnelChart.jsx                # NEW — VIZ-03, VIZ-04 (shared component, different data)
    ├── GaugeChart.jsx                 # NEW — VIZ-05 (gauge half)
    └── HistogramChart.jsx             # NEW — VIZ-05 (histogram half)

app/Filament/Widgets/
├── VoterStatusDonutChart.php          # NEW — VIZ-01, kind: 'pie' (reuses existing PieChart.jsx)
├── CoordinatorTeamStackedBarChart.php # NEW — VIZ-02, kind: 'stacked-bar'
├── CallContactabilityFunnelChart.php  # NEW — VIZ-03, kind: 'funnel'
├── MessageDeliveryFunnelChart.php     # NEW — VIZ-04, kind: 'funnel'
└── SurveyScaleGaugeHistogramChart.php # NEW — VIZ-05, kind: 'gauge' or 'histogram' (2 instances per question, or 1 widget with 2 sub-charts — see Open Questions)

tests/Browser/
├── VoterStatusDonutChartTest.php
├── CoordinatorTeamStackedBarChartTest.php
├── CallContactabilityFunnelChartTest.php
├── MessageDeliveryFunnelChartTest.php
└── SurveyScaleGaugeHistogramChartTest.php   # (or split into 2 files if gauge/histogram ship as 2 widgets)
```

No changes needed to: `vite.config.js`, `package.json`, `main.jsx`, `react-chart.blade.php`, any `*PanelProvider.php`'s Vite render hook (already registered on Admin per Phase 20/21).

### Pattern 1: Existing `{labels, datasets}` contract extends cleanly to `stacked-bar` and `histogram`

**What:** Every existing chart widget's `getData()` returns Chart.js-shaped `{labels: [...], datasets: [{label, data, ...}]}`. `chartjs-adapter.js` already ships `toSeriesRows({labels, datasets})` (confirmed present, currently unused by any component) which pivots that exact shape into the wide-row format Recharts' multi-`<Bar>` stacked pattern needs: `[{label: 'Coord A', validado: 12, rechazado: 3, registrado: 5}, ...]`.

**When to use:** VIZ-02 (stacked-bar) and VIZ-05's histogram (a plain `BarChart` with one dataset — `toNameValueRows` already handles the single-series case, no new adapter needed for histogram).

**Example — PHP side (VIZ-02):**
```php
// getData() returns:
return [
    'labels' => ['Juan Pérez', 'María Gómez', ...],       // one label per coordinator
    'datasets' => [
        ['label' => 'Validado', 'data' => [12, 8, ...]],
        ['label' => 'Rechazado', 'data' => [3, 1, ...]],
        ['label' => 'Registrado', 'data' => [5, 4, ...]],
    ],
];
```

**Example — JS side (`StackedBarChart.jsx`, new):**
```jsx
// Source: chartjs-adapter.js's existing toSeriesRows (unused today), Recharts stacked-bar docs
import { Bar, BarChart as RBarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toSeriesRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function StackedBarChart({ data, theme = 'light' }) {
    const rows = toSeriesRows(data ?? {});
    const seriesKeys = (data?.datasets ?? []).map((ds) => ds.label);
    return (
        <ResponsiveContainer width="100%" height={280}>
            <RBarChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} interval={0} angle={-20} textAnchor="end" height={60} />
                <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip content={<ChartTooltip theme={theme} />} cursor={{ fill: 'rgba(0,0,0,0.03)' }} />
                {seriesKeys.map((key, i) => (
                    <Bar key={key} dataKey={key} stackId="apoyos" fill={rankedMonochromeFill(i, seriesKeys.length, { isDark: theme === 'dark' })}
                        radius={i === seriesKeys.length - 1 ? [8, 8, 0, 0] : [0, 0, 0, 0]} />
                ))}
            </RBarChart>
        </ResponsiveContainer>
    );
}
```
Note: `toSeriesRows` keys each row by `ds.label ?? 'series_N'` — since D-03/D-04/D-05 always name datasets `'Validado'`/`'Rechazado'`/`'Registrado'` literally, `dataKey={key}` in the JSX above must match those exact label strings emitted by PHP. Keep the PHP dataset `label` values and the JS `seriesKeys` derivation in lock-step (both come from `data.datasets[].label`), which they naturally are since `seriesKeys` is derived from the same `data` prop.

### Pattern 2: Native Recharts `Funnel`/`FunnelChart` data shape

**What:** Recharts' real `Funnel` component (confirmed via milestone FEATURES.md, official docs cross-checked) takes `data={[{name, value, fill}]}` and renders genuine narrowing trapezoids, auto-sorted by the array order (not by value) — so the PHP query MUST emit stages in the correct funnel order (largest-to-smallest is the semantic expectation, and D-08/D-09's stage definitions naturally decrease).

**When to use:** VIZ-03, VIZ-04.

**Example — PHP side (VIZ-03, persistence model per D-08):**
```php
protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();
    if (! $activeCampaign) {
        return ['labels' => [], 'datasets' => [['label' => 'Contactabilidad', 'data' => []]], 'emptyReason' => 'no_campaign'];
    }

    $baseQuery = fn () => VerificationCall::query()
        ->join('voters', 'voters.id', '=', 'verification_calls.voter_id')
        ->where('voters.campaign_id', $activeCampaign->id);

    $intento1 = (clone $baseQuery())->distinct('verification_calls.voter_id')->count('verification_calls.voter_id');
    $intento2 = (clone $baseQuery())->where('attempt_number', '>=', 2)->distinct('verification_calls.voter_id')->count('verification_calls.voter_id');
    $intento3Plus = (clone $baseQuery())->where('attempt_number', '>=', 3)->distinct('verification_calls.voter_id')->count('verification_calls.voter_id');
    $contactado = (clone $baseQuery())->successful()->distinct('verification_calls.voter_id')->count('verification_calls.voter_id');

    return [
        'labels' => ['Intento 1', 'Intento 2', 'Intento 3+', 'Contactado'],
        'datasets' => [['label' => 'Contactabilidad', 'data' => [$intento1, $intento2, $intento3Plus, $contactado]]],
    ];
}
```
Note: `$baseQuery` as a closure avoids reusing a single mutated `Builder` instance across 4 counts (Eloquent builders mutate in place; `clone` is required, or rebuild the query 4 times). `->successful()` is `VerificationCall::scopeSuccessful()`, already defined. `attempt_number >= N` naturally captures "reached a Nth attempt" — this over-counts relative to a strict re-read of D-08's literal wording ("Intento 2 = voters who reached a 2nd attempt, not contacted on the 1st") if a voter was contacted on attempt 1 but still has a later attempt row — verify against real data whether a 2nd call is ever placed after a successful 1st contact (if `CallAssignmentService`/`CallCenterService`'s `attempt_number < 3` cap logic stops scheduling further attempts once contacted, this is moot; confirm during planning/implementation, flagged in Open Questions below).

**Example — JS side (`FunnelChart.jsx`, new):**
```jsx
// Source: Recharts official FunnelChart API (recharts.github.io/en-US/api/FunnelChart/), verified against recharts@3.10.1
import { Funnel, FunnelChart as RFunnelChart, LabelList, ResponsiveContainer, Tooltip } from 'recharts';
import { toNameValueRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function FunnelChart({ data, theme = 'light' }) {
    // NOTE: toNameValueRows() SORTS DESCENDING BY VALUE (see chartjs-adapter.js) —
    // for most funnels this coincides with the intended stage order since stages
    // are naturally decreasing, but it does NOT preserve the PHP-emitted label
    // order. If ties or any non-monotonic edge case occurs, this silently
    // reorders stages. A funnel-specific adapter that preserves array order
    // (no sort) is safer — see Open Questions.
    const rows = toNameValueRows(data ?? {});
    return (
        <ResponsiveContainer width="100%" height={280}>
            <RFunnelChart>
                <Tooltip content={<ChartTooltip theme={theme} />} />
                <Funnel dataKey="value" data={rows} isAnimationActive>
                    <LabelList position="right" dataKey="name" fill={theme === 'dark' ? '#fff' : '#111'} stroke="none" />
                    {rows.map((row, i) => (
                        <Cell key={row.name} fill={rankedMonochromeFill(i, rows.length, { isDark: theme === 'dark' })} />
                    ))}
                </Funnel>
            </RFunnelChart>
        </ResponsiveContainer>
    );
}
```
**Critical pitfall flagged here:** `toNameValueRows` (existing, shared with `BarChart`/`PieChart`) sorts rows descending by `value` before returning — this is exactly right for a ranked bar/donut, but for a `Funnel` the stage *order* is semantic (Intento 1 → 2 → 3+ → Contactado must render top-to-bottom in that literal sequence, not resorted by count). Since D-08/D-09's stages are already naturally decreasing in count in the overwhelmingly common case, a value-sort usually produces the same order as the intended sequence — but this is a coincidence of the data, not a guarantee, and should not be relied on. **Recommend a dedicated `toOrderedRows()` adapter function (no sort) for the funnel kind specifically**, rather than reusing `toNameValueRows` — flagged as a concrete implementation task, not a research gap.

### Pattern 3: Gauge as 2-segment semi-circle `Pie` (MonoCharts' actual technique)

**What:** `MonoRoundedGaugeArc.tsx` (confirmed via milestone FEATURES.md's direct source read) is a `Pie` with exactly 2 data segments — `[{value: filledAmount}, {value: 100 - filledAmount}]` — and `startAngle={210}` / `endAngle={-30}` to draw only the bottom semi-circle, with the 2nd segment's fill set to a low-opacity/track color so only the 1st segment reads as "progress."

**When to use:** VIZ-05's gauge half.

**Example — PHP side:**
```php
// $metrics = SurveyMetrics::where('survey_question_id', $questionId)->where('metric_type', 'question_average')->latest('calculated_at')->first();
// $config = $question->configuration ?? []; $min = $config['min'] ?? 1; $max = $config['max'] ?? 5;
return [
    'value' => (float) ($metrics->average_value ?? 0),
    'min' => $min,
    'max' => $max,
    'labels' => [],
    'datasets' => [],   // ChartCard's isChartDataEmpty() check needs adjusting for this shape — see Open Questions
];
```

**Example — JS side (`GaugeChart.jsx`, new):**
```jsx
// Source: MonoRoundedGaugeArc.tsx technique (per milestone FEATURES.md direct source read), adapted to this project's data contract
import { Cell, Pie, PieChart as RPieChart, ResponsiveContainer } from 'recharts';
import { formatNumber } from '../lib/formatters.js';

export default function GaugeChart({ data, theme = 'light' }) {
    const { value = 0, min = 0, max = 5 } = data ?? {};
    const pct = max > min ? Math.max(0, Math.min(1, (value - min) / (max - min))) : 0;
    const rows = [{ name: 'filled', value: pct * 100 }, { name: 'track', value: 100 - pct * 100 }];
    const trackColor = theme === 'dark' ? 'rgba(255,255,255,0.12)' : 'rgba(9,9,11,0.08)';
    return (
        <div className="relative" style={{ width: '100%', height: 180 }}>
            <ResponsiveContainer>
                <RPieChart>
                    <Pie data={rows} dataKey="value" startAngle={210} endAngle={-30} innerRadius={70} outerRadius={95} cornerRadius={8}>
                        <Cell fill="#f97316" />
                        <Cell fill={trackColor} />
                    </Pie>
                </RPieChart>
            </ResponsiveContainer>
            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-end pb-2 text-center">
                <span className="text-2xl font-semibold tabular-nums">{formatNumber(value)}</span>
                <span className="text-xs opacity-60">{min}–{max}</span>
            </div>
        </div>
    );
}
```

### Pattern 4: Histogram is a plain `BarChart` over `SurveyMetrics.distribution`'s bucketed shape

**What:** `SurveyMetricsCalculator::calculateScaleDistribution()` (confirmed via direct source read) already produces exactly the bucketed shape a histogram needs, but the JSON shape is **not** a flat `{value: count}` map — it is `{value: {count: N, percentage: P}}`, one key per integer in `[min, max]`, zero-filled for empty buckets (confirmed: loop pre-seeds `$distribution[$i] = 0` for every `$i` from `min` to `max` before counting real responses).

**When to use:** VIZ-05's histogram half.

**Example — PHP side:**
```php
// $metrics->distribution === ['1' => ['count' => 1, 'percentage' => 10.0], '2' => ['count' => 2, ...], ...]
$distribution = $metrics->distribution ?? [];
return [
    'labels' => array_keys($distribution),
    'datasets' => [['label' => 'Respuestas', 'data' => array_map(fn ($bucket) => $bucket['count'] ?? 0, $distribution)]],
];
```
This is then a `kind: 'bar'`-compatible `{labels, datasets}` payload — **no new JS component is strictly required for the histogram**, since `BarChart.jsx` (existing, `kind: 'bar'`) already renders exactly this shape via `toNameValueRows`. Confirmed by FEATURES.md: "No MonoCharts histogram file exists by that name, but it's structurally identical to `MonoRoundedStackedBarChart`/plain bar variant." **Recommendation: do not register a distinct `histogram` `ChartRouter` kind at all — reuse `kind: 'bar'` for VIZ-05's histogram half**, saving one component and one `ChartRouter` entry. If a visually distinct treatment (e.g. contiguous bars with no gap, to read as a true histogram rather than a categorical bar chart) is wanted, that is a `BarChart.jsx` prop/variant, not a new kind — flag as an implementation-detail choice, not a research blocker.

### Anti-Patterns to Avoid

- **Reordering funnel stages via `toNameValueRows`'s implicit sort:** see Pattern 2 above — build a dedicated non-sorting adapter for `funnel` kind data.
- **Building a new Service/Action class for each new query:** this codebase's own precedent (`TerritorialDistributionChart`, `TopCoordinatorsTable`, `RejectionsCountersOverview`) keeps widget-specific aggregation directly in `getData()`/`table()`. Do not introduce a `VoterStatusAggregationService` etc. unless a query is genuinely shared by 2+ widgets (none of the 4 new queries are).
- **Re-deriving the "rechazado" bucket instead of reusing `RejectionsCountersOverview`'s exact status list:** D-04 is explicit that the 4-status list must be copied verbatim, not redefined — a second, subtly different definition of "rejected" in two widgets would violate the project's "trustworthy, campaign-safe data" core value.
- **Counting `VerificationCall` rows instead of distinct voters for the funnel stages:** D-08 defines each stage as a *voter count* ("voters with at least 1 call attempt"), not a call-row count — a voter with 3 call attempts must count once per stage they reached, not 3 times. Use `distinct('voter_id')->count('voter_id')`, not a plain `count()`.
- **Reading `MessageBatch`'s pre-aggregated counters for the read/click stages:** D-09 is explicit — `MessageBatch` only pre-aggregates `sent_count`/`delivered_count`/`failed_count`; `read_at`/`clicked_at` must be counted from `Message` rows directly, there is no batch-level `read_count`/`clicked_count` column to shortcut with.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|--------------|-----|
| Trapezoid/funnel layout math | A custom SVG funnel shape | Recharts' native `Funnel`/`FunnelChart` component | Ships a real, tested layout algorithm; this codebase has zero precedent for hand-rolled SVG chart geometry and Phase 21 established "use Recharts' native primitive when one exists" as the working convention |
| Coordinator→team resolution | A fresh `whereHas`/subquery reimplementation of "coordinator + their líderes" | `$record->leaders()->pluck('id')->push($record->id)` — the exact expression already proven in `TopCoordinatorsTable.php` and required verbatim by D-07 | Any deviation risks a subtly different team boundary than every other coordinator-scoped report in this codebase, breaking the project's cross-report consistency |
| "Rechazado" status bucket | A new list of rejection-indicating `VoterStatus` values | The exact 4-status list already defined in `RejectionsCountersOverview.php` lines 44-48, required verbatim by D-04 | Same rationale — one canonical definition of "rejected" across the whole app |
| SCALE average/distribution math | Re-implementing average/bucket calculation inside the new widget | `SurveyMetricsCalculator::calculateScaleAverage()`/`calculateScaleDistribution()`, already computed and persisted to `SurveyMetrics` | D-10 explicitly requires zero new aggregation logic; the calculator already handles per-question configurable min/max correctly |
| Successful-call determination | A new `in_array($result, [...])` check | `CallResult::isSuccessfulContact()` / `VerificationCall::scopeSuccessful()` | Both already exist and are the canonical "contactado" definition per D-08 |

**Key insight:** Every single aggregation this phase needs already has either a direct precedent to copy the *shape* from, or an exact definition to copy *verbatim* from an existing widget. There is no genuinely novel business-logic question left open in this phase — the only genuinely new engineering surface is the 3 new Recharts chart-kind components (funnel, stacked-bar, gauge) themselves, which have zero precedent in this codebase (Phase 21 only proved `line`/`bar`/`pie`/`sparkline`).

## Common Pitfalls

### Pitfall 1: `toNameValueRows`'s implicit descending sort silently reorders funnel stages
**What goes wrong:** A funnel rendered with stages out of the intended sequence (e.g. "Contactado" rendered above "Intento 1" if counts happen to tie or an edge case inverts the natural ordering).
**Why it happens:** `toNameValueRows` (shared adapter, used by `BarChart`/`PieChart` today) sorts `.sort((a, b) => b.value - a.value)` — correct for ranking, wrong for a funnel where array order IS the semantic stage order.
**How to avoid:** Build a dedicated non-sorting row adapter (e.g. `toOrderedRows`) for the `funnel` kind; do not reuse `toNameValueRows` for `FunnelChart.jsx`.
**Warning signs:** A funnel where a later, smaller stage visually renders wider/above an earlier, larger stage.

### Pitfall 2: `attempt_number >= N` may over-count vs. D-08's literal "reached a 2nd attempt after not being contacted on the 1st" wording
**What goes wrong:** If a voter is contacted successfully on attempt 1 but a 2nd call row still exists (e.g. a scheduling race, or a survey-only follow-up call logged after contact), the naive `attempt_number >= 2` query counts them into "Intento 2" even though D-08's prose implies "Intento 2" should exclude voters already contacted on attempt 1.
**Why it happens:** The `attempt_number` column is a simple integer counter on each `VerificationCall` row, not a computed "did this voter need this stage" flag — `CallAssignmentService`/`CallCenterService`'s `attempt_number < 3` cap logic (confirmed present) governs *scheduling new attempts*, not guaranteed to prevent a completed voter from having a stray later-attempt row.
**How to avoid:** During implementation, verify empirically (e.g. via `tinker` against real/seeded data) whether any voter has both a successful `attempt_number = 1` call and a later `attempt_number >= 2` row. If the answer is "never happens in practice" (likely, if the calling service stops scheduling once contacted), the simple `>= N` query is correct as written and this is a non-issue. If it does happen, D-08's persistence-model semantics still hold reasonably (the funnel measures "how far did outreach efforts go," not "how many attempts were needed for non-contacted voters") — flag this as a planning-time confirmation, not a blocker.
**Warning signs:** "Contactado" count exceeding "Intento 1" count (would indicate a query bug elsewhere) — not expected from this pitfall specifically, but a useful sanity check regardless.

### Pitfall 3: `ChartCard.jsx`'s `isChartDataEmpty()` doesn't know about the gauge's `{value, min, max}` shape
**What goes wrong:** `isChartDataEmpty(kind, data)` (existing, in `chartjs-adapter.js`) special-cases only `sparkline`/`poc` (checks `data.points`) and otherwise falls through to checking `data.labels`/`data.datasets` — a gauge payload shaped as `{value, min, max}` with empty `labels`/`datasets` arrays (as sketched in Pattern 3 above) will be misclassified as "empty" even when it has a real value, because the fallback branch treats `labels.length === 0` as empty.
**Why it happens:** The gauge is the first chart kind in this codebase whose "has data" signal isn't `labels`/`datasets`-shaped.
**How to avoid:** Either (a) extend `isChartDataEmpty()` with a `kind === 'gauge'` branch checking `typeof data?.value === 'number'` instead, mirroring the existing `sparkline` special-case, or (b) keep gauge data always shaped as `{labels: ['value'], datasets: [{label: 'Promedio', data: [value]}], min, max}` so the existing `labels`/`datasets` empty-check "just works" without modification, and have `GaugeChart.jsx` read `data.datasets[0].data[0]` instead of `data.value`. **(b) is lower-risk** — it requires zero change to the shared `isChartDataEmpty()` function, reducing blast radius on every other chart kind. Flagging as an implementation decision, not fully resolved here.
**Warning signs:** Gauge widget always rendering the "Sin datos" empty state even when a real `average_value` exists.

### Pitfall 4: Forgetting `PAGE_SCOPED_WIDGETS` registration for any page-scoped new widget
**What goes wrong:** `ComponentNotFoundException` on the widget's first `wire:poll` tick — first render succeeds (mounted with the FQCN directly), but the poll follow-up request fails, because Livewire's alias↔class resolution only auto-registers `App\Livewire`-namespaced classes.
**Why it happens:** Documented and hit repeatedly in Phase 21 (`SurveyResultsWidget`, `CallCenterStatsWidget`, `CallCenterCallsSparklineWidget` all needed this fix) — any `App\Filament\Widgets\*` class attached via a Page's `getHeaderWidgets()`/`getFooterWidgets()` (not a panel's global `->widgets([...])` array) needs explicit registration.
**How to avoid:** Every widget in this phase attached to `EditSurvey::getFooterWidgets()` (VIZ-05), `ListVerificationCalls::getHeaderWidgets()` or similar (VIZ-03), or `ListMessageBatches::getHeaderWidgets()`/`ViewMessageBatch::getFooterWidgets()` (VIZ-04) MUST be added to `AppServiceProvider::PAGE_SCOPED_WIDGETS` (currently a 9-item array at `app/Providers/AppServiceProvider.php:42-52`). VIZ-01/VIZ-02, if placed on the Admin dashboard's panel-global `->widgets([...])` array (per Claude's Discretion, following `TerritorialDistributionChart`/`TopCoordinatorsTable` precedent), do NOT need this — only page-scoped widgets do.
**Warning signs:** Widget renders correctly on first page load, then errors (or the chart silently freezes) after the first poll interval elapses.

### Pitfall 5: `TopCoordinatorsTable`'s `apoyos_equipo_count` excludes `DUPLICATE` — VIZ-02's "registrado" residual bucket must not
**What goes wrong:** A stacked-bar total per coordinator that doesn't match the coordinator's true total apoyo count, if the new query silently copies `TopCoordinatorsTable`'s `->where('voters.status', '!=', VoterStatus::DUPLICATE->value)` filter.
**Why it happens:** `TopCoordinatorsTable::table()` (confirmed via direct read) explicitly excludes `DUPLICATE` from its `apoyos_equipo_count` — a reasonable choice for that specific ranking metric, but D-05 explicitly requires `DUPLICATE` to be **included** in VIZ-02's "registrado" residual bucket ("The 3 stacked segments always sum to the coordinator's exact total apoyo count — no voter is silently dropped from the chart").
**How to avoid:** Copy `TopCoordinatorsTable`'s *team-resolution pattern* (`leaders()->pluck('id')->push($record->id)`), not its status-filtering logic. The new query must count every voter belonging to the team, with zero status exclusion at the base query level — only the D-03/D-04 bucket definitions determine which of the 3 stacked segments a voter falls into.
**Warning signs:** Stacked-bar segment totals per coordinator not matching that coordinator's actual total voter count when spot-checked against `VoterResource`'s filtered list.

## Code Examples

See Architecture Patterns section above (Patterns 1-4) — each includes a verified PHP `getData()` sketch and a Recharts component sketch grounded in this repo's actual existing files (`BarChart.jsx`, `PieChart.jsx`, `chartjs-adapter.js`, `palette.js`, `ChartTooltip.jsx`) plus the milestone's already-verified MonoCharts/Recharts source reads (FEATURES.md).

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| Chart.js via Filament's default `ChartWidget` view | React/Recharts via `resources/views/filament/widgets/react-chart.blade.php` | Phase 20-21 (this milestone) | Every new widget in Phase 22 must use the React pipeline from day one — there is no "old way" fallback left to accidentally reach for; `ChartWidget::getOptions()` should not be implemented on new widgets (Phase 21 precedent: deleted, not left unused) |
| `MessageBatch`'s stored `sent_count`/`delivered_count`/`failed_count` counters | Direct `Message` row timestamp counts for anything beyond sent/delivered | N/A — this has always been the case, `MessageBatch` never tracked read/click | VIZ-04 is the first feature to expose this "invisible" data — confirms FEATURES.md's flag that message-delivery funnel data is "currently 0% visible" |

**Deprecated/outdated:** None specific to this phase — the whole chart pipeline is net-new as of Phase 20-21, nothing to deprecate within Phase 22's scope.

## Open Questions

1. **Should VIZ-05's gauge and histogram be one widget instance (rendering 2 sub-charts) or two separate `ChartWidget` subclasses per SCALE question?**
   - What we know: `SurveyResultsWidget` (existing precedent) is one `ChartWidget` per question, each rendering exactly one Recharts chart via a single `getChartKind()` value. `ChartRouter`/`ChartCard`/the Blade view are all built around "one widget = one chart kind."
   - What's unclear: D-10/D-11 describe "a gauge... alongside a histogram" for the same question — this reads as 2 visually distinct charts, which the current 1-widget-1-chart-kind architecture doesn't directly support without either (a) two separate widget instances per SCALE question (e.g. `SurveyScaleGaugeChart` + `SurveyScaleHistogramChart`, both added to `EditSurvey::getFooterWidgets()`), or (b) extending `ChartRouter`/`ChartCard` to support a composite "2 charts in 1 card" kind.
   - Recommendation: (a) — two separate widget classes, each following the exact existing 1-chart-per-widget pattern, both keyed off the same `questionId`. This requires zero architecture change and matches how `EditSurvey::getFooterWidgets()` already maps 1 `SurveyResultsWidget` per question — extending to 2 widgets per SCALE question (gauge + histogram) alongside the 1 widget per non-SCALE question (unchanged `SurveyResultsWidget`) is a minimal, low-risk extension of an already-proven pattern. Flag as a planning decision, not a blocker.

2. **Exact placement (page vs. panel-global) for VIZ-03/VIZ-04 funnels.**
   - What we know: CONTEXT.md's discretion note recommends `VerificationCallResource`/Call Center pages for VIZ-03 and `MessageBatchResource`/Messaging pages for VIZ-04, "mirroring how `SurveyResultsWidget` attaches to Survey pages." `ListVerificationCalls::getHeaderWidgets()` already hosts `CallCenterStatsWidget`/`CallCenterCallsSparklineWidget` (a proven attachment point for campaign-wide, non-per-record call-center widgets). `MessageBatchResource` has both `ListMessageBatches` (index) and `ViewMessageBatch` (single-record) pages.
   - What's unclear: Whether VIZ-04's funnel (explicitly "all `MessageBatch`/`Message` records historically, campaign-scoped, no batch filter" per D-09) belongs on `ListMessageBatches` (campaign-wide index, matching its campaign-wide scope) or `ViewMessageBatch` (a single-batch detail page, which would be semantically wrong for a widget that deliberately ignores per-batch scoping).
   - Recommendation: `ListMessageBatches::getHeaderWidgets()` for VIZ-04 (matches its campaign-wide, not-batch-scoped data), `ListVerificationCalls::getHeaderWidgets()` for VIZ-03 (directly mirrors the proven `CallCenterStatsWidget` placement). Both need `PAGE_SCOPED_WIDGETS` registration (Pitfall 4). Confirm during planning — low-risk either way since both are `getHeaderWidgets()`-shaped attachments with an existing precedent to copy verbatim.

3. **`FunnelChart.jsx`'s exact `Cell`/`fill` per-segment styling against the MonoCharts "rounded pill" chrome.**
   - What we know: Recharts' native `Funnel` supports per-segment `Cell` coloring (same technique already used by `BarChart.jsx`/`PieChart.jsx` via `rankedMonochromeFill`), and `LabelList` for stage labels.
   - What's unclear: Whether Recharts' native `Funnel` shape (rounded rectangles narrowing between stages) visually matches "MonoCharts' actual visual composition" closely enough per Phase 21's D-02/D-03 palette/chrome commitment, since MonoCharts itself never demos its own `Funnel` usage (confirmed by FEATURES.md — MonoCharts' own "funnel" is the bar-chart trick, not Recharts' native component). There is no existing MonoCharts source to pixel-match against for this one component.
   - Recommendation: Build against Recharts' native `Funnel` API directly (Pattern 2 above), apply the same `rankedMonochromeFill`/`ChartTooltip`/`cornerRadius`-style treatment already used elsewhere for visual consistency, and treat exact pixel fidelity to a "MonoCharts pill funnel" as best-effort rather than a hard requirement — no MonoCharts source exists to definitively match against for this specific component.

## Environment Availability

No external tooling/service dependencies beyond what Phases 20-21 already verified installed (Node/npm, `recharts`, `motion`, `react`/`react-dom`, Playwright/Pest 4 Browser). This phase is pure application code (PHP widgets + JS components) — no new environment probe needed.

## Sources

### Primary (HIGH confidence)
- Direct reads of this repository's own source, 2026-08-21: `resources/js/charts/ChartRouter.jsx`, `components/BarChart.jsx`, `components/PieChart.jsx`, `components/ChartCard.jsx`, `components/ChartTooltip.jsx`, `lib/chartjs-adapter.js`, `lib/palette.js`, `lib/formatters.js`, `main.jsx`; `app/Filament/Widgets/TerritorialDistributionChart.php`, `TopCoordinatorsTable.php`, `RejectionsCountersOverview.php`, `SurveyResultsWidget.php`; `app/Enums/VoterStatus.php`, `CallResult.php`, `QuestionType.php`; `app/Models/VerificationCall.php`, `Message.php`, `MessageBatch.php`, `SurveyMetrics.php`, `SurveyQuestion.php`; `app/Models/Concerns/HasCampaignContext.php`; `app/Services/SurveyMetricsCalculator.php`, `CampaignContext.php`; `app/Services/CallAssignmentService.php`, `CallCenterService.php` (`attempt_number < 3` confirmation); `app/Filament/Resources/Surveys/Pages/EditSurvey.php`, `VerificationCalls/Pages/ListVerificationCalls.php`, `VerificationCalls/VerificationCallResource.php`, `Messages/MessageBatchResource.php`; `app/Providers/AppServiceProvider.php` (`PAGE_SCOPED_WIDGETS`); `app/Providers/Filament/AdminPanelProvider.php` (widgets array + Vite render hook); `resources/views/filament/widgets/react-chart.blade.php`; `tests/Browser/SurveyResultsWidgetTest.php`; `package.json`.
- `.planning/research/FEATURES.md`, `ARCHITECTURE.md`, `PITFALLS.md`, `STACK.md` (milestone-level, 2026-08-20) — MonoCharts source reads (GitHub API + raw source), Recharts official docs cross-check, npm registry version verification. All HIGH confidence per those documents' own sourcing.
- `.planning/phases/22-table-stakes-new-visualizations/22-CONTEXT.md` — user decisions (D-01 through D-11), locked verbatim into this document's User Constraints section.

### Secondary (MEDIUM confidence)
- None — no WebSearch was needed for this research; all questions were answerable from the milestone's already-completed research plus direct codebase reads.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages, all versions already verified installed by Phase 21's own `package.json`
- Architecture: HIGH — `{labels, datasets}` contract, `ChartRouter` kind-registration pattern, and `PAGE_SCOPED_WIDGETS` requirement are all directly observed in this repo's existing code, not inferred
- Pitfalls: HIGH for the 5 listed (all traced to specific, named source lines in this repo or the milestone's own PITFALLS.md); MEDIUM for the exact real-world frequency of Pitfall 2's edge case (flagged as an implementation-time empirical check, not resolvable from static code reading alone)

**Research date:** 2026-08-21
**Valid until:** No forced expiry — this phase's research is grounded in this repo's own stable, already-merged code (Phases 20-21), not in external library churn. Re-verify only if Phase 21's chart pipeline changes before Phase 22 executes.

---
*Phase: 22-table-stakes-new-visualizations*
*Researched: 2026-08-21*
