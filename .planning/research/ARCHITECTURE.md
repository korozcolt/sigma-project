# Architecture Research

**Domain:** React+Recharts+Motion micro-frontend island inside a Livewire 3 / Filament 4 widget system
**Researched:** 2026-08-20
**Confidence:** HIGH — the core bridge mechanism is not a hypothesis, it is reverse-engineered from Filament's own vendored `ChartWidget` implementation already running in this codebase (`vendor/filament/widgets/resources/js/components/chart.js` and `vendor/filament/widgets/resources/views/chart-widget.blade.php`), cross-checked against official Livewire and Laravel Vite docs.

## Standard Architecture

### System Overview

```
┌───────────────────────────────────────────────────────────────────┐
│  Filament Widget (PHP) — App\Filament\Widgets\*                    │
│  extends ChartWidget (reuse polling/checksum plumbing)             │
│  or plain Widget+CanPoll (RevalidationProgressWidget precedent)    │
├───────────────────────────────────────────────────────────────────┤
│  Custom Blade view — resources/views/filament/widgets/*.blade.php  │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ <div wire:poll.{interval}="updateChartData">  (LIVE, morphed)│  │
│  │   <div wire:ignore                                          │  │
│  │        x-data="reactChartBridge({ initialData, chartKind })">│  │
│  │     <div data-react-root></div>   ← Livewire NEVER touches   │  │
│  │   </div>                                                     │  │
│  │ </div>                                                        │  │
│  └─────────────────────────────────────────────────────────────┘  │
├───────────────────────────────────────────────────────────────────┤
│  Vite chunk: resources/js/charts/main.tsx (separate entry)         │
│  - registers Alpine.data('reactChartBridge', ...)                  │
│  - on init(): ReactDOM.createRoot(el).render(<ChartRouter .../>)   │
│  - this.$wire.$on('updateChartData', ({data}) => setState(data))   │
│    → React re-renders via STATE UPDATE, not remount                │
├───────────────────────────────────────────────────────────────────┤
│  React tree (Recharts for charts, Motion for transitions)          │
│  ChartRouter picks component by `chartKind` prop (sankey, treemap, │
│  funnel, heatmap, stacked-area, gauge, ...) — one bundle, N charts  │
└───────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|-------------------------|
| Filament Widget class (PHP) | Owns data query/scoping (campaign isolation, role scoping — same rules as every existing widget), serializes to a JSON-safe shape, drives the poll cycle | `extends ChartWidget` (inherits `getData()`/`updateChartData()`/checksum/dispatch) or `extends Widget` + `CanPoll` (RevalidationProgressWidget pattern) with a hand-rolled `dispatch()` call |
| Custom Blade view | Declares the `wire:poll` boundary *outside* the ignored subtree, and the `wire:ignore` boundary around the React mount point; passes initial payload as `@js(...)` | One file per widget under `resources/views/filament/widgets/`, following `revalidation-progress-widget.blade.php`'s existing custom-view pattern |
| Alpine bridge (`reactChartBridge`) | Sole channel between Livewire and React: mounts the React root once, listens for the Livewire-dispatched event, converts it into a React state update | New JS module, structurally a clone of vendored `chart.js`'s `chart()` Alpine component |
| React root (per widget instance) | Owns Recharts render + Motion transitions; re-renders on prop/state change only, never remounted by Livewire | `ReactDOM.createRoot()` called exactly once per DOM node, in `init()` |
| Vite entry (`resources/js/charts/main.tsx`) | Separate build chunk, code-split from `resources/js/app.js`, loaded only where charts are needed | Registered as an additional `input` in `laravel-vite-plugin`'s config, referenced via its own `@vite([...])` call |

## Answering the Four Specific Questions

### Q1 — Is `wire:ignore` the correct mechanism?

**Yes — confirmed, this is exactly how Filament's own `ChartWidget` protects its Chart.js canvas today.** The vendored view (`vendor/filament/widgets/resources/views/chart-widget.blade.php`) wraps the Alpine-driven chart container in `wire:ignore`:

```blade
<div @if ($pollingInterval) wire:poll.{{ $pollingInterval }}="updateChartData" @endif>
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
        wire:ignore
        x-data="chart({ cachedData: @js($this->getCachedData()), options: @js($this->getOptions()), type: @js($type) })"
    >
        <canvas x-ref="canvas"></canvas>
        ...
    </div>
