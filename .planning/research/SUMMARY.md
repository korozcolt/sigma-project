# Project Research Summary

**Project:** SIGMA v1.3 — MonoCharts-style visualizations (React island on Recharts + Motion)
**Domain:** React micro-frontend "island" embedded inside a Laravel 12 / Livewire 3 / Filament 4 / Vite 7 admin app
**Researched:** 2026-08-20
**Confidence:** HIGH

## Executive Summary

This milestone adds a scoped React island — Recharts for chart primitives, Motion for transitions — inside SIGMA's existing Filament/Livewire dashboards, to replace Chart.js and expose several data views (validation-history transitions, message-delivery funnel, territorial hierarchy, caller effectiveness, Día D live voting) that are currently invisible or under-visualized. Experts building this kind of thing keep the framework boundary narrow and one-directional: a single, code-split Vite entry (`resources/js/charts/main.tsx`) mounts independent React roots into `wire:ignore`-protected DOM nodes, and Filament's own already-proven `ChartWidget` polling/checksum/`dispatch()` plumbing — not a new API endpoint, not DOM-attribute polling — is the correct (and only reliable) channel for pushing fresh data into React across `wire:poll` ticks. This mechanism is not a hypothesis; it was reverse-engineered directly from Filament's own vendored `chart.js`/`chart-widget.blade.php`, so the riskiest architectural question (how does React get live data without being remounted or orphaned) is already answered with HIGH confidence.

