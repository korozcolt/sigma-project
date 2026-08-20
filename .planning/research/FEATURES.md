# Feature Research

**Domain:** MonoCharts-style rich visualizations (funnel, sankey, treemap, heatmap, donut, stacked-bar, stream, gauge, live line) for SIGMA's Filament admin panel, built on Recharts as a React island over Livewire
**Researched:** 2026-08-20
**Confidence:** HIGH (Recharts + MonoCharts source directly verified via GitHub API/raw source; a couple of layout-algorithm details sourced from a secondary community wiki, called out below)

## How This Research Was Done

- Fetched `package.json` from `github.com/Subhan-code/Monocharts` (main branch, commit as of 2026-08-20) — confirms the repo runs **React 19 + Recharts ^3.10.1**, no D3/visx/nivo dependency.
- Listed and read the actual source of all 29 files in `src/components/mono-charts/`, downloading and reading in full: `MonoRoundedFunnelChart.tsx`, `MonoRoundedSankeyChart.tsx`, `MonoRoundedTreemapChart.tsx`, `MonoRoundedHeatmapChart.tsx`, `MonoRoundedGaugeArc.tsx`, `MonoRoundedDonutChart.tsx`, `MonoRoundedStackedBarChart.tsx`, `MonoRoundedStreamChart.tsx`, `MonoRoundedLineChart.tsx`, `MonoActivityHeatmap.tsx`, `MonoRoundedPyramidChart.tsx`, plus the shared tooltip primitive `dither-charts/lib/recharts-tooltip.tsx`.
- Cross-checked official Recharts docs (`recharts.github.io/en-US/api/FunnelChart/`) and a secondary technical reference (DeepWiki's Recharts specialized-charts page) for the native `Funnel`/`Treemap`/`Sankey` component data shapes, since MonoCharts itself does **not** use those native components for its own funnel/sankey/treemap (see finding below — this is the single most important discovery of this research).
- Read SIGMA's actual `VoterStatus` enum (12 cases) and `ValidationHistory` model (`previous_status` → `new_status` transition log) to ground the Sankey/funnel complexity assessment in real schema, not assumption.

## Critical Finding: MonoCharts' own funnel/sankey/treemap are NOT built on Recharts' native Funnel/Sankey/Treemap components

This changes the "reuse vs. build" calculus for 3 of the 11 target charts. Despite the milestone brief listing `MonoRoundedFunnelChart`, `MonoRoundedSankeyChart`, `MonoRoundedTreemapChart` as "already built," reading their actual source shows:

| Component | What it actually renders | Recharts primitive used |
|---|---|---|
| `MonoRoundedFunnelChart` | A horizontal `<BarChart layout="vertical">` with rounded bar caps — **not** Recharts' `Funnel`/`FunnelChart` (trapezoid) component at all | `BarChart`/`Bar` (standard) |
| `MonoRoundedSankeyChart` | Two hardcoded `<div>` "nodes" + one hand-drawn `<svg><path>` bezier curve with fixed coordinates for exactly 2 sources → 1 sink — **zero** Recharts import, no layout algorithm, static demo shape only | None — pure hand-coded SVG, not data-driven |
| `MonoRoundedTreemapChart` | A CSS Grid (`grid-cols-3 grid-rows-3`) with 4 hardcoded `col-span`/`row-span` Tailwind classes mapped 1:1 to 4 fixed demo values — **not** Recharts' `Treemap` (squarified algorithm) component | None — pure hand-coded CSS grid, not data-driven |

Recharts itself **does** ship real `Funnel`/`FunnelChart`, `Sankey`, and `Treemap` components with genuine data-driven layout algorithms (squarify for Treemap, depth-assignment + collision-resolution for Sankey, stacked-trapezoid for Funnel) — MonoCharts' authors evidently chose not to use them, likely because the native components don't produce the monochrome "rounded pill" aesthetic without heavy custom shape overrides, and a hardcoded demo is enough for a landing-page showcase.

**Implication for SIGMA:** for these 3 charts, MonoCharts' source is reusable **only as a visual/styling reference** (colors, radii, header/footer chrome, dark/light theming pattern), not as a data-shape or component-usage reference. The actual implementation must go around MonoCharts and use Recharts' real `Funnel`, `Sankey`, and `Treemap` components (or, for funnel specifically, MonoCharts' own bar-chart trick is a legitimate and *simpler* alternative — see below).

## Feature Landscape

### Table Stakes (Direct Recharts Primitives — Low/Medium Complexity)

Charts where Recharts has a native, data-driven component (or a trivial variant of one already proven in SIGMA's Chart.js widgets), and MonoCharts' source is directly reusable for both data shape and styling.

| Feature | Recharts primitive | MonoCharts source reusability | Complexity | Notes |
|---|---|---|---|---|
| Migrate 3 existing `ChartWidget`s (line, bar, bar/pie) + 3 sparklines | `LineChart`, `BarChart`, `PieChart` | Directly reusable (`MonoRoundedLineChart`, `MonoRoundedStackedBarChart`/plain bar variant, `MonoRoundedDonutChart`, `MonoRoundedSparklineChart`) | LOW | Pure re-skin — existing PHP queries/data already work with Chart.js today, only the rendering layer changes. Zero new data-shape work. |
| Donut — Voter state distribution (12 `VoterStatus` states) | `PieChart` + `Pie` (`innerRadius`/`outerRadius`, `paddingAngle`, `cornerRadius`) | Directly reusable, `MonoRoundedDonutChart.tsx` is a complete working example incl. hover state + center callout | LOW | Data shape: `[{name, value}]` — one `GROUP BY status` query. 12 segments is more than the 4-segment demo; needs a palette strategy (12 monochrome shades or grouping small segments into "Otros"). |
| Stacked-bar — team comparison by coordinator | `BarChart` + multiple `Bar` with shared `stackId` | Directly reusable, `MonoRoundedStackedBarChart.tsx` is a complete working example | LOW–MEDIUM | Data shape: one row per coordinator, one numeric key per status/category being stacked. Real complexity is data-side (pivoting per-coordinator counts into wide rows), not chart-side. |
| Gauge — SCALE survey average / target | `PieChart` + `Pie` with `startAngle=210`/`endAngle=-30` (semi-circle trick) | Directly reusable, `MonoRoundedGaugeArc.tsx` is a complete working example | LOW | Not a "real" gauge primitive — it's a 2-segment Pie (`value`, `100-value`) drawn as an arc. Works fine for a single scalar (avg SCALE score / target). |
| Histogram — SCALE survey response distribution | `BarChart` + `Bar` (categorical x-axis = bucket) | No MonoCharts histogram file exists by that name, but it's structurally identical to `MonoRoundedStackedBarChart`/plain bar variant | LOW–MEDIUM | Data shape is standard bar-chart shape once responses are bucketed server-side (PHP does the binning; Recharts just renders bars). |
| Funnel — Voter lifecycle (12 states), call contactability by attempt, message delivery (sent→delivered→read→clicked) | Either (a) Recharts native `Funnel`/`FunnelChart` (`data=[{name, value}]`, trapezoid rendering), or (b) MonoCharts' own horizontal-`BarChart` trick | (b) directly reusable as-is; (a) needs to be built fresh against Recharts docs (MonoCharts doesn't demo it) | LOW (option b) / MEDIUM (option a) | **Not all 3 of these are the same shape.** Contactability-by-attempt and message-delivery are naturally monotonically decreasing (classic funnel semantics: attempt 1 ≥ attempt 2 ≥ attempt 3; sent ≥ delivered ≥ read ≥ clicked) — a real trapezoid `Funnel` looks correct and is worth the extra effort. The 12-state voter-lifecycle "funnel" is **not** naturally monotonic (branches like `REJECTED_CENSUS`, `DUPLICATE` are terminal side-branches, not a strict narrowing pipeline) — forcing it into a real Funnel shape will misrepresent the data; MonoCharts' bar-chart-as-funnel approach (a horizontal ranked bar list) is actually the *more honest* representation for this one case, or the 12 states should be reduced to a defined "happy path" subset before charting. |

### Differentiators (Genuinely New Insight — Medium/High Complexity)

Charts flagged in the milestone brief as exposing "today 100% invisible" data. These are where MonoCharts' source is least directly reusable (styling only, not data-shape/algorithm) and where Recharts either has no native primitive or the primitive needs real data-modeling work to feed correctly.

| Feature | Recharts support | MonoCharts source reusability | Complexity | Notes |
|---|---|---|---|---|
| Sankey — `ValidationHistory` state transitions (`previous_status` → `new_status`) | **Native**: `Sankey` component, data shape `{ nodes: [{name}], links: [{source, target, value}] }` (source/target are node indices) — genuinely maps 1:1 onto SIGMA's schema | Styling only — `MonoRoundedSankeyChart.tsx` is a static 2-node hardcoded demo, not data-driven at all; must be rebuilt from scratch against Recharts' real `Sankey` | HIGH | Real complexity is on both sides: (1) data — aggregate `ValidationHistory` by `GROUP BY previous_status, new_status` into node/link counts, decide how to handle `previous_status = null` (initial creation) as a synthetic "Nuevo" source node; (2) rendering — Recharts' Sankey layout assumes a roughly acyclic, connected flow; SIGMA's real transition graph has back-edges (e.g. `CORRECTION_REQUIRED` → `PENDING_REVIEW` → `CORRECTION_REQUIRED` again) and low-volume edges that will clutter a 12-node diagram. Needs an explicit strategy: either dedupe/collapse cyclical edges, cap to top-N transitions, or accept a layout that shows repeated node appearances is not directly supported — plan for a filtered/curated edge set, not a raw dump of every `(previous_status, new_status)` pair ever recorded. |
| Treemap — territorial hierarchy Departamento → Municipio → Barrio | **Native**: `Treemap` component, data shape is a nested tree (`{name, children: [...]}` with `value` on leaves), squarified layout algorithm | Styling only — `MonoRoundedTreemapChart.tsx` is a hardcoded 4-tile CSS grid, not an algorithmic treemap; must be rebuilt from scratch against Recharts' real `Treemap` | MEDIUM–HIGH | Data-side: a real 3-level `GROUP BY department, municipality, neighborhood` aggregation, shaped into nested JSON — moderate but mechanical work. Rendering-side risk: a real campaign can have dozens of municipios and hundreds of barrios; a flat squarified treemap with that many leaves produces unreadably thin slivers. Recharts' `Treemap` supports a `nest`-type drill-down mode (one level at a time with breadcrumb navigation) specifically for this — strongly recommended over trying to render all 3 levels flat at once. |
| Heatmap — `VerificationCall` caller × hour effectiveness | **No native Recharts heatmap component.** Must be hand-rolled (which is exactly what MonoCharts itself does) | Directly reusable pattern — `MonoRoundedHeatmapChart.tsx`'s CSS-grid-with-opacity approach is a legitimate, production-viable technique for small fixed-dimension 2D categorical data (its own demo is a 7×5 grid) | MEDIUM | This is the one "hard" chart where MonoCharts' actual implementation *is* the right approach (not a Recharts gap to work around, just a genuine gap in Recharts itself — confirmed no `Heatmap` export exists). Two real gaps versus the demo: (1) MonoCharts' tooltip is the native browser `title` attribute, not an interactive React tooltip — needs upgrading to a positioned custom tooltip component (reuse the existing `DitherChartTooltipContent` pattern, adapted for a synthetic `payload`) for a product-grade feel; (2) "caller" is an unbounded dimension (could be dozens of call-center agents) unlike the demo's fixed 5 days — needs a strategy for many rows (scroll container, pagination, or cap to top-N callers by volume). |
| Stream / stacked-area — rejection reasons over time | **Partially native.** Recharts `AreaChart` + `Area` with shared `stackId` natively supports true **stacked area** (zero baseline, cumulative). It does **not** natively support a symmetric **ThemeRiver-style streamgraph** (wiggle/silhouette baseline) | Misleading — `MonoRoundedStreamChart.tsx` is labeled "Stream" but its two `<Area>` elements have **no `stackId`** set at all; it just overlays two independent gradient-filled areas. It is not a real stream/stacked-area implementation despite the name. | LOW (stacked-area) / HIGH (true streamgraph) | Recommend treating this as a standard **stacked area chart** (`stackId` set, like `MonoRoundedStackedBarChart`'s pattern but with `Area` instead of `Bar`) rather than chasing a true symmetric streamgraph — the latter requires a custom `d3-shape` `stackOffsetSilhouette`-style baseline transform that Recharts does not expose as a prop, adding real complexity for a purely aesthetic (not clarity) gain. A standard stacked area of rejection-reason counts over time communicates the trend just as well for an ops dashboard. |
| Live-polling line chart — Día D voting (`VoteRecord.voted_at`) | **Native for the chart itself** (`LineChart`/`Line`, identical shape to the existing `ValidationProgressChart`). **Zero support for polling** — Recharts has no data-fetching/polling concept at all | Chart rendering is directly reusable (`MonoRoundedLineChart.tsx`); the "live" part has no MonoCharts precedent to reuse (the repo is a static demo site, nothing in it polls a backend) | MEDIUM | This is a two-part feature the milestone brief bundles together: (1) the chart itself is table-stakes-easy (same `LineChart` as the already-migrated `ValidationProgressChart`); (2) "live" is a pure application-architecture problem — the React island needs its own polling loop (interval-based `fetch` against a Laravel endpoint, decoupled from Livewire's `wire:poll` since this lives outside the Livewire component tree) hitting a campaign-scoped, ideally cached/pre-aggregated endpoint so repeated polling during Día D peak load doesn't run an expensive live `COUNT` query every N seconds. This complexity lives in STACK/ARCHITECTURE research, not in Recharts itself — flagging here because it's the one chart where "Recharts support" and "feature complexity" diverge sharply: the chart is easy, the live-data plumbing and campaign-safe query cost under load is the real work. |

### Anti-Features (Would Look Good, Not Worth Building As Literally Described)

| Feature | Why it seems appealing | Why it's problematic | Alternative |
|---|---|---|---|
| True symmetric ThemeRiver streamgraph for rejection reasons | Matches the MonoCharts "Stream" naming and looks visually distinctive | Recharts has no built-in silhouette/wiggle baseline; achieving it needs a custom `d3-shape` offset computation layered on top of `AreaChart`, adding real engineering effort for a chart type whose main advantage over stacked-area is aesthetic smoothness, not clarity — actively harder to read absolute values from than a standard stacked area | Standard stacked `AreaChart` with `stackId` (same primitive already used for stacked-bar) |
| Forcing all 12 `VoterStatus` states into a single strict trapezoid `Funnel` | The milestone brief pairs "funnel + donut of the 12 states" as one idea, and a real `Funnel` component exists in Recharts | `VoterStatus` is not a linear pipeline — `REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `DUPLICATE` are terminal side-branches, not narrowing stages; a trapezoid funnel visually implies monotonic narrowing that doesn't exist in the data, which is actively misleading for an ops tool where "inaccurate operational numbers are unacceptable" is a stated project constraint | Either (a) MonoCharts' own horizontal-bar-ranked-list trick (honest ranking, no false monotonic implication), or (b) define an explicit "happy path" subset of states (e.g. `PENDING_REVIEW → VERIFIED_CENSUS → CONFIRMED → VOTED`) that genuinely is a funnel, and show the branch states (rejected/duplicate) separately |
| Rendering the full raw `ValidationHistory` transition graph (every `(previous_status, new_status)` pair ever recorded, unfiltered) as Sankey | "Most complete" data view, zero curation needed | With 12 states and real-world back-edges/cycles, an unfiltered Sankey becomes a dense, crossing-heavy diagram that is harder to read than the table it replaces — defeats the purpose of "expose invisible insight" | Curate to the top-N transitions by volume (or a fixed, product-defined set of "meaningful" transitions), collapsing rare noise edges into an "Otros" bucket |
| Flat (non-drill-down) treemap of all barrios simultaneously | Shows "everything at once," matches literal Departamento→Municipio→Barrio hierarchy request | Recharts' squarified algorithm degrades badly with dozens–hundreds of same-level leaves (unreadable slivers); this is a known general treemap limitation, not a SIGMA-specific one | Use Recharts Treemap's `nest`-mode drill-down (Departamento → click → Municipios → click → Barrios) instead of one flat render |

## Feature Dependencies

```
React island infra (Vite entry + wire:ignore bridge)
    └──requires (blocks all charts below)──> every chart in this document

[Donut / Stacked-bar / Gauge / Histogram / Migrated-3-existing]  (table stakes)
    └──independent of each other, can ship in any order once island infra exists

[Funnel: contactability-by-attempt, message-delivery]
    └──naturally monotonic data──> real trapezoid Funnel component is a good fit

[Funnel: 12-state voter lifecycle]
    └──requires a product decision on "happy path" subset OR bar-ranked-list fallback
           (cannot be built correctly as a literal 1:1 port of the other two funnels)

[Sankey: ValidationHistory transitions]
    └──requires an aggregation query (GROUP BY previous_status, new_status)
    └──requires a curation strategy for cycles/low-volume edges
           (blocks a "just render everything" implementation)

[Treemap: Departamento→Municipio→Barrio]
    └──requires a 3-level nested aggregation query
    └──enhanced by──> nest-mode drill-down (avoids flat-render sliver problem)

[Heatmap: caller×hour]
    └──requires a positioned custom tooltip component (native `title` attr insufficient)
    └──requires a strategy for many-caller rows (unbounded dimension vs. demo's fixed 5)

[Stream/stacked-area: rejection reasons over time]
    └──scope down to standard stacked AreaChart (conflicts with literal "streamgraph" reading)

[Live-polling line: Día D voting]
    └──requires a polling loop in the React island (independent of Livewire wire:poll)
    └──requires a cached/pre-aggregated campaign-scoped endpoint (avoid expensive query per poll tick)
```

### Dependency Notes

- **Everything requires the React island infra first** — none of these 11 charts (or the 3 migrations) can start before the Vite-entry/`wire:ignore` bridge exists; this is the correct Phase 1 of the roadmap regardless of chart ordering.
- **The three "funnel" instances are not interchangeable** — two are naturally monotonic (good `Funnel` fit) and one is not (needs a scoped-down definition or a different chart shape entirely). Treating "funnel" as one uniform feature in planning would hide this.
- **Sankey and Treemap both need their data-aggregation query built before the chart can be attempted** — the chart-side risk (cycles, sliver leaves) can't even be evaluated until real aggregated shapes exist, so these should not be estimated as "just a component swap."
- **Heatmap's real gap is tooltip + row-count handling, not the grid rendering itself** — the core CSS-grid technique from MonoCharts is already correct and reusable.
- **Stream/stacked-area and Live-polling line both have a "the obvious literal reading is the wrong scope" issue** — worth flagging explicitly to whoever writes the phase requirements so "stream" and "live" aren't taken at face value against Recharts' actual capabilities.

## MVP Definition

### Launch With (v1.3 core — table stakes)

- [ ] React island infra (Vite entry + `wire:ignore` bridge) — nothing else is possible without it
- [ ] Migrate the 3 existing `ChartWidget`s + 3 sparklines to Recharts — proves the pipeline end-to-end with zero new data-shape risk
- [ ] Donut of 12 voter states — trivial once infra exists, immediate visual payoff
- [ ] Stacked-bar team comparison by coordinator — direct MonoCharts reuse
- [ ] Funnel of call contactability by attempt — naturally monotonic, real `Funnel` component fits cleanly
- [ ] Funnel of message delivery (sent→delivered→read→clicked) — same as above, and this data is currently "100% invisible" per the brief, high value for low complexity
- [ ] Gauge + histogram for SCALE survey responses — both are direct Recharts primitives

### Add After Validation (v1.3 differentiators)

- [ ] Sankey of `ValidationHistory` transitions — once the aggregation/curation strategy for cycles is decided (this is a product decision, not just an engineering task — flag for discuss-phase)
- [ ] Treemap of territorial hierarchy — once nest-mode drill-down vs. flat-render is decided
- [ ] Heatmap of caller × hour — once the many-callers-rows strategy and real tooltip are built
- [ ] Stacked-area (scoped-down "stream") of rejection reasons over time
- [ ] Live-polling line chart for Día D voting — depends on the polling-endpoint architecture decision (separate from chart rendering itself)

### Future Consideration (v2+)

- [ ] True symmetric streamgraph (if stacked-area is judged insufficient after real usage) — defer until stacked-area is proven inadequate, since it requires custom `d3-shape` work Recharts doesn't provide out of the box
- [ ] Funnel treatment of the full 12-state voter lifecycle as a literal trapezoid — defer until a "happy path" subset is explicitly product-defined; don't force it prematurely

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---|---|---|---|
| Migrate 3 existing charts + sparklines | HIGH (proves pipeline) | LOW | P1 |
| Donut of voter states | MEDIUM | LOW | P1 |
| Stacked-bar coordinator comparison | HIGH | LOW–MEDIUM | P1 |
| Funnel — call contactability by attempt | HIGH | LOW | P1 |
| Funnel — message delivery | HIGH (currently invisible data) | LOW | P1 |
| Gauge + histogram — SCALE survey | MEDIUM | LOW–MEDIUM | P1 |
| Sankey — ValidationHistory transitions | HIGH (currently invisible data) | HIGH | P2 |
| Treemap — territorial hierarchy | MEDIUM–HIGH | MEDIUM–HIGH | P2 |
| Heatmap — caller × hour | MEDIUM–HIGH | MEDIUM | P2 |
| Stacked-area — rejection reasons over time | MEDIUM | LOW–MEDIUM | P2 |
| Live-polling line — Día D voting | HIGH (election-day critical) | MEDIUM (chart) / real risk in polling architecture | P2 |
| Funnel — 12-state voter lifecycle (literal) | LOW as literally scoped (misleading) | — | P3 / needs re-scoping first |

**Priority key:**
- P1: Table stakes, native Recharts primitives, direct MonoCharts source reuse, low risk — good Phase 1/2 roadmap material
- P2: Differentiators, genuinely new insight, real data-modeling or architecture work beyond a component swap — good candidates for dedicated research/discuss-phase before implementation phases
- P3: Needs a product/requirements decision before it can be correctly scoped at all

## Sources

- [`github.com/Subhan-code/Monocharts`](https://github.com/Subhan-code/Monocharts) — `src/components/mono-charts/` (29 files listed via GitHub API; 11 read in full via raw.githubusercontent.com, including all funnel/sankey/treemap/heatmap/gauge/donut/stacked-bar/stream/line files named in the research question) and `src/components/dither-charts/lib/recharts-tooltip.tsx` (shared tooltip primitive). HIGH confidence — primary source, read directly.
- `package.json` from the same repo — confirms `recharts: ^3.10.1`, `react: ^19.0.1`, no D3/visx/nivo dependency. HIGH confidence.
- [Recharts official docs — FunnelChart API](https://recharts.github.io/en-US/api/FunnelChart/) — confirms native `data={[{name, value}]}` shape and `Tooltip`/`Label` support. HIGH confidence (official docs).
- DeepWiki Recharts "Specialized Charts" page (`deepwiki.com/recharts/recharts/3.3-specialized-charts`) — used for Treemap squarify-algorithm and Sankey node/link/depth-assignment layout description. MEDIUM confidence (third-party AI-generated reference, not official Recharts docs) — corroborated against training-data knowledge of Recharts' long-standing `Treemap`/`Sankey` component shapes, no contradictions found.
- SIGMA codebase: `app/Enums/VoterStatus.php` (12-case enum), `app/Models/ValidationHistory.php` (`previous_status`/`new_status` transition schema) — read directly to ground the Sankey/funnel complexity assessment in real project data, not assumption. HIGH confidence (primary source, this repo).

---
*Feature research for: MonoCharts-style chart types on Recharts, for SIGMA v1.3*
*Researched: 2026-08-20*