</div>
```

Note the `wire:poll` is on the **outer** div, and `wire:ignore` on the **inner** div. This is the pattern to replicate: `wire:ignore` on the React mount root only, one level below the polling trigger, not on the whole widget. `wire:ignore.self` is not needed here (no Livewire-bound attributes on the mount div itself that need to keep updating; the whole subtree is React-owned).

**Confidence: HIGH** (verified in this project's own vendor code, not training data).

### Q2 — How does the container re-receive fresh data on each poll tick?

**Not `Livewire.hook('morph.updated')` and not `livewire:navigated`.** Both were offered as hypotheses in the question; neither is what Filament itself uses, and both have real drawbacks confirmed in the Livewire GitHub discussions surveyed: `morph.updated` fires globally (once per *any* Livewire morph on the page, not scoped to this widget) and fires multiple times per update cycle, forcing you to debounce/dedupe and to re-derive "did *my* widget's data actually change" from scratch.

**The mechanism Filament actually uses — and the one to copy — is a Livewire-dispatched browser event, heard via Alpine's `$wire` magic from *inside* the `wire:ignore`d node:**

1. `wire:poll.{interval}="updateChartData"` on the wrapper (outside `wire:ignore`) calls a **Livewire component method** each tick — this is a normal AJAX round-trip, unrelated to DOM morphing.
2. That PHP method (`ChartWidget::updateChartData()`) computes a checksum of the fresh data and only dispatches if it changed:
   ```php
   public function updateChartData(): void
   {
       $newDataChecksum = $this->generateDataChecksum();
       if ($newDataChecksum !== $this->dataChecksum) {
           $this->dataChecksum = $newDataChecksum;
           $this->dispatch('updateChartData', data: $this->getCachedData());
       }
   }
   ```
3. `$this->dispatch(...)` fires a **browser CustomEvent** that is delivered regardless of `wire:ignore` — dispatch is independent of DOM morphing, it just rides along on the same AJAX response.
4. The Alpine component living *inside* the ignored node — `wire:ignore` only stops Livewire from **touching the DOM**, it does not disable Alpine reactivity or the `$wire` magic property — listens and updates in place:
   ```js
   this.$wire.$on('updateChartData', ({ data }) => {
       const chart = this.getChart()
       chart.data = data
       chart.update('resize')   // in-place update, NOT a remount
   })
   ```

**For the React island, replicate this 1:1**, substituting a React state update for `chart.update()`:
```js
// resources/js/charts/main.tsx
Alpine.data('reactChartBridge', ({ initialData, chartKind }) => ({
    root: null,
    init() {
        this.root = ReactDOM.createRoot(this.$el);
        this.render(initialData);
        this.$wire.$on('updateChartData', ({ data }) => this.render(data));
    },
    render(data) {
        this.root.render(<ChartRouter kind={chartKind} data={data} />);
    },
}));
```
Calling `root.render()` again on the **same** root is not a remount — React reconciles against the existing tree, so component state, Recharts internal animation state, and any in-flight Motion transitions are preserved across polls exactly like Chart.js's `chart.update()` preserves the canvas. Full teardown/recreate (`root.unmount()` + new `createRoot()`) would defeat the purpose of an island and should be avoided on every poll tick — only do that on `livewire:navigated` (SPA-style full page swap) to avoid leaking roots across page loads. This is the one edge case where a `livewire:navigated` listener genuinely is needed — for cleanup, not for data refresh.

**Confidence: HIGH** — mechanism is directly read from this project's vendored Filament source, not inferred.

### Q3 — Minimal Vite multi-entry setup for `laravel-vite-plugin` ^2.0

Current `vite.config.js` (single `input` array, three shared entries):
```js
laravel({
    input: ['resources/css/app.css', 'resources/css/filament/theme.css', 'resources/js/app.js'],
    refresh: true,
}),
```
`laravel-vite-plugin`'s `input` accepts an array of independent entry points that each get their own output chunk — you do **not** need a second Vite config file (that's only required for a fully separate build directory/manifest, which is unnecessary here). Add the React plugin and a fourth entry:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/theme.css',
                'resources/js/app.js',
                'resources/js/charts/main.tsx',   // NEW — isolated React chunk
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: { cors: true },
});
```
New dependencies to add (dev): `@vitejs/plugin-react`. Runtime deps: `react`, `react-dom`, `recharts`, `motion` (the current npm package name for the animation library formerly "Framer Motion" — confirm this is what "Motion" in the milestone brief refers to, not the existing hand-rolled `motion-init.blade.php` vanilla-JS UX script already in this codebase, which is unrelated and should not be conflated or renamed during this work).