The recommended approach: extend `ChartWidget` per chart (repoint `$view` to one shared `react-chart.blade.php`), keep `wire:poll` on an outer wrapper and `wire:ignore` on the inner React mount only, and call `root.render()` again on the same root on every poll instead of remounting. Recharts v3 covers most of the target chart types natively (`Funnel`, `Sankey`, `Treemap`, `PieChart`/donut, stacked `BarChart`/`AreaChart`) except heatmap and gauge, which require hand-rolled composites — both already have a reusable pattern in the MonoCharts reference repo. Critically, MonoCharts' own funnel/sankey/treemap components are NOT built on Recharts' real primitives (they're hardcoded demo markup), so they are stylistic references only, not implementation shortcuts — the real Sankey/Treemap/Funnel components must be built from scratch against actual SIGMA data (`ValidationHistory` transitions, territorial hierarchy, `VoterStatus`).

The dominant risk category is not "can Recharts render this" but "does the Livewire↔React bridge stay correctly wired across polling, navigation, and 5 panels of shared widget reuse." Pitfalls research identifies orphaned/stale React roots on poll (if `wire:ignore` boundaries or data channels are built wrong), leaked roots on SPA navigation, event-delegation conflicts with Livewire/Alpine click handlers, and — given this project's own documented history (Phase 18/19) of shared-widget-not-registered-on-every-panel bugs — the very real risk of the new Vite entry or Filament asset registration being missing on 1-2 of the 5 panels. All of these are addressed by building one shared mount/unmount helper and one shared Blade view in a dedicated infrastructure phase before any real chart is built, and by treating "verify on all 5 panels" as an explicit phase success criterion rather than an assumption.

## Key Findings

### Recommended Stack

React 19 + Recharts 3.10 + Motion (the current name for what was "Framer Motion") is the correct, verified-compatible stack for this milestone, added as a net-new, purely additive second Vite entry — no upgrade to Vite, `laravel-vite-plugin`, or any existing build tooling is required or should be attempted (the latest majors of both force a Vite 8 bump that is explicitly out of scope). `@vitejs/plugin-react@^5.2.0` (Babel-based, not SWC) is the correct dev dependency for JSX/Fast Refresh, matching the project's existing "no extra native binaries beyond what's already needed for macOS/Linux parity" posture. No TypeScript — the codebase has none today, and introducing it for a single scoped island isn't worth the parallel build-config surface.

**Core technologies:**
- `react` / `react-dom` `^19.2.8` — chart island runtime, net-new dependency, no framework migration
- `recharts` `^3.10.1` — declarative chart primitives (funnel, sankey, treemap, stacked-bar/area, donut) covering nearly the entire MonoCharts feature list without hand-rolled D3; pulls in Redux Toolkit/Immer/D3 internally, which is exactly why it must be code-split away from `app.js`
- `motion` `^13.1.1` (import as `motion/react`) — animation/transition layer; use `motion`, not the legacy `framer-motion` package name
- `@vitejs/plugin-react` `^5.2.0` (dev) — JSX/Fast Refresh compiler compatible with the pinned `vite@^7.0.4`
- `react-is` `^19.2.8` — required explicit peer for Recharts 3.x

### Expected Features

Of the ~13 chart widgets in scope (3 migrations + ~10 new), Recharts has a native, data-driven primitive for most; heatmap and gauge require hand-rolled composites (both have a working reference in MonoCharts). The single most important finding: MonoCharts' own funnel/sankey/treemap source is **styling reference only** — none of the three use Recharts' real components, so they must be built fresh against real SIGMA data shapes.

**Must have (table stakes / P1 — good Phase 1/2 material):**
- React island infra (Vite entry + `wire:ignore` bridge) — blocks every other chart, must ship first
- Migrate the 3 existing `ChartWidget`s + 3 sparklines to Recharts — proves the full pipeline with zero new data-shape risk
- Donut of 12 `VoterStatus` states, stacked-bar coordinator comparison, gauge + histogram for SCALE survey — direct Recharts primitives, low complexity
- Funnel of call contactability by attempt, funnel of message delivery (sent→delivered→read→clicked) — naturally monotonic data, real `Funnel` component fits, and this data is currently 100% invisible — high value, low complexity

**Should have (differentiators / P2 — needs data-modeling + product decisions before implementation):**
- Sankey of `ValidationHistory` state transitions — requires an aggregation + cycle/low-volume-edge curation strategy (product decision, not just engineering)
- Treemap of Departamento→Municipio→Barrio — requires nest-mode drill-down (a flat render will produce unreadable slivers at real campaign scale)
- Heatmap of caller × hour effectiveness — core CSS-grid technique is reusable from MonoCharts, but needs a real positioned tooltip and a many-callers-rows strategy
- Stacked-area (scoped down from "streamgraph") of rejection reasons over time
- Live-polling line chart for Día D voting — chart itself is easy; the real work is the polling-endpoint/caching architecture under election-day load

**Defer / re-scope before building (P3):**
- A literal trapezoid funnel of the full 12-state voter lifecycle — `VoterStatus` is not a linear pipeline (has terminal side-branches like `REJECTED_CENSUS`, `DUPLICATE`); forcing it into a funnel shape would misrepresent data, which conflicts with this project's "inaccurate operational numbers are unacceptable" constraint. Needs an explicit "happy path" subset defined first, or use MonoCharts' honest bar-ranked-list alternative.
- True symmetric ThemeRiver streamgraph — Recharts has no native silhouette/wiggle baseline; not worth the custom `d3-shape` work for a purely aesthetic gain over stacked-area.

### Architecture Approach

Each chart widget is a `ChartWidget` subclass with a repointed `$view` pointing at one shared `react-chart.blade.php`, keeping `wire:poll` on the outer wrapper and `wire:ignore` scoped tightly to the inner React mount `<div>` only (never the whole widget card). Fresh data reaches React exclusively via Filament's existing `dispatch('updateChartData', data: ...)` browser-event channel (checksum-gated), heard by an Alpine bridge component (`reactChartBridge`) living *inside* the ignored subtree — Alpine reactivity survives `wire:ignore` even though DOM diffing doesn't — which calls `root.render()` again on the same, never-recreated React root. This is reconciliation, not remount, so Recharts/Motion animation state survives every poll tick exactly like Chart.js's `chart.update()` does today.

**Major components:**
1. **PHP `ChartWidget` subclass** (`app/Filament/Widgets/*`) — owns campaign/role-scoped data query via `getData()`, unchanged from existing widgets; only `$view` and the type/kind discriminator change
2. **Shared Blade view + Alpine bridge** (`react-chart.blade.php`, `reactChartBridge` Alpine component) — the one load-bearing integration boundary; mounts once, listens for the dispatched event, never remounts
3. **Vite entry / React tree** (`resources/js/charts/main.tsx`, `ChartRouter.tsx`, per-chart-kind components) — code-split chunk registered via `Vite::withEntryPoints()` in each `*PanelProvider` that ships chart widgets (Admin, Reports today), never in the shared app layout

### Critical Pitfalls

1. **Stale/orphaned React root on `wire:poll`** — if data is read off re-rendered DOM attributes instead of pushed via Filament's dispatch channel, or if the `wire:ignore` wrapper itself gets replaced, the chart silently stops updating after the first poll with no error. Avoid by using the dispatch→`root.render()` bridge exclusively and keeping the ignored wrapper's attributes fully stable.
2. **Leaked React roots on SPA navigation (`wire:navigate`)** — React has no automatic hook into Livewire/Alpine teardown; must wire an explicit Alpine `destroy()` (and a belt-and-suspenders `livewire:navigate` listener) to call `root.unmount()`. Build this once in the shared mount helper — retrofitting after 10+ widgets exist is expensive.
3. **Event-delegation conflicts between React and Livewire/Alpine** — `wire:click`/`x-on` handlers living inside the React-owned subtree can be silently shadowed. Keep the `wire:ignore` boundary as high as practical (whole widget card, not just the inner chart div) and route any chart-triggered action back to Livewire via an explicit bridge call, never native DOM bubbling.
4. **Vite/Filament asset registration missing on some of the 5 panels** — this project has a documented history (Phase 18/19) of exactly this class of gap for shared widgets. Register the chart entry per-`PanelProvider` (not the global layout) and treat "verify on all 5 panels" as an explicit phase success criterion.
5. **Coverage-theater tests** — Livewire/Pest Feature tests can only verify the data contract handed to React, never the actual rendered chart; a Pest Browser (Playwright) test is required per widget to verify real rendered content, per this project's existing "Day D flows require test protection" bar.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: React island infrastructure
**Rationale:** Every chart in this milestone is blocked on the Vite entry + `wire:ignore`/dispatch bridge existing and being proven correct; this is also where nearly every critical pitfall (stale roots, leaked roots, false-hydration confusion, test-layer convention) must be prevented once, centrally, rather than retrofitted across 10+ widgets later.
**Delivers:** `vite.config.js` + `package.json` changes (react, react-dom, recharts, motion, react-is, `@vitejs/plugin-react`), a throwaway "Hello from React" widget proving the full poll→dispatch→`root.render()` cycle in a real browser, shared mount/unmount helper with teardown wired to Alpine `destroy()`/`livewire:navigate`, per-panel `Vite::withEntryPoints()` registration verified across all 5 `PanelProvider`s, and the Pest Feature-vs-Browser test-layer convention documented.
**Addresses:** React island infra (FEATURES.md must-have #1)
**Avoids:** Pitfalls 1, 2, 4, 5, 6 (stale roots, leaked roots, missing per-panel registration, false-hydration debugging, coverage-theater tests)

### Phase 2: Shared chart-widget contract + migrate existing charts
**Rationale:** Establishes the one-view-many-widgets pattern (`react-chart.blade.php` + `ChartRouter.tsx`) before any new chart-specific work, and validates it against the 3 existing `ChartWidget`s whose underlying queries are already proven correct — lowest-risk real-data validation of the new pipeline.
**Delivers:** `react-chart.blade.php` shared view, `ChartRouter.tsx` skeleton, `ValidationProgressChart`/`TerritorialDistributionChart`/`SurveyResultsWidget` migrated to Recharts with `getData()` untouched, sparkline migration-strategy decision made (embedded `Stat::chart()` vs. dedicated small `ChartWidget`s).
**Uses:** `recharts` `LineChart`/`BarChart`/`PieChart` (STACK.md)
**Implements:** `ChartWidget` subclass + repointed `$view` pattern (ARCHITECTURE.md Pattern 2)

### Phase 3: Table-stakes new charts (donut, stacked-bar, funnels, gauge/histogram)
**Rationale:** These are direct Recharts primitives with high MonoCharts source reusability and no data-modeling ambiguity — good candidates to ship as a batch once infra is proven, independent of each other.
**Delivers:** Donut of 12 `VoterStatus` states, stacked-bar coordinator comparison, funnel of call contactability by attempt, funnel of message delivery (sent→delivered→read→clicked — currently 0% visible), gauge + histogram for SCALE survey.
**Addresses:** FEATURES.md P1 list (table stakes)
**Avoids:** Pitfall 3 (event-delegation conflicts) — must be explicitly designed/reviewed here since it affects every widget with adjacent header actions

### Phase 4: Differentiator charts requiring data-modeling/product decisions
**Rationale:** Sankey and Treemap both need their aggregation/curation query built and validated (in isolation, e.g. via `tinker`) before the chart-side risk can even be evaluated — these are not component swaps. The literal 12-state funnel needs an explicit product decision (happy-path subset) before it can be correctly scoped at all.
**Delivers:** Sankey of `ValidationHistory` transitions (curated, not raw-dump), Treemap of territorial hierarchy with nest-mode drill-down, Heatmap of caller × hour (with real positioned tooltip + many-callers strategy), stacked-area of rejection reasons over time.
**Addresses:** FEATURES.md P2 list (differentiators, currently-invisible data)
**Avoids:** The Sankey/Treemap "just a component swap" trap and the literal-streamgraph/literal-12-state-funnel anti-features flagged in FEATURES.md

### Phase 5: Día D live-polling line chart
**Rationale:** The only widget with a materially different polling cadence/live-data-freshness requirement (election-day, not steady-state dashboard); should build on a fully proven bridge rather than being part of proving it, and its query-cost-under-load risk is best isolated last.
**Delivers:** Live voting line chart (`VoteRecord.voted_at`) with its own polling loop and a cached/pre-aggregated campaign-scoped endpoint to avoid expensive per-tick queries during Día D peak load.
**Addresses:** FEATURES.md "live-polling line chart" (P2, election-day critical)
**Avoids:** Pitfall on performance traps — repeated expensive live `COUNT` queries under Día D concurrent load (PITFALLS.md Performance Traps)

### Phase Ordering Rationale

- Infra must come first because literally every chart is architecturally blocked on it (FEATURES.md dependency graph is explicit and unanimous on this point).
- Migrating existing charts before building new ones isolates "does the bridge work" from "is the new data query correct" — two independent risk classes that should not be debugged simultaneously.
- Table-stakes charts are grouped ahead of differentiators because they have no open data-modeling questions; differentiators are deliberately grouped together because they share the same category of pre-work (aggregation query + curation strategy) that should be resourced/reviewed together, likely with a research-phase step.
- Día D is last because it is the one place where the milestone's own architecture research explicitly recommends building on a "fully proven" bridge rather than helping to prove it, given its unique real-time/high-stakes profile.

### Research Flags

Needs research during phase planning:
- **Sankey/Treemap phase (Phase 4):** genuine data-modeling and curation-strategy decisions (cycle handling, drill-down UX) that are product decisions, not pure engineering — flag for a discuss-phase or `/gsd:research-phase` before implementation.
- **Día D live-polling phase (Phase 5):** polling-endpoint/caching architecture under concurrent election-day load has real risk not yet fully resolved in this research round.
- **Event-delegation/DOM-boundary design (touches Phase 3 onward):** PITFALLS.md flags this as needing explicit design review before the first widget migration, not an ad hoc per-widget decision.

Phases with standard, well-documented patterns (skip deep research):
- **Phase 1 (infra):** mechanism verified directly against this project's own vendored Filament source — HIGH confidence, no further research needed, just careful implementation and testing.
- **Phase 2 (migrate existing) and Phase 3 (table-stakes new charts):** direct Recharts primitives with working MonoCharts reference implementations for data shape and styling.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All versions/peer-deps verified live against the npm registry and installed `laravel-vite-plugin` source, not training data |
| Features | HIGH | Recharts + MonoCharts source read directly via GitHub API/raw source; SIGMA's own `VoterStatus`/`ValidationHistory` schema read directly to ground complexity assessments |
| Architecture | HIGH | Core bridge mechanism reverse-engineered from this project's own vendored Filament `ChartWidget`/`chart.js` source, not inferred; one MEDIUM sub-point (`Vite::withEntryPoints()` auto Fast-Refresh preamble) needs a local smoke test to confirm |
| Pitfalls | MEDIUM | Livewire/Alpine `wire:ignore` semantics and Vite multi-entry behavior are HIGH (official docs + GitHub issues); the React-in-Livewire-specific integration pitfalls are reasoned from React's documented lifecycle API plus general cross-framework-island precedent (React-in-Turbo/Hotwire), since no first-party Livewire+React guidance exists — flagged as the weakest-sourced research area, recommend a small spike to validate before committing to the full migration plan |

**Overall confidence:** HIGH

### Gaps to Address

- **`Vite::withEntryPoints()` auto Fast-Refresh preamble behavior** — architecture research flags this as MEDIUM confidence; verify with one `npm run dev` smoke test in Phase 1 before relying on it, fall back to explicit `@viteReactRefresh` + `@vite()` if it doesn't hold.
- **`vite build` atomicity across multi-entry failures** — unverified whether a syntax error in the new chart entry blocks deploys of the unrelated Livewire-only app bundle; test deliberately in Phase 1 (PITFALLS.md "Looks Done But Isn't" checklist item).
- **Tailwind v4 content-scanning coverage of new `.jsx`/`.tsx` files** — verify the React source directory is actually included in Tailwind's scan glob before shipping any chart with Tailwind-styled wrapper markup; silent failure mode (works in dev, unstyled in prod).
- **React+Livewire+Alpine event-delegation conflicts** — this whole pitfall category (PITFALLS.md Pitfall 3) is reasoned from general cross-framework precedent, not a SIGMA-specific or Livewire+React-specific source; validate with a real click-through test in Phase 1/3 against widgets that have adjacent Filament header actions/dropdowns.
- **Sankey cycle/low-volume-edge curation strategy and Treemap drill-down UX** — both are explicitly flagged in FEATURES.md as product decisions, not engineering tasks; must be resolved (likely via discuss-phase) before Phase 4 can be scoped precisely.
- **12-state voter-lifecycle funnel scope** — needs an explicit "happy path" subset defined by product/stakeholders before any implementation attempt; do not build a literal trapezoid funnel of all 12 states.

## Sources

### Primary (HIGH confidence)
- npm registry `dist-tags`/manifest JSON for `react`, `react-dom`, `react-is`, `recharts`, `motion`, `framer-motion`, `laravel-vite-plugin`, `vite`, `@vitejs/plugin-react` — fetched live 2026-08-20
- `unpkg.com/laravel-vite-plugin@2.1.0` and `@3.2.0` source — read directly for multi-entry support and Vite-8 peer bump
- `github.com/Subhan-code/Monocharts` — `src/components/mono-charts/` (29 files listed, 11 read in full via raw source) and `package.json`
- Recharts official docs (`recharts.github.io`) — `FunnelChart` API
- This repository's vendored Filament source — `vendor/filament/widgets/src/ChartWidget.php`, `chart-widget.blade.php`, `chart.js`, `Stat.php`; this repo's own `app/Filament/Widgets/*`, `*PanelProvider.php`, `vite.config.js`, `package.json`, `app/Enums/VoterStatus.php`, `app/Models/ValidationHistory.php`
- Laravel 12.x official docs — Vite/`@vite()`/React setup (`laravel.com/docs/12.x/vite`)
- Livewire 3.x official docs — `wire:poll`, `wire:ignore`
- Pest Browser Testing official docs (`pestphp.com/docs/browser-testing`)
- `.planning/PROJECT.md` — Phase 18/19 cross-panel scoping-gap precedent

### Secondary (MEDIUM confidence)
- DeepWiki Recharts "Specialized Charts" page — Treemap/Sankey layout-algorithm description, corroborated against known Recharts component shapes
- WebSearch — `motion` vs `framer-motion` package-naming consensus, cross-verified against npm manifest evidence
- GitHub Discussions/Issues on `wire:ignore` semantics (#5813, #1878, #7788, #2046) and `laravel/vite-plugin` manifest generation (#212)
- Recent blog posts on Pest 4 Browser testing practical patterns (Shocm's Blog, RichDynamix)

### Tertiary (LOW confidence)
- General cross-framework "island" integration precedent (React-in-Turbo/Hotwire, React-in-Vue) — used to reason through React/Livewire/Alpine event-delegation and root-lifecycle pitfalls where no first-party Livewire+React guidance exists; recommend a validation spike before committing to the full migration plan

---
*Research completed: 2026-08-20*
*Ready for roadmap: yes*
