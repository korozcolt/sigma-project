# Phase 21: Migrate Existing Charts to React/Recharts - Research

**Researched:** 2026-08-20
**Domain:** Recharts component implementation over an existing, proven Livewire↔React bridge (Filament 4 / Recharts 3.10 / Motion)
**Confidence:** HIGH — the bridge mechanism is already built and browser-verified (Phase 20); this phase's open questions are chart-rendering/data-shape questions, grounded directly in this repo's own widget source and MonoCharts' real upstream source (fetched live).

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01 — Sparkline migration strategy:** The 3 embedded sparklines are migrated as **dedicated small `ChartWidget`-based widgets** placed next to their parent `StatsOverviewWidget` in the panel's `->widgets([...])` array — not as a custom `Stat`-shaped Blade partial embedded inside the Stat cell itself. The parent `StatsOverviewWidget`'s numeric `Stat::make()` cells stay exactly as they are today (including their existing `->chart()` Chart.js sparkline as a fallback until the new widget replaces it visually, or removed once replaced — Claude's discretion on the exact swap mechanics).

**D-02 — Visual fidelity level:** All 6 migrated widgets (3 big charts + 3 sparklines) adopt **full MonoCharts visual composition now** — nested card shell, rounded/monochrome bars, header/footer chrome, staggered entrance animation via Motion — not a minimal "swap the rendering engine only" re-skin.

**D-03 — Color palette:** These 6 widgets adopt the **MonoCharts monochrome/rounded palette**, replacing the current ad hoc hardcoded hex colors (e.g. `#3b82f6` blue / `#10b981` green on `ValidationProgressChart`, the 10-color rotation on `TerritorialDistributionChart`).

**D-04 — SurveyResultsWidget's dynamic chart type:** `SurveyResultsWidget`'s runtime chart-type switching (pie for `YES_NO` questions, bar for `SCALE`/`SINGLE_CHOICE`/`MULTIPLE_CHOICE`, decided by `getType()`/`getChartKind()` reading `question_type`) is preserved unchanged. The new `ChartRouter` must support a widget instance whose `chartKind` varies per-render rather than being fixed per widget class — this is a plumbing requirement, not a product/UX change.

### Claude's Discretion

- Exact naming/placement of the new small sparkline `ChartWidget` subclasses (e.g. `CampaignVotersSparklineWidget`) and whether/how the old `Stat::chart()` Chart.js sparkline is removed from the parent `StatsOverviewWidget` vs. left temporarily dormant during the swap.
- `ChartRouter.tsx` internal component structure (per-kind file layout under `resources/js/charts/`) mapping `chartKind` → `MonoRounded*`-style Recharts component, as long as it supports `line`, `bar` (including stacked/grouped variants used by `TerritorialDistributionChart`), and `pie` kinds plus `SurveyResultsWidget`'s dynamic kind requirement (D-04).
- Exact MonoCharts color tokens/CSS variables adopted for the new palette (D-03) — pull directly from MonoCharts' actual source per FEATURES.md, not invented independently.
- Whether legend/tooltip/interaction-mode parity (e.g. `ValidationProgressChart`'s `interaction.mode = 'index'`) is replicated via Recharts' own `Tooltip`/`Legend` props or a MonoCharts-style custom tooltip component.
- Which panel(s) register the new small sparkline widgets, mirroring wherever their parent `StatsOverviewWidget` is already registered today.

### Deferred Ideas (OUT OF SCOPE)

- Any new chart type, new data source, or new business insight — explicitly out of scope for this phase (belongs to Phase 22+, this phase is migration-only per MIGR-01/MIGR-02's "existing" framing).
- Simplifying/changing `SurveyResultsWidget`'s dynamic pie/bar switching behavior — flagged during discussion but explicitly kept as-is (D-04).

**Correction to the phase brief (see Critical Finding 1 below):** the phase's "as long as it supports... including stacked/grouped variants used by `TerritorialDistributionChart`" framing does not match this widget's actual code — `TerritorialDistributionChart` is a single-series ranked bar chart with per-bar colors, not a stacked/grouped multi-series bar. No widget in this phase's scope needs Recharts' `stackId` grouping. See Critical Finding 1 for the corrected chart-kind inventory.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| MIGR-01 | `ValidationProgressChart`, `TerritorialDistributionChart`, and `SurveyResultsWidget` render through the new React/Recharts pipeline instead of Chart.js, with their existing campaign/role-scoped `getData()` queries unchanged | Critical Finding 1 (exact `chartKind` inventory + Chart.js→Recharts data adapters); Critical Finding 2 (`SurveyResultsWidget` is currently unregistered on any panel — must be wired to a real page for MIGR-01 to be observable/testable); Architecture Patterns section (repointed `$view` + `getChartKind()` proxy pattern, already proven by `ReactIslandPocWidget`) |
| MIGR-02 | The 3 embedded sparklines (`CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`) render through the new pipeline | Critical Finding 3 (5 actual `Stat::chart()` call sites across 3 parent widgets vs. "3 sparklines" in the requirement text — recommends 3 new dedicated widgets, one per parent, and which chart to pick); Critical Finding 4 (`SurveyStatsOverview`'s only `chart()` call is presently unreachable on any registered panel since `$surveyId` is never set); Don't Hand-Roll section (shared query extraction to avoid duplicating campaign-scoping logic) |
</phase_requirements>

## Summary

Phase 20 already proved the hard part: the `wire:poll` → `dispatch('updateChartData')` → Alpine bridge → `root.render()` cycle is built, browser-verified, and has a working reference implementation (`ReactIslandPocWidget` + `react-chart.blade.php` + `resources/js/charts/main.jsx`). This phase's job is narrower and more concrete than the phase brief's own framing suggests once the actual widget source is read: it needs exactly **3 Recharts chart-kind components** (`line`, `bar`, `pie`) plus one new **`sparkline`** kind — not a `stacked`/`grouped` bar variant, which nothing in this phase's scope actually needs (that requirement belongs to Phase 22's `VIZ-02` stacked-bar coordinator comparison). The most consequential, previously-undocumented finding is that **all 3 existing widgets return Chart.js-shaped payloads (`{labels, datasets}`), and `getData()`'s body must stay unchanged per MIGR-01** — so `ChartRouter` needs a small, shared adapter layer that reshapes `{labels, datasets}` into the row-object arrays Recharts expects, rather than each widget being rewritten to emit Recharts-native shapes directly.

Two further findings materially change scope/risk for this phase: `SurveyResultsWidget` is **not registered on any panel today** (confirmed via full-codebase grep — no `PanelProvider`, no resource page references it), so "migrate the existing widget" cannot be observed or browser-tested until the plan also decides where it gets mounted; and `SurveyStatsOverview`'s only `->chart()` call lives inside a `$surveyId`-conditional branch that is never reached by current panel registrations (no page passes `surveyId`), meaning its "existing sparkline" is presently dead code on every dashboard that renders it. Both require an explicit planning decision, not just a re-skin.

**Primary recommendation:** Build a shared `resources/js/charts/lib/chartjs-adapter.js` (two small pure functions: rows-per-label for multi-series `line`, name/value pairs for single-series `bar`/`pie`), a `ChartRouter.jsx` that dispatches on `kind` to 4 components (`LineChart`, `BarChart`, `PieChart`, `SparklineChart`) built directly against MonoCharts' real source (fetched and verified below), generalize `react-chart.blade.php` to read `$this->getHeading()`/`$this->getDescription()` instead of the PoC's hardcoded string, and resolve the `SurveyResultsWidget`/`SurveyStatsOverview` registration gaps as an explicit Task before writing their Browser tests.

## Critical Finding 1: The actual chart-kind inventory is `line` + `bar` (ranked, single-series) + `pie` + new `sparkline` — not `stacked`/`grouped` bar

Reading all 3 widgets' real `getData()`/`getType()` bodies (verified 2026-08-20, this repo):

| Widget | `getType()` today | Actual Chart.js dataset shape | Recharts equivalent needed |
|---|---|---|---|
| `ValidationProgressChart` | `'line'` | 2 datasets (`Total Apoyos`, `Validados`), each `data: number[]`, shared `labels: string[]` (30 days) | **`line`**, multi-series: one `<Line>` per dataset, `XAxis dataKey="label"` |
| `TerritorialDistributionChart` | `'bar'` | **1 dataset** (`Apoyos`), `data: number[]` (10 values), `backgroundColor: string[]` (10 distinct hex colors, one per bar) | **`bar`**, single-series ranked bar with **per-`Cell` fill** (like a bar-shaped donut, not a `stackId` group) |
| `SurveyResultsWidget` (question view, `YES_NO`) | `'pie'` | 1 dataset, `data: number[]` (2 values, Sí/No), `backgroundColor: string[]` | **`pie`** |
| `SurveyResultsWidget` (question view, `SCALE`/`SINGLE_CHOICE`/`MULTIPLE_CHOICE`) | `'bar'` | 1 dataset, `data: number[]` (2-8 values from `distribution` map), `backgroundColor: string[]` (rotates through 8 rgba colors) | **`bar`**, same single-series ranked shape as `TerritorialDistributionChart` — reuse the same component |

No widget in this phase's scope uses Chart.js `stackId`-equivalent multi-series stacking. The phase brief's framing ("bar's stacked-vs-grouped variant needed by `TerritorialDistributionChart`") does not match the code — `TerritorialDistributionChart` is a single `dataset` with a `backgroundColor` array, which Chart.js renders as one differently-colored bar per index, not multiple series. Building a real Recharts `stackId`-based stacked/grouped bar component is unnecessary for MIGR-01/MIGR-02; it belongs to Phase 22 (`VIZ-02`, per `ROADMAP.md`/`REQUIREMENTS.md`, a genuinely different multi-coordinator-per-category shape). **Recommendation: don't build a stacked-bar Recharts component in this phase** — build one `bar` component that takes `name`/`value` rows and renders each bar with its own `<Cell>` fill (rank-based monochrome per D-03), shared by `TerritorialDistributionChart` and `SurveyResultsWidget`'s bar branch.

`getChartKind()` values needed this phase: `'line'`, `'bar'`, `'pie'`, `'sparkline'` (new, for MIGR-02). This matches `ReactIslandPocWidget`'s already-established `getChartKind(): string` → `getType()` proxy pattern (`ARCHITECTURE.md` line 301's recommendation, already implemented in Phase 20's PoC — reuse verbatim for all 6 widgets in this phase).

**Confidence: HIGH** — read directly from `app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php`, `SurveyResultsWidget.php` in this repo.

## Critical Finding 2: `SurveyResultsWidget` is not registered on any panel today — MIGR-01 cannot be observed without a wiring decision

Full-codebase grep (`app/`, `resources/`, `tests/`) for `SurveyResultsWidget` returns exactly one hit: its own class definition. It is:
- Not in any of the 5 `*PanelProvider.php` `->widgets([...])` arrays.
- Not referenced by any Filament Resource page (`SurveyResource`'s `EditSurvey.php` has no `getHeaderWidgets()`/`getFooterWidgets()`; there is no `ViewSurvey` page).
- Not covered by any existing test (`tests/` grep returns zero hits).

Its `canView(): true` override and its `public ?int $surveyId` / `public ?int $questionId` properties (clearly designed to be passed in via `->widgets([SurveyResultsWidget::class => ['surveyId' => ...]])`-style mounting on a page that has a specific survey/question in context) suggest it was built for a future integration point that was never wired up. Since `getData()`'s campaign/role scoping is not the issue here (this widget has no campaign scoping at all — it queries `SurveyMetrics`/`Survey` directly), migrating its *rendering* without also deciding *where it's mounted* leaves Success Criterion #3 ("each migrated widget has a Pest 4 Browser test verifying real rendered chart content") impossible to satisfy via `visit()`-based Browser testing, since Pest 4 Browser tests need a real routed page to visit.

**This needs an explicit planning decision, not a default assumption.** Two viable paths, in order of recommendation:
1. **Register it on `SurveyResource`'s `EditSurvey` page** as a `getFooterWidgets()` entry, passed the current record's ID as `surveyId` (Filament supports passing widget data via `getWidgetData()` on `HasWidgets`-trait pages) — this is the most product-correct fix (a survey's edit page showing its own results chart is an obviously intended feature) and gives the Browser test a real page to `visit()`.
2. **Mount it directly in the Browser test via a disposable test route/Volt wrapper** (lower-risk, more surgical, avoids expanding scope beyond "pure migration") — e.g. a minimal test-only Livewire page that renders `<livewire:survey-results-widget :survey-id="$id" />`, used only by the Browser test, not shipped as a real feature.

Path 1 is recommended because it makes the widget's `getChartKind()` dynamic-kind requirement (D-04) actually exercised by a real user flow (an admin editing a survey sees its live results), rather than only exercised by test scaffolding — and because an unreachable, never-rendered widget migrated for its own sake provides no operational value, which conflicts with this project's "no wasted/inaccurate-looking work" posture. Either path is a legitimate call for the plan to make explicitly; flag it as a Task-0-style decision, not an assumption baked into later tasks.

**Confidence: HIGH** — verified via repo-wide grep, zero false negatives possible for a class-name string search.

## Critical Finding 3: The requirement text says "3 sparklines," but the actual code has 5 `Stat::chart()` call sites across those 3 parent widgets

| Parent widget | `Stat::chart()` call sites | Which Stat |
|---|---|---|
| `CampaignStatsOverview` | 2 | `getTotalVotersStat()` → `getVotersGrowthChart()` (7-day voter growth, plain count); `getValidationProgressStat()` → `getValidationProgressChart()` (7-day validation %) |
| `CallCenterStatsWidget` | 2 | `getTotalCallsStat()` → `getLastWeekCallsChart()` (7-day call volume); `getConfirmationsStat()` → `getWeekConfirmationsChart()` (7-day confirmations) |
| `SurveyStatsOverview` | 1 | `getSurveyStats()`'s "Respuestas Únicas" stat → `getResponsesChart()` (7-day unique responses) — **only reachable when `$surveyId` is set; see Finding 4** |

D-01/D-02/D-04's phrasing ("the 3 embedded sparklines... `CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`") consistently names 3 *parent widget classes*, not 5 individual chart datasets — and D-01 says "a dedicated small `ChartWidget`-based widget... placed next to their parent," singular per parent. **Recommendation: build exactly 3 new dedicated widgets, one per parent, each showing that parent's single most prominent/headline sparkline** — this is explicitly Claude's discretion territory per CONTEXT.md, but the naming pattern in every locked decision points at "3," not "5." Suggested picks (the first/primary `Stat` in each parent's `getStats()` array):
- `CampaignStatsOverview` → the "Total de Apoyos" 7-day growth chart (`getVotersGrowthChart()`).
- `CallCenterStatsWidget` → the "Llamadas Totales Hoy" 7-day chart (`getLastWeekCallsChart()`).
- `SurveyStatsOverview` → the "Respuestas Únicas" chart (`getResponsesChart()`) — the *only* candidate that exists on this widget (see Finding 4 for its own separate wiring problem).

All 5 underlying data arrays are **plain `array<float>`** (no labels) — `Stat::chart(array $chart)`'s type hint confirms this (`vendor/filament/widgets/src/StatsOverviewWidget/Stat.php` line 106: `chart(array|Arrayable|null $chart)`, stored as `array<float>`). The new dedicated `ChartWidget`s' `getData()` can return the exact same shape `ReactIslandPocWidget` already established (`['points' => [{label, value}, ...]]`), synthesizing sequential labels (`'Día -6'`...`'Hoy'` or similar) since the source data has no inherent labels either.

**Confidence: HIGH** — read directly from `app/Filament/Widgets/CampaignStatsOverview.php`, `CallCenterStatsWidget.php`, `SurveyStatsOverview.php`, and `vendor/filament/widgets/src/StatsOverviewWidget/Stat.php`.

## Critical Finding 4: `SurveyStatsOverview`'s sparkline is currently unreachable on every panel it's registered on

`SurveyStatsOverview::getStats()` branches: `if (! $this->surveyId) { return $this->getGlobalStats(); }` — and **only** `getGlobalStats()`'s 3 stats (no `chart()` calls at all) are what a dashboard ever sees, because `$this->surveyId` is never set. Grep confirms: `SurveyStatsOverview::class` appears only in `AdminPanelProvider.php` and `ReportsPanelProvider.php`'s `->widgets([...])` arrays, with **no** `surveyId` parameter passed at registration (Filament's `->widgets([Widget::class])` array syntax, not the `->widgets([Widget::class => [...]])` data-passing syntax). `getSurveyStats()` (the branch with the `->chart()` call) is dead code on every currently-registered panel.

This means MIGR-02's "migrate `SurveyStatsOverview`'s sparkline" has no live production sparkline to migrate as things stand today — same root cause as Finding 2 (`SurveyResultsWidget`), suggesting these two survey-widgets' per-survey-detail features were built together but never wired into a page. **Recommendation:** couple the fix for this with Finding 2's — if `EditSurvey` (or a new `ViewSurvey` page) gains `getFooterWidgets()` passing `surveyId`, both `SurveyResultsWidget` and `SurveyStatsOverview`'s per-survey chart become live at the same time, and the new dedicated sparkline widget for `SurveyStatsOverview` should read the same `$surveyId` widget-data parameter to stay consistent with its parent.

**Confidence: HIGH** — verified via the exact registration call sites and `getStats()`'s own conditional.

## Standard Stack

No new packages — `react@19.2.8`, `react-dom@19.2.8`, `react-is@19.2.8`, `recharts@3.10.1`, `motion@13.1.1` are already installed (`package.json`, verified 2026-08-20) and already wired into `vite.config.js` (`resources/js/charts/main.jsx` is already a registered entry point) and all 5 `*PanelProvider.php` files (`Vite::withEntryPoints(['resources/js/charts/main.jsx'])` render hook already present on all 5, confirmed via grep). This phase adds zero new dependencies — it only adds new `.jsx` files under `resources/js/charts/components/` and new/modified PHP widget classes.

### Installation

No `npm install` needed. Confirm nothing has drifted since Phase 20 with:
```bash
npm ls react react-dom react-is recharts motion @vitejs/plugin-react
```

## Architecture Patterns

### Recommended Project Structure (delta from Phase 20)

```
resources/
├── js/
│   └── charts/
│       ├── main.jsx                        # UNCHANGED — Alpine bridge, already generic (reads chartKind/theme from x-data params)
│       ├── ChartRouter.jsx                  # NEW — replaces ChartCard.jsx as the main.jsx entry point; dispatches on `kind`
│       ├── components/
│       │   ├── ChartCard.jsx                # MODIFIED — PoC-only bar chart removed; becomes the shared chrome shell (error state + entrance animation + inner chart-area container only, NOT an outer card border — see UI-SPEC's explicit "don't build a second competing card" divergence)
│       │   ├── LineChart.jsx                # NEW — MonoRoundedLineChart-derived, multi-series, used by ValidationProgressChart
│       │   ├── BarChart.jsx                 # NEW — single-series ranked bar with per-Cell monochrome fill, used by TerritorialDistributionChart + SurveyResultsWidget's bar branch
│       │   ├── PieChart.jsx                 # NEW — MonoRoundedDonutChart-derived, used by SurveyResultsWidget's pie branch
│       │   └── SparklineChart.jsx           # NEW — MonoRoundedSparklineChart's single-row inner pattern, used by the 3 new dedicated sparkline widgets
│       └── lib/
│           ├── chartjs-adapter.js           # NEW — the load-bearing piece: {labels, datasets} -> Recharts row shapes (see Code Examples)
│           ├── palette.js                   # NEW — D-03's monochrome ramp: given N segments + an accent index, returns fill colors
│           └── formatters.js                # NEW — es-CO number formatting (per UI-SPEC Typography section)
├── views/
│   └── filament/
│       └── widgets/
│           └── react-chart.blade.php        # MODIFIED — generalize heading/description (see Code Examples), otherwise structurally unchanged
app/
└── Filament/
    └── Widgets/
        ├── ValidationProgressChart.php       # MODIFIED — repoint $view, add getChartKind(), keep getData() body untouched
        ├── TerritorialDistributionChart.php  # MODIFIED — same
        ├── SurveyResultsWidget.php           # MODIFIED — same, plus registration decision (Finding 2)
        ├── CampaignVotersSparklineWidget.php # NEW (naming: Claude's discretion) — dedicated ChartWidget, getChartKind() = 'sparkline'
        ├── CallCenterCallsSparklineWidget.php# NEW — same pattern
        └── SurveyResponsesSparklineWidget.php# NEW — same pattern, needs surveyId wiring (Finding 4)
```

### Pattern 1: `getChartKind()` proxy — already established, reuse verbatim

`ReactIslandPocWidget` (Phase 20) already implements the exact pattern `ARCHITECTURE.md` recommends:

```php
// Source: app/Filament/Widgets/ReactIslandPocWidget.php (this repo, Phase 20)
protected function getType(): string
{
    return $this->getChartKind();
}

protected function getChartKind(): string
{
    return 'poc';
}
```

Apply the same 2-method shape to all 6 widgets in this phase. For `SurveyResultsWidget`, `getChartKind()` becomes the existing dynamic `getType()` logic verbatim (rename only):

```php
protected function getChartKind(): string
{
    if ($this->questionId) {
        $question = SurveyQuestion::find($this->questionId);
        if (! $question) {
            return 'bar';
        }

        return match ($question->question_type) {
            QuestionType::YES_NO => 'pie',
            QuestionType::SCALE, QuestionType::SINGLE_CHOICE, QuestionType::MULTIPLE_CHOICE => 'bar',
            default => 'bar',
        };
    }

    return 'bar';
}

protected function getType(): string
{
    return $this->getChartKind();
}
```

`chartKind` is read once, at initial Blade render (`@js($this->getChartKind())` embedded into the Alpine `x-data` params, exactly as `react-chart.blade.php` already does) — it does not need to change on a poll tick, only `data` does. `$this->questionId`/`$this->surveyId` are set once at widget mount (no code path in this widget currently mutates them post-mount), so treating `chartKind` as fixed-for-the-instance's-lifetime is safe and matches the existing Blade/Alpine contract exactly.

### Pattern 2: Chart.js-shape → Recharts-shape adapter (the one genuinely new piece of plumbing this phase needs)

Per MIGR-01, `getData()`'s body stays unchanged — it keeps returning `{labels: string[], datasets: [{label, data: number[], backgroundColor?, borderColor?}, ...]}`. `ChartRouter`/the per-kind components must consume that shape directly (no PHP-side reshaping). Two small, shared, pure functions cover every kind in this phase:

```js
// resources/js/charts/lib/chartjs-adapter.js

/**
 * Multi-series -> one row per label, one key per dataset. Used by `line`.
 * {labels:['Ene','Feb'], datasets:[{label:'A',data:[1,2]},{label:'B',data:[3,4]}]}
 * -> [{ label: 'Ene', A: 1, B: 3 }, { label: 'Feb', A: 2, B: 4 }]
 */
export function toSeriesRows({ labels = [], datasets = [] }) {
    return labels.map((label, i) => ({
        label,
        ...Object.fromEntries(datasets.map((ds) => [ds.label, ds.data[i] ?? null])),
    }));
}

/**
 * Single-series -> name/value pairs, ranked by original array order. Used by `bar`, `pie`.
 * {labels:['A','B'], datasets:[{data:[10,4]}]} -> [{name:'A', value:10}, {name:'B', value:4}]
 */
export function toNameValueRows({ labels = [], datasets = [] }) {
    const data = datasets[0]?.data ?? [];
    return labels.map((name, i) => ({ name, value: data[i] ?? 0 }));
}
```

`BarChart.jsx`/`PieChart.jsx` should compute their own D-03 monochrome fill per row (accent for the highest-value row, descending zinc opacity for the rest — see `lib/palette.js` below), completely ignoring the incoming `backgroundColor` array from `getData()` (that array is Chart.js-era styling data that D-03 explicitly replaces, not a value to preserve).

### Pattern 3: D-03 monochrome ramp, generalized beyond the UI-SPEC's 3-tier example

UI-SPEC gives an explicit 3-segment example (Base 100% / Mid 50% / Top 20%) but `TerritorialDistributionChart` has up to 10 segments and `SurveyResultsWidget`'s bar branch can have 2-8. A simple linear interpolation generalizes the documented 3-tier rule without inventing a new design language:

```js
// resources/js/charts/lib/palette.js
const ACCENT = '#f97316';       // primary-500, D-03's single accent
const ZINC_MAX_OPACITY = 1.0;   // matches UI-SPEC's "Base" tier
const ZINC_MIN_OPACITY = 0.15;  // slightly below UI-SPEC's "Top" (0.2) tier, for long tails beyond 3 segments

export function rankedMonochromeFill(index, total, { isDark = false } = {}) {
    if (index === 0) return ACCENT; // highest-ranked segment = accent (D-03)
    if (total <= 1) return ACCENT;

    const t = (index - 1) / Math.max(total - 2, 1); // 0 for 2nd item, 1 for last item
    const opacity = ZINC_MAX_OPACITY - t * (ZINC_MAX_OPACITY - ZINC_MIN_OPACITY);
    const base = isDark ? '255,255,255' : '9,9,11'; // zinc-950-ish, matches MonoCharts' isDark ? #FFFFFF : #09090B convention
    return `rgba(${base},${opacity.toFixed(2)})`;
}
```

This is a concrete, defensible default — flag it as Claude's-discretion-adjacent (not literally specified by UI-SPEC) but consistent with D-03's explicit intent and MonoCharts' own opacity-layering technique (verified in `MonoRoundedDonutChart.tsx`, which hardcodes exactly 4 opacity tiers for its 4-segment demo — this function is the N-segment generalization of that same idea).

**Open sub-question for the plan:** for `SurveyResultsWidget`'s bar branch, should "rank" mean sorted-by-value (matching `TerritorialDistributionChart`'s already-sorted-by-`orderByDesc('total')` query) or insertion-order (the `foreach ($metrics->distribution as $option => $stats)` loop's natural order, which is NOT guaranteed sorted)? Recommend sorting client-side in `BarChart.jsx` before assigning ranks, so the "highest = accent" rule is visually true regardless of what order `getData()` happens to emit rows in — cheap, and avoids a false-looking accent assignment.

### Pattern 4: Generalizing `react-chart.blade.php`

Current PoC-only line: `<x-filament::section heading="React Island PoC">` — hardcoded. Generalize to read the widget's real heading/description (already inherited from `ChartWidget`/`Widget`, and already dynamically overridden by `SurveyResultsWidget::getHeading()`):

```blade
{{-- Source: resources/views/filament/widgets/react-chart.blade.php, generalized --}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="$this->getHeading()"
        :description="$this->getDescription()"
    >
        {{-- wire:poll / wire:ignore / Alpine bridge skeleton: UNCHANGED from Phase 20 --}}
    </x-filament::section>
    {{-- fallback-timeout <script>: UNCHANGED from Phase 20 --}}
</x-filament-widgets::widget>
```

Everything else in the file (the `wire:poll`/`wire:ignore` skeleton, the `x-data="reactChartBridge({...})"` call, the fallback-timeout `<script>`) is already generic — it takes `chartKind`/`initialData`/`theme` as parameters and has no PoC-specific logic baked in beyond the hardcoded heading string. No other changes needed to make this "one shared view for all 6 widgets," confirming `SUMMARY.md`'s framing.

### Anti-Patterns to Avoid

- **Reshaping `getData()`'s return value to a Recharts-native shape:** MIGR-01 explicitly requires `getData()` bodies stay unchanged. Do the reshaping in JS (`chartjs-adapter.js`), not PHP.
- **Building a real Recharts `stackId`-grouped bar component for this phase:** nothing in scope needs it (Critical Finding 1) — would be speculative work for Phase 22, not Phase 21.
- **Reading `getData()`'s `backgroundColor` arrays as the chart's actual fill colors:** D-03 replaces the entire palette; those arrays are dead styling data post-migration, not a value to thread through.
- **Building a second, MonoCharts-style outer card** inside `react-chart.blade.php`'s existing `<x-filament::section>` — UI-SPEC is explicit that this is a deliberate divergence from MonoCharts' own demo (which assumes no host chrome exists). Only the *inner* chart-area container (14px radius) is new/React-owned.
- **Treating `SurveyResultsWidget`/`SurveyStatsOverview`'s registration gap (Findings 2 & 4) as someone else's problem or an assumption to skip past** — without resolving it, Success Criterion #3's Browser test literally cannot be written for `SurveyResultsWidget` or (meaningfully) for the `SurveyStatsOverview`-derived sparkline.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Livewire→React data bridge | A second bridge mechanism/event channel for this phase's widgets | The exact `reactChartBridge` Alpine component + `dispatch('updateChartData')` pattern already proven by `ReactIslandPocWidget` in Phase 20 | Already built, browser-verified, zero reason to diverge — `main.jsx` needs no changes for this phase |
| Chart.js→Recharts data reshaping | Ad hoc per-widget reshaping logic scattered across 6 React components | One shared `lib/chartjs-adapter.js` with 2 pure functions (Pattern 2) | All 3 existing widgets share the same 2 underlying shapes; duplicating the reshape logic per component risks silent divergence bugs |
| Campaign/role-scoped sparkline data queries | A second, independent query implementation inside each new dedicated sparkline `ChartWidget` that re-derives the same numbers as its parent `StatsOverviewWidget` | Extract each sparkline's underlying query (`getVotersGrowthChart()`, `getLastWeekCallsChart()`, `getResponsesChart()`) into a `protected`/`public` method reusable by both the parent `StatsOverviewWidget` (still driving its own fallback `Stat::chart()`) and the new dedicated `ChartWidget`, e.g. via a small shared trait or by simply widening the existing method's visibility and calling it from the new widget | Two independent implementations of "7-day voter growth, campaign/role-scoped" are a drift risk this project's own constraint ("reporting/widgets must reflect campaign reality — inaccurate numbers unacceptable") explicitly warns against |
| Tooltip positioning/styling | A from-scratch positioned tooltip component | Recharts' `<Tooltip content={<CustomTooltip />}>` prop, styled after MonoCharts' `DitherChartTooltipContent` (full source verified below, in Code Examples) | Real, working reference implementation already exists and matches UI-SPEC's explicit tooltip contract (8px radius, chart-area bg token, backdrop blur) |

**Key insight:** every piece of genuinely new plumbing this phase needs (the adapter functions, the palette ramp, the heading/description generalization) is small, shared, and reusable across all 6 widgets — building it once centrally (rather than per-widget) avoids the exact "retrofit across N widgets is expensive" trap `PITFALLS.md` flags for the mount/unmount helper in Phase 20.

## Common Pitfalls

### Pitfall 1: `getOptions()` overrides on the 3 existing widgets become silently-dead code after migration

**What goes wrong:** `ValidationProgressChart`, `TerritorialDistributionChart`, and `SurveyResultsWidget` all currently override `getOptions(): array` with Chart.js-shaped configuration (`plugins.legend`, `scales.y.beginAtZero`, `interaction.mode`). `react-chart.blade.php` never calls `$this->getOptions()` anywhere (confirmed by reading the file) — Recharts components don't consume Chart.js options objects. After migration, these overrides keep compiling and keep returning data, but nothing reads them.
**Why it happens:** Migrating a widget's `$view`/`getType()`/`getData()` feels complete without auditing every method `ChartWidget` exposes; `getOptions()` is easy to leave untouched since removing it isn't required for the widget to render.
**How to avoid:** Explicitly decide, per widget, whether to delete the `getOptions()` override (cleanest — nothing reads it) or keep it with a `@deprecated`/explanatory comment if the plan wants to preserve the *intent* (e.g. `interaction.mode = 'index'` → Recharts' `<Tooltip shared>`-equivalent hover-all-series-at-once behavior) as a note for whoever builds the React components. Recommend deletion — the equivalent intent is better expressed directly in each Recharts component's own JSX (e.g. hardcode `legend display + position` in the component itself, matching D-02's "full composition now" framing) rather than left as orphaned PHP config nobody reads.
**Warning signs:** `getOptions()` present in `git diff` with zero corresponding change in Blade/JS; a future developer confused about why changing `getOptions()` has no visible effect.
**Phase to address:** This phase, per-widget, as part of each migration task's own diff review.

### Pitfall 2: `SurveyResultsWidget`'s Browser test needs `visit()`-able state, but nothing sets `questionId` on any registered instance either

Same root cause as Findings 2/4, called out again because it's easy to fix Finding 2 (register on a survey page) while forgetting `questionId` still defaults to `null`, which routes to `getChartKind()`'s `'bar'` fallback (`Sin datos` state) rather than exercising the `pie`/`bar`-by-question-type dynamic logic D-04 explicitly requires be preserved and tested. The Browser test needs a real `SurveyQuestion` fixture with a `YES_NO` type (to prove `pie`) and ideally a second assertion with a `SCALE`/`SINGLE_CHOICE` question (to prove `bar`) — not just a `surveyId`-only mount.
**Phase to address:** Task that writes `SurveyResultsWidget`'s Browser test — plan should specify at least 2 fixture questions (one `YES_NO`, one non-`YES_NO`) to actually exercise D-04, not just render the widget once.

### Pitfall 3: `Stat::make()->chart()` sparkline arrays have no labels — synthesizing them wrong will silently misrepresent recency

`CampaignStatsOverview::getVotersGrowthChart()` et al. return a plain `array<float>` with **no date labels**, ordered oldest-to-newest (`for ($i = 6; $i >= 0; $i--)`, so index 0 = 6 days ago, index 6 = today). When the new dedicated sparkline widgets wrap this into `ReactIslandPocWidget`'s `{'points': [{label, value}]}` shape, the synthesized labels must preserve this oldest-to-newest ordering (`points[0]` = 6 days ago) — reversing it, or mislabeling which end is "today," would make the sparkline's implicit trend direction backwards, which is exactly the kind of "inaccurate operational number" this project's constraints explicitly forbid, even though the underlying data itself is correct.
**How to avoid:** When wrapping these arrays, keep the widget's own loop order and verify the rightmost point on the resulting sparkline visually corresponds to "today" in a manual/browser check, not just an automated assertion on the raw array.
**Phase to address:** This phase, in each of the 3 new sparkline widgets' own implementation task.

## Code Examples

### `DitherChartTooltipContent` — MonoCharts' real tooltip primitive (verified via raw source fetch, 2026-08-20)

```typescript
// Source: raw.githubusercontent.com/Subhan-code/Monocharts/main/src/components/dither-charts/lib/recharts-tooltip.tsx
export function DitherChartTooltipContent({ active, payload, label, theme = 'dark', indicator = 'dot', formatter }) {
  if (!active || !payload || payload.length === 0) return null;
  const isDark = theme === 'dark';
  return (
    <div className={`px-3 py-2 rounded-xl text-xs shadow-2xl backdrop-blur-md border ... ${
      isDark ? 'bg-[#181818]/90 border-white/10 text-white' : 'bg-white/95 border-neutral-200 text-neutral-900'
    }`}>
      {label && <div className="font-medium mb-1.5 pb-1 border-b ...">{label}</div>}
      {payload.map((item, idx) => (
        <div key={idx} className="flex items-center justify-between gap-3">
          <span style={{ backgroundColor: item.color || item.fill }} className="w-2 h-2 rounded-full ring-1 ring-white/20" />
          <span>{item.name || item.dataKey}:</span>
          <span className="font-semibold tabular-nums">{item.value.toLocaleString()}</span>
        </div>
      ))}
    </div>
  );
}
```
Directly adaptable: swap `toLocaleString()` for the `es-CO`-locale formatter UI-SPEC's Typography section requires (`resources/js/charts/lib/formatters.js`), adjust radius to 8px per UI-SPEC's "Tooltip / badge pill" spacing table (MonoCharts uses `rounded-xl` ≈12px; UI-SPEC specifies 8px for tooltips — use UI-SPEC's value, it is the authoritative spec for this project, not MonoCharts' own demo).

### `MonoRoundedLineChart` structure (verified via raw source fetch, 2026-08-20) — basis for `LineChart.jsx`

Key structural elements to carry over: `ResponsiveContainer` + `LineChart` with `margin={{ top: 12, right: 12, left: -22, bottom: 0 }}`, `CartesianGrid` with `vertical={false}` and a near-invisible stroke (`rgba(0,0,0,0.05)` light / `rgba(255,255,255,0.05)` dark — matches UI-SPEC's gridline color exactly), `XAxis`/`YAxis` with `tickLine={false} axisLine={false}` and 10px tick font, one `<Line dot activeDot>` per series with `type="monotone" strokeLinecap="round"`. For SIGMA: the "secondary" (non-accent) line uses the D-03 zinc-base-opacity color instead of MonoCharts' own dashed-gray secondary; the "primary" (accent) line uses `#f97316` instead of MonoCharts' pure white/black.

### `MonoRoundedDonutChart` structure (verified via raw source fetch, 2026-08-20) — basis for `PieChart.jsx` and the per-`Cell` fill technique `BarChart.jsx` reuses

Key technique: `<Pie data=... dataKey="value" nameKey="name" innerRadius outerRadius paddingAngle={6} cornerRadius={8}>` wrapping one `<Cell fill={...} stroke={cardBg} strokeWidth={2}>` per segment, with per-segment fill computed from index (this is exactly the pattern `lib/palette.js`'s `rankedMonochromeFill()` generalizes for N>4 segments). Center-callout text (`hoverIndex !== null ? value : total`) matches UI-SPEC's "Display" typography role (24px/600, single number per instance) — reuse this pattern for the donut center value described in UI-SPEC, and note `SurveyResultsWidget`'s pie branch (2 segments, Sí/No) is a valid, simpler case of this same component.

### `MonoRoundedSparklineChart` structure (verified via raw source fetch, 2026-08-20) — basis for `SparklineChart.jsx`

The upstream component renders 3 stacked rows (label + value + mini `<LineChart>`) in one card — SIGMA's 3 new dedicated sparkline widgets are each their *own* full MonoCharts card (per D-02, "full composition now" applies to all 6 widgets), so `SparklineChart.jsx` should extract just the **single-row inner pattern** (`<ResponsiveContainer height="100%"><LineChart data={points}><Line dataKey="value" dot={false} strokeLinecap="round" /></LineChart></ResponsiveContainer>`, no axes/grid at all) and let `ChartCard.jsx`'s shared chrome (header/footer/entrance-animation) provide the surrounding card — not the upstream's own multi-row-in-one-card layout.

## Open Questions

1. **Where does `SurveyResultsWidget` get mounted, and does that count as in-scope for a "migration" phase?**
   - What we know: it is fully unregistered today (Finding 2); registering it somewhere is required to satisfy Success Criterion #3 literally.
   - What's unclear: whether wiring it into `EditSurvey`'s footer widgets (or a new page) is "plumbing needed to migrate an existing widget" (in scope) or "adding a new feature" (arguably out of scope per this phase's explicit "migration-only" framing).
   - Recommendation: treat it as in-scope plumbing (Path 1 in Finding 2) — the widget's own code (`canView(): true`, `surveyId`/`questionId` properties) already assumes it will be page-mounted; wiring it up is completing an already-intended integration, not adding new business logic. Flag explicitly in the plan's own scope note so it isn't silently skipped or silently over-scoped.

2. **Exact opacity-ramp formula for D-03's monochrome palette beyond 3 segments.**
   - What we know: UI-SPEC gives a 3-tier example (100%/50%/20%); `TerritorialDistributionChart` needs up to 10 segments.
   - What's unclear: no exact interpolation formula is specified for N>3.
   - Recommendation: use the linear interpolation in Pattern 3 (`rankedMonochromeFill`) as a reasonable default; treat exact min/max opacity constants as tunable during implementation/visual review, not a hard contract.

3. **Should `getOptions()` overrides be deleted or kept-with-comment on the 3 migrated widgets?**
   - What we know: nothing reads them post-migration (Pitfall 1).
   - What's unclear: whether the plan wants a git-history trail of "this used to mean X in Chart.js" preserved via comment, vs. a clean deletion.
   - Recommendation: delete — cleaner, and the equivalent visual intent moves into the React components themselves, which is the actual source of truth going forward.

## Environment Availability

No new external dependencies — all packages (`react`, `react-dom`, `recharts`, `motion`, `react-is`, `@vitejs/plugin-react`) are already installed and verified working end-to-end by Phase 20's browser-checkpoint (`STATE.md` Phase 20 Plan 03 decisions: admin panel verified directly via Chrome browser automation, live poll-cycle update confirmed, clean console). No environment audit needed for this phase — it is pure code/config work on top of an already-provisioned toolchain.

## Sources

### Primary (HIGH confidence)
- This repository's own source, read directly (2026-08-20): `app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php`, `SurveyResultsWidget.php`, `CampaignStatsOverview.php`, `CallCenterStatsWidget.php`, `SurveyStatsOverview.php`, `ReactIslandPocWidget.php`; `resources/js/charts/main.jsx`, `components/ChartCard.jsx`; `resources/views/filament/widgets/react-chart.blade.php`; `app/Providers/Filament/*PanelProvider.php` (all 5, grepped for widget registrations and Vite entry-point render hook); `vendor/filament/widgets/src/ChartWidget.php`, `StatsOverviewWidget/Stat.php`; `tests/Browser/ReactIslandPocWidgetTest.php`; `tests/Pest.php` (`loginRealBrowserUser()`); `package.json`, `vite.config.js`; full-repo grep confirming `SurveyResultsWidget` has zero registration/test references.
- `github.com/Subhan-code/Monocharts` — `MonoRoundedLineChart.tsx`, `MonoRoundedStackedBarChart.tsx`, `MonoRoundedDonutChart.tsx`, `MonoRoundedSparklineChart.tsx`, `dither-charts/lib/recharts-tooltip.tsx` — fetched live via raw.githubusercontent.com, 2026-08-20, full source read and reproduced above.
- `.planning/research/ARCHITECTURE.md`, `FEATURES.md`, `PITFALLS.md`, `STACK.md`, `SUMMARY.md` — this milestone's Phase-20-produced research, HIGH confidence per their own sourcing.
- `.planning/phases/20-react-island-infrastructure/20-CONTEXT.md`, `.planning/phases/21-migrate-existing-charts-to-react-recharts/21-CONTEXT.md`, `21-UI-SPEC.md` — locked decisions and UI contract for this phase.
- `.planning/REQUIREMENTS.md`, `.planning/STATE.md` — requirement text and Phase 20 completion decisions (including the concrete browser-verification precedent this phase should follow, per the user's standing "browser-verify before prod" preference).

### Secondary (MEDIUM confidence)
None — every load-bearing claim in this document was verified directly against this repo's own source or MonoCharts' live-fetched upstream source.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages, everything already installed/verified in Phase 20.
- Architecture: HIGH — bridge mechanism already proven; the adapter/palette/registration patterns in this document are derived directly from reading the actual widget source, not inferred.
- Pitfalls: HIGH — all 3 pitfalls above are concrete, code-verified findings (unread `getOptions()`, missing test fixtures, label-ordering), not generic cross-framework speculation like some of Phase 20's pitfalls research.

**Research date:** 2026-08-20
**Valid until:** Should remain valid for the duration of this phase's execution (no external/fast-moving dependencies); re-verify MonoCharts source only if the plan is revisited after a significant delay, since it's an actively-developed external repo.