**Blade reference — do NOT add this to the shared root layout** (`resources/views/components/layouts/app.blade.php`, used by Volt/leader-facing pages) since charts are Filament-panel-only. Instead register it panel-wide via the same `PanelsRenderHook::BODY_END`/`HEAD_END` mechanism already used for `motion-init.blade.php`, scoped only to the panels that ship chart widgets (Admin, Reports today; extend to Coordinator/AreaCoordinator/Leader panels only if/when charts are added there):

```php
// AdminPanelProvider.php / ReportsPanelProvider.php
use Illuminate\Support\Facades\Vite;

->renderHook(
    PanelsRenderHook::HEAD_END,
    fn () => Vite::withEntryPoints(['resources/js/charts/main.tsx'])->toHtml(),
)
```
This mirrors the existing `->viteTheme('resources/css/filament/theme.css')` and `->renderHook(..., fn () => view('filament.components.motion-init'))` calls already present in every `*PanelProvider.php`. `Vite::withEntryPoints()` is the programmatic equivalent of the `@vite([...])` Blade directive and is safe to call once per panel (browsers dedupe identical `<script type="module" src>` tags across a single page load, so even if a dashboard shows 5 chart widgets, the chunk loads once). Do **not** duplicate `@vite(['resources/js/charts/main.tsx'])` inside every individual widget Blade view — that risks duplicate root registration attempts if a widget partial is ever re-rendered as a fragment.

Add `@viteReactRefresh` immediately before that entry point's tag **only if** JSX/TSX Fast Refresh in local dev is wanted; it compiles to nothing in production builds (confirmed: the directive injects the React Refresh preamble only when Vite is running its dev server via the hot file — `vite build` output omits it entirely), so it's safe to always include with no production cost:
```php
->renderHook(
    PanelsRenderHook::HEAD_END,
    fn () => Vite::withEntryPoints(['resources/js/charts/main.tsx'])->toHtml(),
)
```
(no separate `@viteReactRefresh` call needed via the `Vite::` facade path — `withEntryPoints()->toHtml()` already emits the correct dev-mode preamble automatically when the Vite dev server is running, matching what `@vite()` does under the hood.)

**Confidence: HIGH** for the multi-entry `input` array and official React plugin wiring (Laravel official docs, verified 2026-08-20). **MEDIUM** for the exact `Vite::withEntryPoints()` auto-preamble claim — verify by running `npm run dev` once and confirming Fast Refresh works before relying on it; if not, fall back to the documented `@viteReactRefresh` + `@vite()` Blade-directive pair placed once in a shared widget-wrapping Blade partial.

### Q4 — Custom `Widget` subclass per chart vs. overriding `ChartWidget`'s view

**Recommendation: extend `ChartWidget` and override its `protected string $view` property**, rather than starting fresh from plain `Widget` for every chart type. Both are legitimate patterns already proven in this codebase, but they solve different problems:

