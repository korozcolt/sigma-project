# Phase 23: Differentiator Visualizations - Research

**Researched:** 2026-08-21
**Domain:** Recharts native `Sankey`/`Treemap` component wiring, hand-rolled CSS-grid heatmap with a real positioned tooltip, `AreaChart`+`stackId` stacked-area, and a happy-path subset `Funnel` — all on top of the React island infrastructure already built in Phases 20-22
**Confidence:** HIGH (all four new chart kinds' Recharts APIs verified directly against the installed `node_modules/recharts@3.10.1` TypeScript declarations and source, not training data; all reused code paths read directly from the SIGMA codebase)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Happy-path funnel (VIZ-06)**
- **D-01:** Happy-path sequence is the roadmap's 4-stage example: `PENDING_REVIEW → VERIFIED_CENSUS → CONFIRMED → VOTED`. Deliberately skips `VERIFIED_REGISTRADURIA` and `VERIFIED_CALL` — not every voter passes through those two gates, so including them would break strict monotonic narrowing for voters who reach `CONFIRMED` via a different verification path.
- **D-02:** Branch/terminal states (`REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `CENSUS_NOT_FOUND`, `DUPLICATE`, `CORRECTION_REQUIRED`, `DID_NOT_VOTE`) are NOT forced into the funnel shape. They render as a small counter row below the funnel, similar chrome to `RejectionsCountersOverview` — the funnel itself stays 100% happy-path only.
- **D-03:** `DID_NOT_VOTE` goes in the branch counter row alongside the other terminal states, not as a 5th funnel stage — a voter who reached `CONFIRMED` but didn't vote is a branch outcome, not a happy-path completion.
- **D-04:** Scope: campaign-wide, admin-only — matches VIZ-01/02's precedent (`VoterStatusDonutChart`). No leader/coordinator/area_coordinator role-scoping branch, consistent with the roadmap's "Admin sees..." framing for this chart.

**Sankey de transiciones (VIZ-07)**
- **D-05:** Curation strategy is top-N by volume: `GROUP BY previous_status, new_status`, order by count, keep the top N transitions (target ~8-10), collapse the remainder into an "Otros" edge. Adapts automatically as real campaign transition patterns emerge, rather than requiring a hand-picked fixed set.
- **D-06:** `previous_status = null` (initial voter creation) renders as a synthetic "Nuevo" source node feeding into the voter's first real status — shows true entry volume into the validation pipeline instead of being dropped.
- **D-07:** Cycles/back-edges (e.g. `CORRECTION_REQUIRED ↔ PENDING_REVIEW` repeating) are NOT deduped per-voter. Each recorded `(previous_status, new_status)` pair counts every occurrence — a voter bouncing back and forth 3× contributes 3× to that edge's weight. Simplest query (flat `GROUP BY` count, no `DISTINCT`-per-voter logic), and composes naturally with D-05's top-N-by-volume ranking.
- **D-08:** Time range: all campaign history, campaign-scoped, no date filter — matches VIZ-04's message-funnel precedent (Phase 22 D-09) and keeps this milestone's historical-chart pattern consistent.

**Treemap territorial drill-down (VIZ-08)**
- **D-09:** Role-scoping carries over unchanged from `TerritorialDistributionChart`: leader sees only their own registered apoyos, coordinator/area_coordinator see their team's (via `teamCoordinatorUserIds()`), admin/super_admin see the full campaign. This is a direct 1:1 replacement, not a behavior regression.
- **D-10:** The treemap **replaces `TerritorialDistributionChart` in place** — its `getData()`/`getChartKind()` swap to the new nested-tree shape and drill-down kind; same widget slot, same sort position. Matches the roadmap's literal wording ("instead of the current flat top-10 bar list").
- **D-11:** Drill-down UX: click a Departamento tile to zoom into its Municipios; click a Municipio tile to zoom into its Barrios; a breadcrumb trail (e.g. "Todos > Antioquia > Medellín") lets the admin jump back up to any level. One level rendered at a time (nest-mode), per the milestone research's explicit recommendation against a flat all-levels-at-once render.
- **D-12:** No leaf-tile cap — show all barrios in a drilled-into municipio and let Recharts' squarified `Treemap` layout handle it. Consistent with this milestone's full-fidelity precedent (Phase 22 D-01/D-06); relies on drill-down already having narrowed the dataset to one municipio's worth of barrios before rendering, which should keep leaf counts small enough to stay legible.

**Heatmap caller×hora (VIZ-09)**
- **D-13:** Cell metric is contact rate (%): successful contacts ÷ total call attempts, per caller×hour cell. Uses `CallResult::isSuccessfulContact()` (the same canonical "contactado" definition established in Phase 22 D-08) as the numerator. Shows who's effective when, not just who's busy.
- **D-14:** Many-callers strategy: scroll container, show every caller as a row — no top-N truncation. Consistent with this milestone's full-fidelity precedent; the caller axis can grow arbitrarily without hiding low-volume callers.
- **D-15:** Hour axis is business-hours-only (e.g. 7am-9pm), not a full 24-column grid — a full 24h axis would be mostly empty/wasted columns since campaign calling doesn't realistically happen overnight. Exact start/end hour boundary is Claude's discretion.
- **D-16:** A caller×hour cell with zero call attempts renders with a distinct "no data" shade, visually separate from a real 0%-effectiveness cell (attempts made, zero successes). Avoids implying a caller who simply didn't call in that hour "failed every call."
- **D-17:** Tooltip must be a real positioned React tooltip, never the native browser `title=` attribute — explicit roadmap success criterion (VIZ-09 point 4), not just a nice-to-have.

**Stacked-area rejection reasons over time (VIZ-10)**
- **D-18:** "Rejection reason" = `VoterStatus` rejection states only: `REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `CENSUS_NOT_FOUND`, `CORRECTION_REQUIRED` — each its own stacked-area series, driven by `ValidationHistory.new_status` transitions. Deliberately does NOT fold in `RejectionsCountersOverview`'s mixed `CallResult`-based rejection definition. `DUPLICATE` is excluded, consistent with Phase 22 D-04's precedent of keeping it out of the "rechazado" bucket.
- **D-19:** Time granularity: weekly buckets, over the campaign's full history (no bounded window) — matches D-08/Phase-22-D-09's "all history" pattern.
- **D-20:** Each rejection event is bucketed by the `ValidationHistory` row's `created_at` (when the transition to a rejection state happened), not the voter's original registration date.

### Claude's Discretion

- Exact dashboard/resource placement for all 5 new widgets — follow existing precedent: campaign-wide charts (funnel, Sankey, heatmap, stacked-area) on the Admin dashboard alongside `VoterStatusDonutChart`/`RejectionsCountersOverview`; the treemap replaces `TerritorialDistributionChart` in its existing slot per D-10.
- New `ChartRouter.jsx` kind implementations for `funnel` (if not already added in Phase 22 for VIZ-03/04 — confirm and reuse), `sankey`, `treemap`, `heatmap`, `stacked-area` — none of these 4 new kinds exist yet in the router.
- Exact query/service structure for each new aggregation — no existing service class covers Sankey/treemap/heatmap/stacked-area aggregation; new query logic expected in each widget's `getData()`, following the established pattern.
- Business-hours boundary for the heatmap's hour axis (D-15) — pick a reasonable default, optionally grounded in real `VerificationCall.call_date` hour distribution if that's a quick check during research/planning.
- Empty-state and error-state behavior — must follow Phase 20 D-03 (explicit visible error on load/bridge failure) and Phase 21/22's carried-forward pattern; no new decision needed.
- Whether new page-scoped widgets need `AppServiceProvider::PAGE_SCOPED_WIDGETS` registration — apply the Phase 21/22 lesson if any widget attaches via a Page rather than a panel's global `->widgets([...])`.
- Recharts' native `Sankey`/`Treemap` component API specifics (node/link index wiring, `nest`-mode drill-down prop shape) — implementation detail for research/planning, not a product decision.

### Deferred Ideas (OUT OF SCOPE)

- True symmetric ThemeRiver-style streamgraph for rejection reasons — explicitly out of scope (REQUIREMENTS.md v2 VIZ-11), deferred until the standard stacked-area (D-18/D-19) is judged insufficient after real usage; requires custom `d3-shape` work Recharts doesn't provide natively.
- Literal trapezoid funnel of all 12 `VoterStatus` states (superseding VIZ-06's happy-path subset) — explicitly out of scope (REQUIREMENTS.md v2 VIZ-12), deferred until a complete product definition of every branch's funnel semantics exists.
- Bounded date-range filtering for the Sankey (VIZ-07) or heatmap (VIZ-09) — not raised during discussion; both stay unbounded/all-history per D-08 and implicitly D-19's precedent.
- Click-to-drill-through from any of these 5 charts to a filtered voter/call list (beyond the treemap's own zoom-in drill-down, which is a different interaction) — not discussed, consistent with Phase 22 D-02's precedent of no drill-through on any new chart this milestone.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| VIZ-06 | Happy-path funnel of Voter lifecycle (`PENDING_REVIEW→VERIFIED_CENSUS→CONFIRMED→VOTED`), branch/terminal states shown separately | The `funnel` kind already exists in `ChartRouter.jsx`/`FunnelChart.jsx` (built Phase 22, real Recharts trapezoid `Funnel`/`FunnelChart`, not the bar-chart trick) — 100% reusable, zero new frontend code. Backend: single `Voter::whereIn('status', [...])->count()` per stage using cumulative subset sets (see Pattern 1). Branch counter row: new `StatsOverviewWidget` subclass mirroring `RejectionsCountersOverview`'s exact `Stat::make()` chrome — NOT a React/Recharts chart at all. |
| VIZ-07 | Sankey of `ValidationHistory` state transitions, curated top-N + "Otros" | Recharts' native `Sankey` component verified in `node_modules/recharts/types/chart/Sankey.d.ts` — data shape `{nodes: [{name}], links: [{source, target, value}]}` with source/target as **node array indices**, not names. New `sankey` kind needed in `ChartRouter.jsx`. Aggregation query pattern in Pattern 2 below, including the `voter_id → voters.campaign_id` join (no direct `campaign_id` on `ValidationHistory`, same gap already solved for `CallContactabilityFunnelChart`). |
| VIZ-08 | Drill-down treemap of territorial distribution, one level at a time with breadcrumb | Recharts' native `Treemap` component (verified `node_modules/recharts/types/chart/Treemap.d.ts`, source `node_modules/recharts/es6/chart/Treemap.js`) has a **built-in `type="nest"` drill-down mode with self-managed breadcrumb state** (`nestIndex` internal `this.state`, `nestIndexContent` render prop) — corrects `FEATURES.md`'s earlier speculation that nest-mode needs custom state-driven re-render. New `treemap` kind needed. `TerritorialDistributionChart.php`'s exact role-scoping branches read directly (Pattern 3) — D-09's reuse target confirmed byte-for-byte. |
| VIZ-09 | Heatmap of caller×hour effectiveness, real tooltip, many-callers strategy | No native Recharts heatmap (confirmed, `FEATURES.md`'s finding still holds) — CSS-grid-with-opacity hand-rolled component is correct, same technique as `StackedBarChart.jsx`'s `overflow-x-auto` scroll pattern (adapted to vertical scroll for many caller rows). Real tooltip: cannot use Recharts' `<Tooltip>` (no Recharts chart context exists for a plain CSS grid) — must build a manually-positioned tooltip using hover state + `onMouseMove` coordinates, reusing `ChartTooltip.jsx`'s visual styling by feeding it a synthetic `payload` shape. `CallResult::isSuccessfulContact()` confirmed present in `app/Enums/CallResult.php`. New `heatmap` kind needed. |
| VIZ-10 | Stacked-area of rejection reasons over time, weekly buckets | Recharts' `AreaChart`+`Area` with shared `stackId` (verified exported from `recharts` package) — structurally identical to `StackedBarChart.jsx`'s existing pattern, and its data shape (`toSeriesRows()`) is **already the exact shape needed**, zero new adapter code required in `chartjs-adapter.js`. New `stacked-area` kind needed (thin `Area`-instead-of-`Bar` variant of the existing `StackedBarChart.jsx`). Weekly bucketing must be done in PHP (not SQL) to guarantee identical week semantics between MySQL (prod) and sqlite (tests) — see Pitfall 5. |
</phase_requirements>

## Summary

All 5 charts build on infrastructure that is already fully proven: the `wire:ignore`/Alpine bridge, the shared `react-chart.blade.php` view (already generic — it reads `getChartKind()` and needs zero changes for new kinds), and `ChartRouter.jsx`'s kind-dispatch pattern. The `funnel` kind already exists from Phase 22 (`CallContactabilityFunnelChart`/`MessageDeliveryFunnelChart` both use it) and is a real Recharts `Funnel`/`FunnelChart` trapezoid component — VIZ-06 reuses it verbatim with zero new frontend code, only a new PHP widget plus a plain `StatsOverviewWidget` for the branch counter row.

The four genuinely new chart kinds are `sankey`, `treemap`, `heatmap`, `stacked-area`. Direct inspection of the installed `recharts@3.10.1` package (not training data, not MonoCharts' non-representative demo source) confirms: Sankey and Treemap are both real, data-driven Recharts components with documented prop shapes — Sankey needs `{nodes, links}` with index-based source/target wiring; Treemap's `type="nest"` prop is a **complete, self-contained drill-down implementation** (internal breadcrumb state, click-to-zoom, `nestIndexContent` customization hook) that this research confirms exists in the currently-installed version — this measurably de-risks VIZ-08 versus the milestone's earlier assumption that drill-down needed hand-built state management. Heatmap has no Recharts primitive (confirmed) and must be hand-rolled as a CSS grid, exactly as MonoCharts' own source does it — but MonoCharts' native-`title=` tooltip is explicitly insufficient per D-17 and must be replaced with a manually-positioned React tooltip. Stacked-area is the least risky of the four: it is a one-line variant of the already-shipped `StackedBarChart.jsx` pattern, and its data adapter (`toSeriesRows`) needs no changes.

The highest-value non-obvious finding for planning is the Sankey/Treemap campaign-scoping join requirement: `ValidationHistory` (used by both Sankey and stacked-area) has no `campaign_id` column and must be scoped via `voter_id → voters.campaign_id`, identically to the already-solved `CallContactabilityFunnelChart` pattern. The second highest-value finding is that weekly date-bucketing must happen in PHP, not SQL — MySQL and sqlite disagree on ISO week semantics (`YEARWEEK`/`DATE_FORMAT` vs `strftime('%Y-%W')`), which would silently produce different bucket boundaries in tests (sqlite) than production (MySQL), a correctness risk this project's "inaccurate operational numbers are unacceptable" constraint explicitly forbids.

**Primary recommendation:** Reuse the existing `funnel` kind unchanged for VIZ-06; build 4 new `ChartRouter.jsx` kinds (`sankey`, `treemap`, `heatmap`, `stacked-area`) directly against Recharts' real native components (verified shapes below) rather than against MonoCharts' non-representative demo source; bucket weeks in PHP/Carbon, never in raw SQL, to keep MySQL/sqlite test parity.

## Standard Stack

### Core

No new packages required — the phase is scoped entirely within the already-installed React island stack.

| Library | Version (installed) | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `recharts` | `3.10.1` (confirmed in `package.json` and `node_modules`) | `Sankey`, `Treemap`, `AreaChart`/`Area`, `Funnel`/`FunnelChart` — all 4 needed primitives ship in the already-installed version | Verified directly: `node_modules/recharts/types/index.d.ts` exports `Sankey`, `Treemap`, `AreaChart`, `Area`; `node_modules/recharts/types/chart/Treemap.d.ts` confirms `type: 'flat' \| 'nest'` prop with `@since 3.9` node-inset/gap props, both satisfied by 3.10.1 |
| `react` / `react-dom` | `19.x` (confirmed) | Unchanged from Phase 20-22 | No island infra changes needed for this phase |
| `motion` | `13.x` (confirmed, `motion/react` import path) | `ChartCard.jsx`'s existing fade-in wrapper covers all 5 new widgets automatically — no new Motion usage anticipated | Same wrapper every existing chart already uses |

### Supporting

No new supporting libraries. Heatmap tooltip positioning uses plain React state (`useState`/`onMouseMove`) — no positioning library (e.g. Floating UI/Popper) needed given the heatmap is a bounded, scrollable grid, not a free-floating overlay needing collision detection.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Recharts native `Sankey`/`Treemap` | Rebuild against MonoCharts' own `MonoRoundedSankeyChart.tsx`/`MonoRoundedTreemapChart.tsx` source | Rejected — `FEATURES.md`'s critical finding confirms both are hand-coded static demos with zero Recharts usage; not a valid component-usage reference, styling only |
| Hand-rolled CSS-grid heatmap | A dedicated heatmap library (e.g. `nivo`'s `ResponsiveHeatMap`) | Rejected per `STACK.md`'s existing milestone-level decision — a second charting library duplicates dependency surface for one chart; Recharts' `Cell`/manual grid composition is sufficient at this data scale (bounded caller×hour matrix) |
| PHP/Carbon weekly bucketing | MySQL `YEARWEEK()`/sqlite `strftime()` | Rejected as the primary mechanism — see Pitfall 5; DB-side bucketing is fine for MySQL-only production code but breaks dev/test (sqlite) parity, a correctness risk worth avoiding given this project's test suite runs on sqlite by default |

**Installation:**
No installation needed — 0 new npm packages, 0 new composer packages.

**Version verification:**
```bash
npm view recharts version   # confirms latest is still 3.10.x line as of research date; installed 3.10.1 unchanged
```
Recharts' `Sankey`/`Treemap`/`AreaChart` exports and the `Treemap` `type="nest"` prop were verified by reading the installed package's own `.d.ts`/`.js` source directly (`node_modules/recharts/types/chart/{Sankey,Treemap}.d.ts`, `node_modules/recharts/es6/chart/Treemap.js`), not by trusting documentation or training data — this is ground truth for the exact version this project has installed today.

## Architecture Patterns

### Recommended Project Structure

```
resources/js/charts/
├── ChartRouter.jsx                    # MODIFIED — add 4 new kind entries
├── components/
│   ├── FunnelChart.jsx                # UNCHANGED — reused verbatim for VIZ-06
│   ├── SankeyChart.jsx                # NEW — VIZ-07
│   ├── TreemapChart.jsx               # NEW — VIZ-08, nest-mode
│   ├── HeatmapChart.jsx               # NEW — VIZ-09, hand-rolled CSS grid + manual tooltip
│   └── StackedAreaChart.jsx           # NEW — VIZ-10, Area+stackId variant of StackedBarChart.jsx
└── lib/
    └── chartjs-adapter.js             # UNCHANGED for stacked-area (toSeriesRows already fits);
                                        # sankey/treemap need bespoke shaping done server-side in getData(),
                                        # not through the existing labels/datasets adapters

app/Filament/Widgets/
├── VoterHappyPathFunnelChart.php      # NEW — VIZ-06 funnel (kind: funnel, reused)
├── VoterLifecycleBranchCountersOverview.php  # NEW — VIZ-06 branch row (StatsOverviewWidget, NOT a chart kind)
├── ValidationHistorySankeyChart.php   # NEW — VIZ-07 (kind: sankey)
├── TerritorialDistributionChart.php   # MODIFIED IN PLACE — D-10, getData()/getChartKind() swap to treemap
├── CallerHourHeatmapChart.php         # NEW — VIZ-09 (kind: heatmap)
└── RejectionReasonsStackedAreaChart.php  # NEW — VIZ-10 (kind: stacked-area)
```

### Pattern 1: Happy-path funnel — cumulative current-status subset, no new join needed

**What:** `Voter.status` is a single current-status column (not a full traversal log). A "happy path funnel" over 4 linearly-ordered stages is computed as a **cumulative subset count**: stage N's count = number of voters whose *current* status is stage N or any stage *after* it in the happy-path order. This is monotonically non-increasing by construction (each stage's status set is a strict subset of the previous stage's), satisfies D-01's "strict monotonic narrowing" requirement, and correctly includes voters who reached `CONFIRMED`/`VOTED` via the `VERIFIED_REGISTRADURIA`/`VERIFIED_CALL` alternate gates (D-01's explicit reason for excluding those 2 states from the funnel) without needing `ValidationHistory` at all.

**When to use:** VIZ-06 exactly.

**Example:**
```php
// Source: pattern derived from VoterStatusDonutChart.php's Voter::status GROUP BY precedent,
// adapted to cumulative-subset semantics per D-01.
use App\Enums\VoterStatus;

protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();
    if (! $activeCampaign) {
        return ['labels' => [], 'datasets' => [['label' => 'Apoyos', 'data' => []]], 'emptyReason' => 'no_campaign'];
    }

    // D-01: strict 4-stage happy path, deliberately excludes VERIFIED_REGISTRADURIA/VERIFIED_CALL.
    $stages = [
        'Pendiente de Revisión' => [VoterStatus::PENDING_REVIEW, VoterStatus::VERIFIED_CENSUS, VoterStatus::CONFIRMED, VoterStatus::VOTED],
        'Verificado en Censo' => [VoterStatus::VERIFIED_CENSUS, VoterStatus::CONFIRMED, VoterStatus::VOTED],
        'Confirmado' => [VoterStatus::CONFIRMED, VoterStatus::VOTED],
        'Votó' => [VoterStatus::VOTED],
    ];

    $baseQuery = fn () => Voter::query()->where('campaign_id', $activeCampaign->id);

    $counts = [];
    foreach ($stages as $label => $statusSet) {
        $counts[] = $baseQuery()->whereIn('status', array_map(fn ($s) => $s->value, $statusSet))->count();
    }

    if ($counts[0] === 0) {
        return ['labels' => [], 'datasets' => [['label' => 'Apoyos', 'data' => []]], 'emptyReason' => 'no_voters'];
    }

    return ['labels' => array_keys($stages), 'datasets' => [['label' => 'Apoyos', 'data' => $counts]]];
}

protected function getChartKind(): string { return 'funnel'; } // reuses Phase 22's FunnelChart.jsx unchanged
```

**Trade-offs:** A voter currently at `CONFIRMED` who arrived via `VERIFIED_REGISTRADURIA`/`VERIFIED_CALL` is silently counted in the `Verificado en Censo` stage's total even though their status was never literally `VERIFIED_CENSUS`. This is the correct, intended behavior per D-01's own rationale (the funnel measures "reached at least this point in the pipeline," not "this exact status was recorded") — but the widget's `getDescription()` should say so explicitly to avoid confusing an admin who cross-references it against the Sankey's transition-level detail.

### Pattern 2: Sankey — index-based node/link wiring, `campaign_id` join, top-N + "Otros" curation

**What:** Recharts' `Sankey` requires `data={{ nodes: [{name}], links: [{source, target, value}] }}` where `source`/`target` are **integer indices into the `nodes` array**, not status strings — this is a hard Recharts API constraint (confirmed in `node_modules/recharts/types/chart/Sankey.d.ts`'s `LinkDataItem` interface: `source: number; target: number`). The aggregation query must therefore: (1) group `ValidationHistory` by `(previous_status, new_status)` with a `campaign_id` join through `voter_id`, (2) build a stable node-name→index map (including a synthetic `"Nuevo"` node for D-06's null-`previous_status` rows), (3) rank by count and keep top-N (~8-10 per D-05), (4) collapse the remainder into an `"Otros"` bucket.

**When to use:** VIZ-07 exactly.

**Example:**
```php
// Source: pattern derived from CallContactabilityFunnelChart.php's voter_id -> campaign_id join precedent
use App\Models\ValidationHistory;
use Illuminate\Support\Facades\DB;

protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();
    if (! $activeCampaign) {
        return ['nodes' => [], 'links' => [], 'emptyReason' => 'no_campaign'];
    }

    // ValidationHistory has no campaign_id directly — join through voter_id, same as
    // CallContactabilityFunnelChart's VerificationCall pattern.
    $transitions = ValidationHistory::query()
        ->join('voters', 'voters.id', '=', 'validation_history.voter_id')
        ->where('voters.campaign_id', $activeCampaign->id)
        ->select('validation_history.previous_status', 'validation_history.new_status', DB::raw('COUNT(*) as total'))
        ->groupBy('validation_history.previous_status', 'validation_history.new_status')
        ->orderByDesc('total')
        ->get();

    if ($transitions->isEmpty()) {
        return ['nodes' => [], 'links' => [], 'emptyReason' => 'no_voters'];
    }

    // D-05: top-N by volume (target ~8-10), remainder collapsed to "Otros".
    $topN = 8;
    $kept = $transitions->take($topN);
    $excluded = $transitions->slice($topN);

    // D-06: null previous_status renders as synthetic "Nuevo" source node.
    $nodeNames = collect(['Nuevo']) // index 0 reserved for the synthetic entry node
        ->merge($kept->flatMap(fn ($t) => [
            $t->previous_status?->getLabel() ?? 'Nuevo',
            $t->new_status->getLabel(),
        ]))
        ->push('Otros') // always present as the collapse bucket
        ->unique()
        ->values();

    $nodeIndex = $nodeNames->flip(); // name => index

    $links = $kept->map(fn ($t) => [
        'source' => $nodeIndex[$t->previous_status?->getLabel() ?? 'Nuevo'],
        'target' => $nodeIndex[$t->new_status->getLabel()],
        'value' => (int) $t->total,
    ])->values();

    if ($excluded->isNotEmpty()) {
        // Collapse remaining low-volume pairs, grouped by their source node, into "Otros".
        $otrosBySource = $excluded->groupBy(fn ($t) => $t->previous_status?->getLabel() ?? 'Nuevo');
        foreach ($otrosBySource as $sourceLabel => $group) {
            $links->push([
                'source' => $nodeIndex[$sourceLabel],
                'target' => $nodeIndex['Otros'],
                'value' => (int) $group->sum('total'),
            ]);
        }
    }

    return [
        'nodes' => $nodeNames->map(fn ($name) => ['name' => $name])->toArray(),
        'links' => $links->toArray(),
    ];
}

protected function getChartKind(): string { return 'sankey'; }
```

```jsx
// resources/js/charts/components/SankeyChart.jsx — NEW
// Source: node_modules/recharts/types/chart/Sankey.d.ts (verified prop shape)
import { Sankey as RSankey, ResponsiveContainer, Tooltip, Rectangle } from 'recharts';
import ChartTooltip from './ChartTooltip.jsx';

export default function SankeyChart({ data, theme = 'light' }) {
    return (
        <ResponsiveContainer width="100%" height={360}>
            <RSankey
                data={data}
                nodePadding={16}
                nodeWidth={12}
                link={{ stroke: theme === 'dark' ? 'rgba(255,255,255,0.2)' : 'rgba(9,9,11,0.15)' }}
                node={<Rectangle radius={4} fill={theme === 'dark' ? '#fff' : '#111'} />}
            >
                <Tooltip content={<ChartTooltip theme={theme} />} />
            </RSankey>
        </ResponsiveContainer>
    );
}
```

**Trade-offs / open question for planning:** Recharts' `Sankey` layout algorithm assumes a roughly acyclic flow — a self-referencing edge (`source === target`, e.g. `previous_status === new_status`, which should not occur in valid `ValidationHistory` rows but is worth an explicit `WHERE previous_status IS DISTINCT FROM new_status OR previous_status IS NULL`-style guard) can produce a degenerate zero-length link. D-07 explicitly does not dedupe cycles like `CORRECTION_REQUIRED ↔ PENDING_REVIEW`, so the diagram will show real back-edges (two separate directional links between the same node pair) — Recharts renders each `links[]` entry as its own path, so this is visually fine, not a rendering bug, but the planner should confirm with a real-data smoke test (`tinker`) that the top-8-10 pairs from real/seeded data don't produce a degenerate all-self-loop result before wiring the widget.

### Pattern 3: Treemap — native `type="nest"` drill-down, zero custom breadcrumb state needed

**What:** Contrary to `FEATURES.md`'s earlier speculation ("Recharts' `Treemap` supports a `nest`-type drill-down mode... one level at a time with breadcrumb navigation"), direct inspection of the installed `Treemap.js` source (`node_modules/recharts/es6/chart/Treemap.js`) confirms this is **fully self-contained**: the component's own `this.state.nestIndex` array tracks the drill-down path, its internal `onClick`/`handleClick` handlers push/pop that array on tile clicks, and it auto-renders a breadcrumb strip via `renderNestIndex()` using the `nestIndexContent` render prop for customization. The planner does not need to build custom click-to-zoom state — only pass the full nested tree once and set `type="nest"`.

**When to use:** VIZ-08 exactly.

**Example:**
```php
// Source: TerritorialDistributionChart.php's existing role-scoping (D-09, reused verbatim)
// plus a new 3-level nested aggregation (replaces the flat top-10 query per D-10).
protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();
    if (! $activeCampaign) {
        return ['tree' => ['name' => 'root', 'children' => []], 'emptyReason' => 'no_campaign'];
    }

    $user = Auth::user();
    $rows = Voter::query()
        ->select(
            'departments.id as dept_id', 'departments.name as dept_name',
            'municipalities.id as muni_id', 'municipalities.name as muni_name',
            'neighborhoods.id as hood_id', 'neighborhoods.name as hood_name',
            DB::raw('COUNT(*) as total')
        )
        ->join('municipalities', 'voters.municipality_id', '=', 'municipalities.id')
        ->join('departments', 'municipalities.department_id', '=', 'departments.id')
        ->leftJoin('neighborhoods', 'voters.neighborhood_id', '=', 'neighborhoods.id') // D-09 note: nullable
        ->where('voters.campaign_id', $activeCampaign->id)
        ->when($user?->hasRole(UserRole::LEADER->value), fn ($q) => $q->where('voters.registered_by', Auth::id()))
        ->when(
            $user?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value]),
            fn ($q) => $q->whereIn('voters.registered_by', User::whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())->pluck('id'))
        )
        ->groupBy('departments.id', 'departments.name', 'municipalities.id', 'municipalities.name', 'neighborhoods.id', 'neighborhoods.name')
        ->get();

    if ($rows->isEmpty()) {
        return ['tree' => ['name' => 'root', 'children' => []], 'emptyReason' => 'no_voters'];
    }

    // Nest: Departamento -> Municipio -> Barrio (or "Sin barrio" if neighborhood_id is null).
    $tree = $rows->groupBy('dept_name')->map(function ($deptRows, $deptName) {
        $municipios = $deptRows->groupBy('muni_name')->map(function ($muniRows, $muniName) {
            $barrios = $muniRows->map(fn ($r) => ['name' => $r->hood_name ?? 'Sin barrio', 'value' => (int) $r->total]);
            return ['name' => $muniName, 'children' => $barrios->values()->toArray()];
        });
        return ['name' => $deptName, 'children' => $municipios->values()->toArray()];
    })->values()->toArray();

    return ['tree' => $tree];
}

protected function getChartKind(): string { return 'treemap'; }
```

```jsx
// resources/js/charts/components/TreemapChart.jsx — NEW
// Source: node_modules/recharts/types/chart/Treemap.d.ts + node_modules/recharts/es6/chart/Treemap.js
import { Treemap as RTreemap, ResponsiveContainer } from 'recharts';
import { rankedMonochromeFill } from '../lib/palette.js';

export default function TreemapChart({ data, theme = 'light' }) {
    const tree = data?.tree ?? [];
    return (
        <ResponsiveContainer width="100%" height={360}>
            <RTreemap
                data={tree}
                dataKey="value"
                nameKey="name"
                type="nest"           // built-in drill-down: internal state, no custom code needed
                aspectRatio={4 / 3}
                nodeGap={2}
                colorPanel={undefined} // let per-Cell / content render control color if needed
                nestIndexContent={(item, i) => (
                    <span key={i} className="cursor-pointer text-sm opacity-70 hover:opacity-100">
                        {item.name} {'>'}
                    </span>
                )}
            />
        </ResponsiveContainer>
    );
}
```

**Trade-offs:** `neighborhood_id` is nullable on `Voter` (confirmed — `LEFT JOIN neighborhoods`). Silently omitting voters with no assigned neighborhood would undercount, conflicting with this project's "inaccurate operational numbers are unacceptable" constraint and D-12's full-fidelity leaf goal — the `?? 'Sin barrio'` fallback bucket (shown above) must be included, not dropped.

### Pattern 4: Heatmap — hand-rolled CSS grid, manually-positioned tooltip (no Recharts chart context)

**What:** No Recharts primitive exists for this (confirmed absent from `node_modules/recharts/types/index.d.ts`'s exports). Build a CSS grid: rows = callers (scrollable, D-14), columns = business hours (D-15). Each cell's background color is a function of contact-rate %, with an explicit "no data" state distinct from "0% but attempted" (D-16). Because there is no Recharts `<Tooltip>` context around a plain CSS grid, `ChartTooltip.jsx` cannot be used through Recharts' own `<Tooltip content={...} />` wiring — it must be rendered directly with a synthesized `payload` shape and manually positioned via mouse-tracked state, satisfying D-17.

**When to use:** VIZ-09 exactly.

**Example:**
```php
// Source: pattern derived from CallContactabilityFunnelChart's voter_id -> campaign_id join;
// hour bucketing done via a driver-aware SQL expression (BirthdayWidget precedent) since HOUR()
// extraction (unlike week-bucketing) IS semantically identical across MySQL/sqlite.
use App\Enums\CallResult;
use Illuminate\Support\Facades\DB;

protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();
    if (! $activeCampaign) {
        return ['cells' => [], 'callers' => [], 'hours' => [], 'emptyReason' => 'no_campaign'];
    }

    $hourExpr = DB::connection()->getDriverName() === 'sqlite'
        ? "CAST(strftime('%H', verification_calls.call_date) AS INTEGER)"
        : 'HOUR(verification_calls.call_date)';

    $rows = VerificationCall::query()
        ->join('voters', 'voters.id', '=', 'verification_calls.voter_id')
        ->join('users', 'users.id', '=', 'verification_calls.caller_id')
        ->where('voters.campaign_id', $activeCampaign->id)
        ->select(
            'users.id as caller_id', 'users.name as caller_name',
            DB::raw("$hourExpr as hour"),
            DB::raw('COUNT(*) as attempts'),
            DB::raw('SUM(CASE WHEN verification_calls.call_result IN (?, ?, ?) THEN 1 ELSE 0 END) as successes')
        )
        ->addBinding([CallResult::ANSWERED->value, CallResult::CONFIRMED->value, CallResult::CALLBACK_REQUESTED->value], 'select')
        ->groupBy('users.id', 'users.name', DB::raw($hourExpr))
        ->get();

    // D-15: business hours 7am-9pm (default; Claude's discretion, verify against real call_date
    // distribution via tinker if time allows).
    $hours = range(7, 21);

    if ($rows->isEmpty()) {
        return ['cells' => [], 'callers' => [], 'hours' => $hours, 'emptyReason' => 'no_calls'];
    }

    $callers = $rows->pluck('caller_name', 'caller_id')->unique()->toArray();

    $cells = [];
    foreach ($rows as $row) {
        $cells[] = [
            'caller_id' => $row->caller_id,
            'caller_name' => $row->caller_name,
            'hour' => (int) $row->hour,
            'attempts' => (int) $row->attempts,
            'rate' => $row->attempts > 0 ? round($row->successes / $row->attempts * 100, 1) : null, // D-16: null = no data
        ];
    }

    return ['cells' => $cells, 'callers' => $callers, 'hours' => $hours];
}

protected function getChartKind(): string { return 'heatmap'; }
```

```jsx
// resources/js/charts/components/HeatmapChart.jsx — NEW
// Cell color scale + real positioned tooltip (D-17). No Recharts <Tooltip> — synthetic payload
// fed directly into ChartTooltip.jsx's rendering, positioned via onMouseMove state.
import { useState } from 'react';
import ChartTooltip from './ChartTooltip.jsx';

function cellColor(rate, isDark) {
    if (rate === null) return isDark ? 'rgba(255,255,255,0.04)' : 'rgba(9,9,11,0.03)'; // D-16: no-data shade
    const t = Math.max(0, Math.min(1, rate / 100));
    return `rgba(249,115,22,${(0.12 + t * 0.75).toFixed(2)})`; // accent orange ramp, 0% still visibly distinct from no-data
}

export default function HeatmapChart({ data, theme = 'light' }) {
    const { cells = [], callers = {}, hours = [] } = data ?? {};
    const [hover, setHover] = useState(null); // { x, y, payload }
    const byCallerHour = new Map(cells.map((c) => [`${c.caller_id}-${c.hour}`, c]));

    return (
        <div className="relative max-h-[420px] overflow-y-auto overflow-x-auto">
            <div className="grid" style={{ gridTemplateColumns: `140px repeat(${hours.length}, 32px)` }}>
                <div className="sticky left-0 top-0 z-10 bg-inherit" />
                {hours.map((h) => (
                    <div key={h} className="sticky top-0 z-10 bg-inherit text-center text-[10px] opacity-60">{h}h</div>
                ))}
                {Object.entries(callers).map(([callerId, name]) => (
                    <>
                        <div key={`label-${callerId}`} className="sticky left-0 z-10 truncate bg-inherit pr-2 text-xs">{name}</div>
                        {hours.map((h) => {
                            const cell = byCallerHour.get(`${callerId}-${h}`) ?? null;
                            return (
                                <div
                                    key={`${callerId}-${h}`}
                                    className="h-8 w-8 cursor-default"
                                    style={{ backgroundColor: cellColor(cell?.rate ?? null, theme === 'dark') }}
                                    onMouseMove={(e) => setHover({
                                        x: e.clientX, y: e.clientY,
                                        payload: [{ name: 'Efectividad', value: cell ? `${cell.rate}%` : 'Sin datos', color: '#f97316' }],
                                        label: `${name} · ${h}h`,
                                    })}
                                    onMouseLeave={() => setHover(null)}
                                />
                            );
                        })}
                    </>
                ))}
            </div>
            {hover && (
                <div className="pointer-events-none fixed z-50" style={{ left: hover.x + 12, top: hover.y + 12 }}>
                    <ChartTooltip active payload={hover.payload} label={hover.label} theme={theme} />
                </div>
            )}
        </div>
    );
}
```

**Trade-offs:** `position: fixed` + `clientX`/`clientY` is simplest and avoids container-relative offset math, but will not auto-flip near viewport edges — acceptable given the grid itself is already inside a scrollable, bounded widget card, not spanning the full viewport width. If real usage shows edge clipping, a follow-up could add boundary detection; out of scope for this phase per the "no drill-through/no extra polish beyond stated criteria" pattern established in prior phases.

### Anti-Patterns to Avoid

- **Rendering the Sankey/Treemap against MonoCharts' own component source:** confirmed non-representative (hand-coded static demos, zero Recharts data-binding) — always build against the installed `recharts` package's own verified prop shapes, not MonoCharts' `.tsx` files.
- **Bucketing weeks in raw SQL:** MySQL's `YEARWEEK()`/`DATE_FORMAT('%x-%v')` and sqlite's `strftime('%Y-%W', ...)` use different week-numbering rules (ISO Monday-start vs. Sunday-start, year-boundary handling) — would silently diverge between production (MySQL) and the test suite (sqlite), risking a real correctness bug that's hard to detect since both "work," just differently. Bucket in PHP/Carbon instead (Pitfall 5).
- **Forcing the heatmap through Recharts' `<Tooltip>` component:** it only works inside an actual Recharts chart's coordinate/context system — a plain CSS grid has no such context, so `<Tooltip>` will silently do nothing. Must manually track hover state and render the tooltip content component directly.
- **Dropping voters with `neighborhood_id = null` from the treemap:** silently shrinks the total count shown vs. the campaign's real total, violating the "accurate operational numbers" constraint — always bucket into an explicit "Sin barrio" leaf.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Trapezoid funnel rendering | A custom SVG funnel shape | Recharts' native `Funnel`/`FunnelChart` (already wired as the `funnel` kind since Phase 22) | Already built, tested, and in production use by 2 widgets — VIZ-06 needs zero new frontend code |
| Sankey flow diagram layout | Manual bezier-curve/coordinate math (MonoCharts' own approach for its 2-node demo) | Recharts' native `Sankey` (depth-assignment + collision-resolution algorithm, ships in the installed package) | Confirmed real, data-driven, and already installed — building a custom layout algorithm would be substantially more work for a worse result |
| Treemap squarified layout + drill-down breadcrumb state | Custom `useState`-driven zoom/breadcrumb management | Recharts' native `Treemap` `type="nest"` (self-contained internal state, verified in source) | This research's key finding — the drill-down mechanics the milestone brief worried about are already built into the installed library version |
| Weekly date-bucketing | Raw `YEARWEEK()`/`strftime()` SQL | `Carbon::parse($row->created_at)->startOfWeek()->format('Y-m-d')` in a PHP collection pipeline over already-fetched rows | Guarantees identical bucket boundaries in MySQL (prod) and sqlite (tests) — a raw-SQL approach would need a driver-switch (`BirthdayWidget` precedent) AND still risk subtly different week-numbering semantics between the two branches, which a driver-switch alone doesn't fix |
| Positioned tooltip on non-Recharts content | A generic tooltip/positioning library (Floating UI, Popper, Tippy) | Manual `onMouseMove` + `useState` + `position: fixed`, reusing `ChartTooltip.jsx`'s existing visual rendering | The heatmap's tooltip target is a bounded, already-scrollable grid inside a widget card — no viewport-collision-avoidance complexity that would justify a new dependency |

**Key insight:** Every one of the 4 genuinely new chart kinds has a real, already-installed Recharts primitive except the heatmap — the actual net-new engineering surface for this phase is (1) 4 new PHP aggregation queries, (2) 4 new thin `ChartRouter.jsx` component wrappers around those primitives, and (3) one hand-rolled CSS-grid + manual-tooltip component for the heatmap specifically. Nothing in this phase requires a new npm/composer dependency.

## Common Pitfalls

### Pitfall 1: Sankey `source`/`target` must be array indices, not status name strings

**What goes wrong:** Recharts' `Sankey` `links[].source`/`links[].target` are `number` (verified in `LinkDataItem` interface), not the status label/enum value. Passing a string (e.g. `'VERIFIED_CENSUS'`) instead of that node's index in the `nodes` array either silently renders nothing or throws inside Recharts' internal `computeData()`.
**Why it happens:** Every other Recharts chart in this codebase so far (`Pie`, `Bar`, `Funnel`) uses name-based `dataKey`/`nameKey` lookups against flat row objects — Sankey's index-based wiring is a structurally different contract, easy to reach for the familiar pattern by habit.
**How to avoid:** Build the node list and a `name => index` map in PHP (Pattern 2 above) before building `links`; never reference nodes by name inside `links[]`.
**Warning signs:** Sankey renders an empty/blank SVG with no console error, or React DevTools shows the `Sankey` component receiving `links` but zero rendered `<path>` elements.

### Pitfall 2: Weekly bucketing SQL diverges between MySQL (prod) and sqlite (tests)

**What goes wrong:** `YEARWEEK(created_at, 3)` (MySQL, ISO week) and `strftime('%Y-%W', created_at)` (sqlite, non-ISO, Sunday-start) will bucket dates near year/week boundaries differently — a test asserting "3 rejections in week X" against sqlite fixture data could pass in CI but produce a visibly different week grouping against real MySQL production data, exactly the kind of silent divergence this project's `BirthdayWidget` precedent already had to fix once (for `DAY()` vs `strftime('%d', ...)`, a simpler case with no semantic ambiguity).
**Why it happens:** `phpunit.xml` forces `DB_CONNECTION=sqlite`/`:memory:` for all test runs (confirmed) while production runs MySQL (`.env`: `DB_CONNECTION=mysql`, confirmed) — this project's own established driver-switch pattern (`BirthdayWidget`) handles *syntax* differences but week-numbering is a *semantic* difference a syntax swap alone doesn't fully resolve.
**How to avoid:** Fetch `created_at`/`new_status` rows unaggregated (or aggregated only by day, which IS driver-portable), then bucket into weeks using `Carbon::parse($date)->startOfWeek()->format('Y-m-d')` in a PHP `Collection::groupBy()` — guarantees identical semantics regardless of DB driver.
**Warning signs:** A Feature/Browser test asserting specific weekly bucket counts passes locally (sqlite) but the same scenario manually verified against the MySQL-backed local dev DB (`sigma_betha_backup`, per this project's established local-testing convention) shows data in a different week.
**Phase to address:** This phase, in `RejectionReasonsStackedAreaChart::getData()` — get the bucketing right in the widget's first implementation rather than retrofitting after a test-vs-prod mismatch is discovered.

### Pitfall 3: `neighborhood_id` nullability silently undercounts the treemap

**What goes wrong:** `Voter.neighborhood_id` is nullable (confirmed — `LEFT JOIN neighborhoods` is required, not an inner join). A naive `INNER JOIN neighborhoods` (matching `TerritorialDistributionChart`'s existing pattern, which never joins `neighborhoods` at all today) would drop every voter without an assigned neighborhood from the treemap entirely, understating each municipio's total versus the campaign's real total.
**Why it happens:** The existing `TerritorialDistributionChart` only aggregates at the municipality level and never touches `neighborhoods` — there's no existing precedent in this codebase for the 3rd nesting level to copy from, so the join-type choice is a genuinely new decision this phase introduces.
**How to avoid:** Use `LEFT JOIN`, and bucket null-neighborhood voters into an explicit `"Sin barrio"` leaf (shown in Pattern 3's example) rather than dropping them.
**Warning signs:** Summing all leaf `value`s in a drilled-into municipio's treemap doesn't match that municipio's total from the (unmodified) donut/other widgets — a data-integrity smoke test worth adding.

### Pitfall 4: Recharts' `<Tooltip>` does nothing outside a Recharts chart context

**What goes wrong:** If the heatmap is implemented with the familiar `<Tooltip content={<ChartTooltip />} />` pattern copied from every other chart component in this codebase, it silently renders nothing — Recharts' `Tooltip` reads position/active-payload state from its parent chart's internal Redux-backed context (confirmed: Recharts 3.x's internal state management runs on `@reduxjs/toolkit`, per `STACK.md`), which only exists inside an actual `<LineChart>`/`<BarChart>`/etc. wrapper, not around a plain `<div>` grid.
**Why it happens:** Every other chart component in `resources/js/charts/components/` uses this exact `<Tooltip content={...} />` pattern successfully, making it the obvious thing to copy without realizing the heatmap has no Recharts chart wrapper to provide that context.
**How to avoid:** Build the heatmap's tooltip manually via hover state (Pattern 4 above) — never reach for Recharts' `<Tooltip>` component for the heatmap specifically.
**Warning signs:** Hovering a cell does nothing, no console error, no visible tooltip — easy to mistake for a CSS z-index/visibility bug rather than the actual root cause (missing chart context).

### Pitfall 5: Sankey self-loop or degenerate top-N set from sparse seed/test data

**What goes wrong:** With a small number of seeded `ValidationHistory` rows (e.g. in a Browser test using `Voter::factory()`), the top-8 `(previous_status, new_status)` pairs by volume could all collapse to 1-2 real transitions plus mostly-empty "Otros" buckets, or — if a data seeding bug ever produces a row where `previous_status === new_status` — a zero-length self-referencing link that Recharts may render as an invisible/degenerate path.
**Why it happens:** Real campaign data will naturally have transition diversity; small test fixtures won't, and no explicit guard currently exists against `previous_status === new_status` rows being written to `ValidationHistory` in the first place (out of this phase's scope to fix the writer, but the reader/aggregator should be defensive).
**How to avoid:** Add a `WHERE previous_status IS NULL OR previous_status != new_status`-style guard in the aggregation query (belt-and-suspenders, doesn't change legitimate data); for Browser/Feature tests, seed at least 3-4 distinct `(previous_status, new_status)` pairs so the Sankey renders a non-degenerate diagram worth asserting against.
**Warning signs:** A Browser test's `assertVisible('[data-chart-kind="sankey"]')` passes but a follow-up screenshot/manual check shows an empty or single-node diagram.

## Code Examples

See Patterns 1-4 above — all code examples are inline with their respective pattern for direct traceability between the Recharts API constraint and the PHP query producing data in that shape.

### Stacked-area — thin variant of the existing StackedBarChart.jsx (zero new adapter code)

```jsx
// resources/js/charts/components/StackedAreaChart.jsx — NEW
// Source: structurally identical to StackedBarChart.jsx (Phase 22), Area instead of Bar.
import { Area, AreaChart as RAreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { toSeriesRows } from '../lib/chartjs-adapter.js';
import { rankedMonochromeFill } from '../lib/palette.js';
import ChartTooltip from './ChartTooltip.jsx';

export default function StackedAreaChart({ data, theme = 'light' }) {
    const rows = toSeriesRows(data ?? {}); // same adapter StackedBarChart.jsx already uses — no changes needed
    const seriesKeys = (data?.datasets ?? []).map((ds) => ds.label);

    return (
        <ResponsiveContainer width="100%" height={280}>
            <RAreaChart data={rows} margin={{ top: 12, right: 12, left: -22, bottom: 0 }}>
                <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} />
                <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip content={<ChartTooltip theme={theme} />} />
                {seriesKeys.map((key, i) => (
                    <Area
                        key={key}
                        type="monotone"
                        dataKey={key}
                        stackId="rejections" // shared stackId = true stacked area, same mechanism as StackedBarChart's stackId="apoyos"
                        stroke="none"
                        fill={rankedMonochromeFill(i, seriesKeys.length, { isDark: theme === 'dark' })}
                    />
                ))}
            </RAreaChart>
        </ResponsiveContainer>
    );
}
```

```php
// RejectionReasonsStackedAreaChart.php getData() — weekly bucketing done in PHP (Pitfall 2)
use App\Enums\VoterStatus;
use App\Models\ValidationHistory;
use Illuminate\Support\Carbon;

protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();
    if (! $activeCampaign) {
        return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_campaign'];
    }

    // D-18: VoterStatus rejection states only, driven by ValidationHistory.new_status.
    $rejectionStatuses = [
        VoterStatus::REJECTED_CENSUS, VoterStatus::REJECTED_OUT_OF_SCOPE,
        VoterStatus::CENSUS_NOT_FOUND, VoterStatus::CORRECTION_REQUIRED,
    ];

    $rows = ValidationHistory::query()
        ->join('voters', 'voters.id', '=', 'validation_history.voter_id')
        ->where('voters.campaign_id', $activeCampaign->id)
        ->whereIn('validation_history.new_status', array_map(fn ($s) => $s->value, $rejectionStatuses))
        ->select('validation_history.new_status', 'validation_history.created_at')
        ->get();

    if ($rows->isEmpty()) {
        return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_voters'];
    }

    // D-19/D-20: bucket by created_at into weeks, in PHP — driver-portable (Pitfall 2).
    $byWeek = $rows->groupBy(fn ($r) => Carbon::parse($r->created_at)->startOfWeek()->format('Y-m-d'));
    $weekLabels = $byWeek->keys()->sort()->values();

    $datasets = collect($rejectionStatuses)->map(fn ($status) => [
        'label' => $status->getLabel(),
        'data' => $weekLabels->map(
            fn ($week) => $byWeek[$week]->where('new_status', $status)->count()
        )->toArray(),
    ])->toArray();

    return ['labels' => $weekLabels->toArray(), 'datasets' => $datasets];
}

protected function getChartKind(): string { return 'stacked-area'; }
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Assumed Treemap `nest`-mode needs custom drill-down state (per milestone `FEATURES.md`) | Confirmed Recharts 3.9+ (installed: 3.10.1) ships a fully self-contained `type="nest"` with internal breadcrumb state | Verified this research session against the actually-installed package source | Removes a whole category of custom React state-management work the milestone brief anticipated for VIZ-08 — plan the treemap component as a thin wrapper, not a stateful drill-down implementation |

**Deprecated/outdated:** None — this is a purely additive phase on top of an already-current stack (`recharts@3.10.1`, confirmed still the latest 3.x line).

## Open Questions

1. **Sankey "Otros" collapse: single bucket vs. per-source-node collapse**
   - What we know: D-05 says "collapse the remainder into an 'Otros' edge" (singular framing); Pattern 2 above implements a per-source-node collapse (multiple edges into one shared "Otros" target node, grouped by each excluded pair's source) to preserve directional information.
   - What's unclear: Whether the product intent is a single flattened "Otros" edge (losing all source-node distinction for excluded pairs) or the richer per-source-node version implemented in Pattern 2.
   - Recommendation: Build the per-source-node version (Pattern 2) — it's a strict superset of information, degrades gracefully to a single node if only one source ever falls outside top-N in real data, and better matches D-05's "adapts automatically as real campaign transition patterns emerge" spirit. Flag for a quick confirmation during planning/UI-spec, not a blocker.

2. **Heatmap business-hours boundary (D-15, explicitly Claude's discretion)**
   - What we know: D-15 defaults to "e.g. 7am-9pm" and explicitly allows checking real `VerificationCall.call_date` hour distribution.
   - What's unclear: Whether local/seed data has enough real call volume to make that check meaningful before production data exists.
   - Recommendation: Default to 7-21 (15 columns) as implemented in Pattern 4's example; the planner can add a quick `tinker` check (`VerificationCall::selectRaw('HOUR(call_date) as h, COUNT(*) as c')->groupBy('h')->orderByDesc('c')->get()`) against `sigma_betha_backup` if real distribution data exists, but this is a low-risk default either way since D-15 doesn't require precision, just "not wastefully full-24h."

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| `recharts` (Sankey/Treemap/AreaChart/Area exports) | VIZ-07/08/10 | ✓ | 3.10.1 (confirmed installed, verified via direct `.d.ts`/`.js` source inspection) | — |
| `react`/`react-dom`/`motion` | All 5 widgets (via existing island infra) | ✓ | 19.x / 13.x (confirmed, unchanged from Phase 20-22) | — |
| MySQL (production) | Weekly-bucket/hour-extraction correctness | ✓ | `.env` confirms `DB_CONNECTION=mysql` | — |
| sqlite (test suite) | Test execution | ✓ | `phpunit.xml` forces `sqlite`/`:memory:` | — |
| Node.js / npm (build tooling) | Vite build of new `.jsx` files | ✓ | Node v22.22.3, npm 10.9.8 (confirmed via `node -v`/`npm -v`) | — |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:** None — every dependency this phase needs is already installed and verified working.

## Sources

### Primary (HIGH confidence)

- `node_modules/recharts/types/chart/Sankey.d.ts` (installed `recharts@3.10.1`) — `SankeyData`/`LinkDataItem`/`SankeyProps` exact shapes, read directly
- `node_modules/recharts/types/chart/Treemap.d.ts` and `node_modules/recharts/es6/chart/Treemap.js` (installed `recharts@3.10.1`) — `type: 'flat' | 'nest'`, `nestIndexContent`, internal `nestIndex` state confirmed by reading the actual component source, not documentation
- `node_modules/recharts/types/index.d.ts` — confirms `Area`/`AreaChart`/`Sankey`/`Treemap` are all exported from the installed package
- SIGMA codebase, read directly: `app/Filament/Widgets/{TerritorialDistributionChart,RejectionsCountersOverview,CallContactabilityFunnelChart,MessageDeliveryFunnelChart,VoterStatusDonutChart}.php`, `app/Models/{ValidationHistory,VerificationCall,Municipality,Neighborhood,Department,Voter}.php`, `app/Enums/{VoterStatus,CallResult}.php`, `resources/js/charts/{ChartRouter.jsx,components/*.jsx,lib/*.js}`, `resources/views/filament/widgets/react-chart.blade.php`, `app/Providers/{AppServiceProvider,Filament/AdminPanelProvider}.php`, `phpunit.xml`, `.env`, `package.json`
- `.planning/research/{FEATURES,ARCHITECTURE,PITFALLS,STACK}.md` — milestone-level research, read in full, cross-checked and in one case (Treemap nest-mode) corrected against direct source inspection
- `tests/Browser/CallContactabilityFunnelChartTest.php`, `tests/Feature/PageScopedWidgetRegistrationTest.php` — established testing/registration conventions, read directly

### Secondary (MEDIUM confidence)

None — every claim in this document was verified against either the installed package source or this project's own codebase directly.

### Tertiary (LOW confidence)

None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies, all 4 needed Recharts primitives confirmed present in the installed version by reading source directly
- Architecture: HIGH — reuses the fully-proven Phase 20-22 bridge/kind-router pattern unchanged; `react-chart.blade.php` needs zero modification for new kinds
- Pitfalls: HIGH for Sankey index-wiring, Treemap null-neighborhood, Recharts-Tooltip-context — all derived from direct source/schema inspection; MEDIUM for the exact Sankey "Otros" collapse shape (Open Question 1) — flagged explicitly, not asserted as fact

**Research date:** 2026-08-21
**Valid until:** 30 days (stable dependency versions, no fast-moving external API surface — Recharts 3.x is the current major, no pending breaking changes anticipated in this window)

---
*Phase: 23-differentiator-visualizations*
*Researched: 2026-08-21*