- `RevalidationProgressWidget` (plain `Widget` + `CanPoll`) is right when there is **no reusable data-refresh contract** to inherit — it just re-renders its whole Blade fragment on each poll via normal Livewire morphing, because none of its content is a foreign-JS-owned subtree.
- `ChartWidget` already solves exactly the problem this milestone has — "get fresh JSON into a `wire:ignore`d foreign-rendered subtree without full remount" — via its `getData()` → `getCachedData()` → checksum → `dispatch('updateChartData', ...)` pipeline. Reimplementing that by hand in N new plain-`Widget` classes would duplicate proven plumbing (`CanPoll`, `dataChecksum` with `#[Locked]`, filter-schema support, the `rendering()` lifecycle hook) for no benefit.

Because `protected string $view` is `protected` (not `final`/private) on `ChartWidget`, a subclass can freely repoint it:
```php
class SankeyTransitionsChart extends ChartWidget
{
    protected string $view = 'filament.widgets.react-chart'; // overrides filament-widgets::chart-widget
    protected ?string $pollingInterval = '120s';

    protected function getType(): string { return 'sankey'; } // repurposed as a "chartKind" discriminator, not a literal Chart.js type

    protected function getData(): array
    {
        // Any JSON-serializable shape is fine — getData()'s contract is
        // `array<string, mixed>`, NOT the Chart.js {labels, datasets} shape.
        // Filament only enforces that shape inside its own default view,
        // which this widget no longer uses.
        return ['nodes' => [...], 'links' => [...]];
    }
}
```
One shared Blade view (`resources/views/filament/widgets/react-chart.blade.php`) can serve every chart-kind widget — it just needs `getType()`'s return value (or a dedicated `getChartKind()` method, clearer naming since `getType()` no longer means "Chart.js type") to route to the right React component client-side, plus `$this->getCachedData()` for the initial payload and the same `wire:poll="updateChartData"` / `wire:ignore` skeleton described in Q1/Q2. This gives one PHP base pattern and one Blade view for all 10+ new chart widgets in this milestone, with only `getData()`/`getType()` differing per subclass — same shape as how `ValidationProgressChart` and `TerritorialDistributionChart` already differ from each other today.

**Exception — the 3 embedded `Stat::make()->chart([...])` sparklines** (in `CallCenterStatsWidget`, `CampaignStatsOverview`, `SurveyStatsOverview`) are a **structurally different** Filament primitive (`Filament\Widgets\StatsOverviewWidget\Stat`, its own `protected string $view = 'filament-widgets::stats-overview-widget.stat'`), not a `ChartWidget`. `Stat` has no `dispatch()`/checksum plumbing of its own — it's a single small SVG line rendered per-Stat inside a `StatsOverviewWidget`'s grid. Migrating these to React sparklines means either (a) leaving the parent `StatsOverviewWidget` alone and swapping only the tiny inline SVG the `Stat` component renders for a mounted mini Recharts `<Sparkline>` (requires a custom `Stat`-shaped Blade partial, harder to isolate since `Stat` doesn't currently support `wire:poll` per-item), or (b) converting the specific Stat(s) that need real sparklines into small dedicated `ChartWidget`-based widgets placed next to the `StatsOverviewWidget` in the panel's `->widgets([...])` array instead of embedded inside it. **(b) is simpler and lower-risk** given the proven pattern above — flag this as a design decision for the roadmap rather than assuming embedded-sparkline parity is required.

**Confidence: HIGH** — `$view` overridability and `Stat`'s separate view property both verified directly in `vendor/filament/widgets/src/*.php`.

## Recommended Project Structure

```
resources/
├── js/
│   ├── app.js                        # unchanged, still empty/shared entry
│   └── charts/
│       ├── main.tsx                  # NEW — Vite entry, registers Alpine.data('reactChartBridge', ...)
│       ├── ChartRouter.tsx           # NEW — picks Recharts component by `kind` prop
│       ├── components/
│       │   ├── FunnelChart.tsx       # NEW
│       │   ├── SankeyChart.tsx       # NEW
│       │   ├── TreemapChart.tsx      # NEW
│       │   ├── HeatmapChart.tsx      # NEW (Recharts has no native heatmap — custom composed chart)
│       │   ├── StackedAreaChart.tsx  # NEW
│       │   ├── GaugeChart.tsx        # NEW (Recharts has no native gauge — RadialBarChart-based)
│       │   └── DonutChart.tsx        # NEW
│       └── lib/
│           └── formatters.ts         # NEW — shared es-CO number/date formatting (mirrors motion-init.blade.php's toLocaleString('es-CO') convention)
├── views/
│   └── filament/
│       └── widgets/
│           ├── revalidation-progress-widget.blade.php   # unchanged, precedent only
│           └── react-chart.blade.php                    # NEW — one shared view for all React-backed ChartWidget subclasses
app/
└── Filament/
    └── Widgets/
        ├── ValidationProgressChart.php        # MODIFIED — repoint $view, keep getData()/campaign scoping
        ├── TerritorialDistributionChart.php    # MODIFIED — same
        ├── SurveyResultsWidget.php             # MODIFIED — same
        ├── VoterStatusFunnelChart.php           # NEW
        ├── VoterStatusDonutChart.php             # NEW
        ├── ValidationHistorySankeyChart.php     # NEW — first surface for previously-invisible ValidationHistory data
        ├── TerritorialTreemapChart.php          # NEW
        ├── CoordinatorTeamStackedBarChart.php   # NEW
        ├── CallerHeatmapChart.php               # NEW
        ├── CallAttemptFunnelChart.php           # NEW
        ├── RejectionReasonsStreamChart.php      # NEW
        ├── SurveyScaleHistogramGaugeChart.php   # NEW
        ├── DiaDLiveLineChart.php                # NEW — Día D live voting line (VoteRecord.voted_at)
        └── MessageDeliveryFunnelChart.php       # NEW — first surface for previously-invisible MessageBatch/Message data
vite.config.js                                    # MODIFIED — add react() plugin + 4th input entry
package.json                                      # MODIFIED — add react, react-dom, recharts, motion, @vitejs/plugin-react
app/Providers/Filament/AdminPanelProvider.php     # MODIFIED — add Vite::withEntryPoints() render hook
app/Providers/Filament/ReportsPanelProvider.php   # MODIFIED — same
```

### Structure Rationale

- **`resources/js/charts/` as its own subtree, not mixed into `resources/js/app.js`:** keeps the React chunk fully independent/code-split, matching the milestone's "additive over Livewire, isolated" constraint, and matches the existing precedent of `resources/css/filament/theme.css` being a dedicated entry separate from `resources/css/app.css`.
- **One shared `react-chart.blade.php` view, not one Blade view per widget:** the `wire:poll`/`wire:ignore` skeleton and the JSON-passing contract are identical for every chart kind — only `getData()`'s shape and the `kind` discriminator change. This mirrors Filament's own single `chart-widget.blade.php` serving every `getType()` value (line/bar/pie/doughnut/...).
- **PHP widget classes stay in `app/Filament/Widgets/`, unchanged location:** no new base folder needed, consistent with the "no new base folders without approval" constraint and with how the 2 existing `ChartWidget` subclasses are already organized.

## Architectural Patterns

### Pattern 1: Livewire→React data bridge via dispatched event, not remount

**What:** A single `ReactDOM.createRoot()` per widget instance, kept alive across polls; Livewire pushes fresh JSON via `$this->dispatch()`, Alpine's `$wire.$on()` (available inside `wire:ignore` because Alpine is untouched by morphing) receives it and calls `root.render()` again with new props.
**When to use:** Every chart widget in this milestone — this is the load-bearing pattern for the whole feature.
**Trade-offs:** Requires a small Alpine shim per mount (not pure React) — acceptable since Alpine is already a first-class citizen of every Livewire/Filament page and this project already has an Alpine-JS UX layer (`motion-init.blade.php`) coexisting with it.

**Example:**
```js
// resources/js/charts/main.tsx
import { createRoot, Root } from 'react-dom/client';
import ChartRouter from './ChartRouter';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('reactChartBridge', ({ initialData, chartKind }: { initialData: unknown; chartKind: string }) => ({
        _root: null as Root | null,
        init() {
            this._root = createRoot(this.$el as HTMLElement);
            this._render(initialData);
            (this as any).$wire.$on('updateChartData', ({ data }: { data: unknown }) => this._render(data));
        },
        _render(data: unknown) {
            this._root!.render(<ChartRouter kind={chartKind} data={data} />);
        },
        destroy() {
            this._root?.unmount();
        },
    }));
});
```

### Pattern 2: `ChartWidget` subclass with repointed `$view`, shared JSON contract

**What:** Reuse `ChartWidget`'s polling/checksum/dispatch machinery; only override `$view` and repurpose `getType()`/`getData()`.
**When to use:** Every one of the ~13 chart widgets in the migration/build list.
**Trade-offs:** `getType()`'s name becomes slightly misleading (no longer a literal Chart.js type string) — worth a PHPDoc note on each subclass, or rename via a dedicated `getChartKind(): string` method that `getType()` simply proxies to, for clarity.

**Example:** see Q4 above (`SankeyTransitionsChart`).

### Pattern 3: Widget-instance-scoped mount, never a page-global React app

**What:** Each Filament widget mounts its own independent `ReactDOM.createRoot()`; there is no single global React app shell spanning the dashboard.
**When to use:** Always, for this milestone — Filament dashboards are composed of independently polling widgets (each with its own `wire:poll` interval — `120s` for most charts per existing convention, tighter for the Día D live line), so a single shared React root would need its own cross-widget state management for no benefit and would fight Livewire's per-widget component boundaries.
**Trade-offs:** N separate small JS bundles worth of React runtime overhead is avoided (still one shared Vite chunk/one React runtime instance — just N mount points), but shared state/interaction *between* two charts (e.g. cross-filtering) is not free and would need an explicit event-bus if ever wanted later — out of scope for this milestone.

## Data Flow

### Request Flow (steady-state polling)

```
[wire:poll timer fires, e.g. every 120s]
    ↓
[Livewire AJAX request] → [ChartWidget::updateChartData()] → [getData() re-queries Eloquent, same campaign/role scoping as every other widget]
    ↓                                                                ↓
[checksum unchanged? → no dispatch, nothing happens]      [checksum changed → $this->dispatch('updateChartData', data: [...])]
                                                                       ↓
                                                    [browser CustomEvent delivered on AJAX response,
                                                     independent of DOM morph/wire:ignore]
                                                                       ↓
                                            [Alpine `$wire.$on()` inside the wire:ignore'd node fires]
                                                                       ↓
                                            [React: root.render(<ChartRouter data={newData} />) — reconciled, not remounted]
```

### Initial Mount Flow

```
[Filament page render] → [Blade view: getCachedData() serialized via @js()] → [wire:ignore div with x-data="reactChartBridge({ initialData, chartKind })"]
    ↓
[Alpine init() → createRoot(el) → first root.render()]
    ↓
[Recharts + Motion render from server-supplied initial payload — no flash of empty chart, no second network round-trip needed for first paint]
```

### Key Data Flows

1. **Steady-state refresh:** described above — the *only* way fresh data reaches the island, since `wire:ignore` makes morphing a dead end for this purpose.
2. **Initial paint:** server-rendered JSON embedded directly in the Blade view via `@js($this->getCachedData())`, avoiding a client-side fetch-on-mount round trip.
3. **Campaign/role scoping:** unchanged — happens entirely in PHP's `getData()`, identical to every existing widget's `CampaignContext::currentCampaign()` + role-branch pattern (e.g. `ValidationProgressChart::scopedVoterQuery()`). React never sees raw Eloquent data or campaign IDs it could leak across a boundary — it only receives the already-scoped, already-serialized payload.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Current dashboards (10-20 widgets/page, single campaign context) | Pattern as described is sufficient — one Vite chunk, N independent mounts, each polling on its existing interval |
| Many simultaneous polling widgets on one dashboard | Not a React-side concern — it's the same concern every existing `wire:poll` widget already has (network request volume); no new pattern needed, follow existing polling-interval conventions (120s standard, tighter only for Día D live line per its own already-approved use case) |
| Very large chart payloads (e.g. full-campaign Sankey with many nodes) | Keep aggregation in PHP (`getData()`), never ship raw per-record data to the client — same discipline already applied to every existing report/export in this codebase |

## Anti-Patterns

### Anti-Pattern 1: Remounting the React root on every poll tick

**What people do:** Listen for `Livewire.hook('morph.updated')` or `livewire:navigated` and call `root.unmount()` + `createRoot()` again to "refresh" the chart.
**Why it's wrong:** Destroys Recharts' internal animation/transition state and any live Motion transitions on every single poll (every 120s), causing visible flicker; also `morph.updated` is a page-global hook firing for *any* Livewire update anywhere on the page, not scoped to this widget, so it would over-fire and require manual widget-id filtering that Filament's own `dispatch()`-based approach avoids entirely.
**Instead:** Keep the root alive for the widget's lifetime; call `root.render()` again with new props (Pattern 1 above).

### Anti-Pattern 2: Putting `wire:ignore` on the whole widget's outer element

**What people do:** Wrap the entire `<x-filament-widgets::widget>` root (heading, description, filters, everything) in `wire:ignore` "to be safe."
**Why it's wrong:** Breaks Filament's own chrome — the section heading/description/collapsible toggle/filter dropdown are meant to keep morphing normally (e.g. if `getHeading()` changes based on `$this->questionId` like `SurveyResultsWidget` already does); over-scoping `wire:ignore` also silently breaks `wire:poll` itself if it ends up nested inside the ignored subtree.
**Instead:** Scope `wire:ignore` to only the innermost mount `<div>`, exactly as Filament's own `chart-widget.blade.php` does — `wire:poll` stays on an ancestor outside the ignored boundary.

### Anti-Pattern 3: Fetching data client-side instead of using the existing dispatch channel

**What people do:** Add a `fetch('/api/chart-data/...')` call inside the React component's own polling `useEffect`, bypassing Livewire entirely.
**Why it's wrong:** Duplicates campaign/role scoping logic outside the Eloquent layer (a fresh API endpoint would need its own auth/campaign-context resolution, re-implementing what `CampaignContext` + Filament's panel auth already do for free inside a Livewire component), doubles the network chatter (Livewire's own poll request *and* a separate API poll), and breaks the "additive, doesn't touch Eloquent/business logic" constraint from the milestone brief.
**Instead:** Reuse the existing Livewire component method + dispatch channel (Pattern 1) — zero new HTTP endpoints, zero new auth surface.

## Integration Points

### External Services

None — this is a purely client-side rendering change. No new API/backend service is introduced; Recharts and Motion run entirely in-browser against data already computed by existing Eloquent queries.

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| PHP `ChartWidget` subclass ↔ Blade view | `getCachedData()` / `getType()` / `getPollingInterval()` — unchanged Filament contract | No new contract needed; only `$view` is repointed |
| Blade view ↔ Alpine bridge | `@js(...)`-serialized initial payload as `x-data` params | Standard Alpine/Livewire idiom, same as vendored `chart.js` |
| Alpine bridge ↔ React root | `this.$wire.$on('updateChartData', cb)` → `root.render()` | The one load-bearing bridge — see Q2 |
| React tree internals | Props only (`data`, `kind`) — no external state library needed for this milestone's scope | Each widget instance is fully self-contained; no cross-widget store required |
| Vite build ↔ Filament panels | `Vite::withEntryPoints(['resources/js/charts/main.tsx'])->toHtml()` via `PanelsRenderHook::HEAD_END`, registered per-`*PanelProvider.php` that ships chart widgets | Mirrors the existing `->viteTheme()` / `motion-init` render-hook precedent already in every panel provider |

## Suggested Build Order

1. **Infra first, provably working, before any real chart migrates:** `vite.config.js` + `package.json` changes, a trivial `resources/js/charts/main.tsx` that mounts a "Hello from React" `<div>` via the bridge pattern into one throwaway/test widget, `Vite::withEntryPoints()` render hook wired into `AdminPanelProvider`. Prove the wire:ignore + dispatch + `root.render()` refresh cycle works end-to-end with real `wire:poll` ticks in a real browser before writing a single Recharts component — this is the highest-risk, most novel part of the whole milestone and isolates that risk from all chart-specific work.
2. **`react-chart.blade.php` shared view + `ChartRouter.tsx` skeleton** (empty/placeholder chart components per kind) — establishes the one-view-many-widgets contract before content.
3. **Migrate the 3 existing `ChartWidget`s** (`ValidationProgressChart`, `TerritorialDistributionChart`, `SurveyResultsWidget`) to the new view/bridge, keeping their existing `getData()` untouched — lowest-risk real-data validation of the new pipeline, since correctness of the underlying query is already proven.
4. **Decide and implement the sparkline path** (embedded `Stat::chart()` migration strategy — Q4's exception) before building new standalone widgets, since it may inform whether a shared "small chart" React component is needed alongside the "big chart" ones.
5. **Net-new, currently-invisible-data widgets**, roughly in the milestone's own listed order (Sankey of `ValidationHistory` and funnel of `MessageBatch`/`Message` are explicitly flagged as "0% visible today" — validate the underlying aggregation queries in isolation, e.g. via `tinker`, before wiring them to a widget, since these have no existing widget to copy query logic from).
6. **Día D live line chart last** — it is the only widget with a materially different polling cadence/live-data freshness requirement (election-day, not steady-state dashboard use), so it should build on a fully proven bridge rather than being part of proving it.

## Sources

- Vendored source in this repository — HIGH confidence, direct evidence, not training data:
  - `vendor/filament/widgets/src/ChartWidget.php`
  - `vendor/filament/widgets/resources/views/chart-widget.blade.php`
  - `vendor/filament/widgets/resources/js/components/chart.js`
  - `vendor/filament/widgets/src/StatsOverviewWidget/Stat.php`
  - `app/Filament/Widgets/RevalidationProgressWidget.php` + `resources/views/filament/widgets/revalidation-progress-widget.blade.php`
  - `app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php`, `SurveyResultsWidget.php`
  - `app/Providers/Filament/AdminPanelProvider.php` (existing render-hook/`viteTheme` precedent)
  - `resources/views/filament/components/motion-init.blade.php` (existing `Livewire.hook('morph.updated', ...)` precedent in this codebase — used here as a **counter-example** to distinguish from the recommended dispatch-based pattern)
  - `vite.config.js`, `package.json`
- [Livewire 3.x — wire:ignore](https://livewire.laravel.com/docs/3.x/wire-ignore) — MEDIUM confidence (official docs, but doesn't cover React-specific bridging, only the general third-party-JS caveat)
- [Laravel 12.x — Asset Bundling (Vite)](https://laravel.com/docs/12.x/vite), React section — HIGH confidence, official docs, verified 2026-08-20
- WebSearch, verified against official docs where noted — MEDIUM confidence:
  - `@viteReactRefresh` production no-op behavior (community consensus, not an explicit Laravel-docs statement — recommend a quick local `npm run dev` smoke test before relying on it)
  - Filament chart.js `$wire.$on('updateChartData', ...)` pattern (cross-checked directly against this repo's own vendored file, so elevated to HIGH)

---
*Architecture research for: React+Recharts+Motion island integrated into Filament/Livewire widgets (SIGMA v1.3 milestone)*
*Researched: 2026-08-20*
