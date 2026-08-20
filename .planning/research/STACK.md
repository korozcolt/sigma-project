# Stack Research

**Domain:** React micro-frontend "island" (charts/data-viz) embedded inside a Laravel 12 / Livewire 3 / Filament 4 / Vite 7 app
**Researched:** 2026-08-20
**Confidence:** HIGH (all versions verified directly against the npm registry and the installed `laravel-vite-plugin` v2.1.0 source; Laravel Vite docs fetched live)

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `react` | `^19.2.8` | React runtime for the chart island | Current npm `latest`. Required peer for Recharts 3.x and Motion. No other React exists in the codebase today, so this is a net-new, additive dependency — not a framework migration. |
| `react-dom` | `^19.2.8` | DOM renderer, mounts the island into a `wire:ignore` container | Must match `react` major exactly (19.x); verified as npm `latest`. |
| `recharts` | `^3.10.1` | Declarative chart primitives (funnel, sankey, treemap, stacked-bar, area, histogram/bar) on top of D3 | Current npm `latest` (v3 line, stable — not the `3.0.0-alpha`/`beta` tags). v3's `peerDependencies` explicitly allow `react ^19.0.0` / `react-dom ^19.0.0` / `react-is ^19.0.0` — verified directly from the published package manifest. Best fit for the MonoCharts feature list (funnel, donut, sankey, treemap, stacked-bar, heatmap-via-bar, stream/stacked-area, histogram, gauge-via-radial) without hand-rolling D3. |
| `motion` | `^13.1.1` | Animation layer for chart transitions/enter-exit (the "Motion" in "React + Recharts + Motion") | This is the **current** package name — Framer Motion was renamed "Motion" and the actively developed package on npm is `motion` (import path `motion/react`), not `framer-motion`. Verified via npm dist-tags and corroborated by web search: `framer-motion` still publishes but is now a thin compatibility package (its own `dependencies` field points back to `motion-dom`/`motion-utils`, the same internals `motion` uses). Peer deps (`react ^18‖19`, `react-dom ^18‖19`) are marked **optional**, confirming Motion also works headless/vanilla-JS if ever needed outside React. |
| `@vitejs/plugin-react` | `^5.2.0` | Enables JSX/Fast Refresh compilation for the new Vite entry | **Do not use 6.x** — `@vitejs/plugin-react@6.1.0`'s `peerDependencies` require `vite ^8.0.0`, which is incompatible with this project's pinned `vite ^7.0.4`. Verified `5.2.0`'s peer range is `^4.2.0 ‖ ^5.0.0 ‖ ^6.0.0 ‖ ^7.0.0 ‖ ^8.0.0` — the last version in the 5.x line and the correct match for Vite 7. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `react-is` | `^19.2.8` | Recharts peer dependency (internal type-checking of React elements) | Add explicitly alongside `react`/`react-dom` — Recharts 3.x lists it as a required peer, not a transitive dependency, so npm/pnpm will warn (or fail under strict peer resolution) without it. |

**Recharts 3.x pulls in its own runtime dependencies you do not need to install yourself** but should be aware of for bundle-size budgeting: `@reduxjs/toolkit`, `react-redux`, `immer`, `reselect`, `es-toolkit`, `victory-vendor` (D3 sub-modules), `decimal.js-light`, `eventemitter3`. These are real `dependencies` of `recharts@3.10.1` (verified from the published manifest), not peers — Recharts v3 rewrote its internal state management on Redux Toolkit. This is the single biggest reason the chart entry point must be code-split away from `app.js`: pulling Redux/Immer/D3 into every page load would be wasteful for an app where most pages have no charts at all.

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| `@vitejs/plugin-react` (Babel-based, not `-swc`) | JSX transform + Fast Refresh in dev | Deliberately choosing the Babel variant over `@vitejs/plugin-react-swc`: pure JS, no platform-specific native binary. This project's `package.json` already carries `optionalDependencies` for Linux-specific native binaries (`@rollup/rollup-linux-x64-gnu`, `@tailwindcss/oxide-linux-x64-gnu`, `lightningcss-linux-x64-gnu`) to bridge macOS dev / Linux (Dokploy) prod — adding another native-binary dependency (swc) for a small, single-purpose island is unnecessary risk/complexity for no measurable benefit at this scale. |
| No TypeScript | — | The codebase has no `tsconfig.json` and no TS anywhere today (Laravel/Filament/Livewire/Volt stack). Use plain `.jsx` files for the island to avoid introducing a second type system and build-config surface for a single, scoped feature. `@types/react`/`@types/react-dom` (`^19.2.18`/`^19.2.4` if ever needed) are not required for this milestone. |

## Installation

```bash
# Core island runtime
npm install react@^19.2.8 react-dom@^19.2.8 react-is@^19.2.8 recharts@^3.10.1 motion@^13.1.1

# Dev dependency: JSX/Fast Refresh compiler for Vite (must be 5.x, not 6.x — see rationale above)
npm install -D @vitejs/plugin-react@^5.2.0
```

No changes to `laravel-vite-plugin` (`^2.0`, currently `2.1.0`) or `vite` (`^7.0.4`) are required — both already satisfy the new plugin's peer requirements (verified below).

## Second Vite Entry Point — Confirmed Supported by `laravel-vite-plugin@^2.0`

**Yes.** The currently-installed `laravel-vite-plugin` version (`^2.0`, resolves to `2.1.0`) supports multiple entry points natively, and this is the mechanism for code-splitting the React runtime away from the default page load. Verified by reading the published `2.1.0` source directly (not training data):

```js
// resolvePluginConfig() in laravel-vite-plugin@2.1.0
if (typeof config === "string" || Array.isArray(config)) {
    config = { input: config, ssr: config };
}
```

and

```js
function resolveInput(config, ssr) {
    return ssr ? config.ssr : config.input;
}
// ...later, feeds directly into: build.rollupOptions.input
```

This means `laravel([...])` accepts an array today exactly as documented for all versions of the plugin, e.g.:

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',      // existing, empty today — untouched
                'resources/js/charts.jsx',  // NEW second entry — the React island
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
});
```

Because `charts.jsx` and `app.js` share no imports, Rollup emits them as **separate output chunks** with independent `manifest.json` entries. The React/Recharts/Motion bundle (plus Recharts' Redux/Immer/D3 payload) is only fetched by the browser on pages that actually include:

```blade
@viteReactRefresh
@vite('resources/js/charts.jsx')
```

Every other Blade layout / Livewire / Filament view that only loads `resources/js/app.js` (or nothing extra) never downloads the React runtime at all — this is the code-splitting guarantee, confirmed structurally (via the plugin's own `rollupOptions.input` array → per-entry chunking, which is standard Rollup/Vite behavior, not something the Laravel plugin needs to special-case).

**Peer-dependency compatibility check (why no `laravel-vite-plugin` or `vite` upgrade is needed):**

| Package | Installed | Peer requirement | Compatible? |
|---------|-----------|-------------------|--------------|
| `laravel-vite-plugin@2.1.0` | `^2.0` (current) | `vite: ^7.0.0` | Yes — project pins `vite@^7.0.4` |
| `@vitejs/plugin-react@5.2.0` | new | `vite: ^4.2.0 ‖ ^5.0.0 ‖ ^6.0.0 ‖ ^7.0.0 ‖ ^8.0.0` | Yes — `^7.0.4` satisfies |
| `laravel-vite-plugin@3.2.0` (latest) | not needed | `vite: ^8.0.0` | **No** — would force a Vite 8 major upgrade project-wide; explicitly avoided this milestone |
| `@vitejs/plugin-react@6.1.0` (latest) | not needed | `vite: ^8.0.0` | **No** — same reason, avoided |

Staying on `laravel-vite-plugin ^2.0` + `vite ^7.0.4` and picking `@vitejs/plugin-react@5.2.0` keeps this milestone strictly additive with zero forced upgrades to the existing build pipeline (Tailwind v4 plugin, existing Vite config, Playwright, etc. all remain untouched).

## `wire:ignore` Bridge Pattern (mounting point)

Not a new library — this is a Livewire-native mechanism, already implicitly decided in the milestone scope ("Vite entry separado + puente `wire:ignore`"). Practical shape for this project:

```blade
{{-- inside a Filament ChartWidget view or Volt component --}}
<div wire:ignore>
    <div id="chart-root-{{ $this->getId() }}" data-chart="funnel" data-payload="{{ json_encode($chartData) }}"></div>
</div>
```

`wire:ignore` tells Livewire to never touch that DOM subtree on re-render, so React fully owns it. The `charts.jsx` entry mounts one or more `createRoot()` instances by querying `[data-chart]` elements on `DOMContentLoaded`, reading `data-payload` (or receiving data via a small `window.dispatchEvent`/Livewire event bridge for live-updating widgets like the Día D voting line chart's polling). This pattern requires no additional package — plain `react-dom/client`.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|--------------------------|
| `recharts` v3 | `visx` (Airbnb, low-level D3+React primitives) | If MonoCharts' visual spec demands pixel-level custom chart geometry (e.g., a bespoke Sankey layout) that Recharts' declarative API can't express — visx trades convenience for full D3 control. Not recommended as the default here since Recharts v3 already ships Funnel, Sankey, Treemap, and ComposedChart (stacked-bar/area) components covering essentially the whole MonoCharts feature list out of the box. |
| `recharts` v3 | `nivo` | Nivo has strong out-of-the-box theming and a built-in Heatmap component (useful for the caller×hora heatmap), but pulls in its own larger dependency tree per chart family (separate `@nivo/*` packages) and has a less consistent v3-era React 19 compatibility story than Recharts at time of research. Recharts' `ComposedChart`/custom `Cell` coloring can approximate a heatmap via a colored grid of `Bar`/`Scatter` cells, avoiding a second charting library. |
| `motion` (`motion/react`) | `framer-motion` (legacy name) | Only if pinned by an existing dependency that still imports `framer-motion` directly — otherwise always prefer `motion`, since `framer-motion@13.1.1`'s own `dependencies` now point back into `motion-dom`/`motion-utils`, confirming it is the compatibility shim, not the actively developed package. |
| `@vitejs/plugin-react` (Babel) | `@vitejs/plugin-react-swc` | If dev-server cold-start/HMR latency on the chart island becomes a measurable pain point later (SWC is faster to transform than Babel). Not worth the added native-binary surface for a single, scoped island at this milestone's size. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|--------------|
| `laravel-vite-plugin@^3.x` / `@vitejs/plugin-react@^6.x` | Both require `vite ^8.0.0`; upgrading Vite is an unrelated, unscoped major-version change that would touch the Tailwind v4 Vite plugin and every existing build config for zero benefit to this milestone's goal | `laravel-vite-plugin@^2.0` (already installed) + `@vitejs/plugin-react@^5.2.0` |
| Making `resources/js/app.js` import React/Recharts/Motion directly (single shared entry) | Defeats the entire "island" premise — every page (including pages with zero charts) would download React + Recharts' Redux/Immer/D3 payload on every load | A dedicated second Vite entry (`resources/js/charts.jsx`) included only via `@vite(...)` on chart-bearing views |
| `chart.js` / `react-chartjs-2` | Chart.js currently only exists as a transitive dependency pulled in by Filament itself (not a direct npm dependency) and does not natively support Sankey/Treemap without extra plugins; the milestone explicitly targets React/Recharts to get MonoCharts' actual visual composition, not just its color palette | `recharts` as scoped above |
| `framer-motion` (as a fresh install) | Legacy package name; new installs should target the actively-developed `motion` package directly to avoid an unnecessary indirection layer | `motion`, imported as `motion/react` |
| Introducing TypeScript for just this island | No TS anywhere else in the codebase; adds a parallel type-checking/build surface for a single, scoped feature with no other stated need | Plain `.jsx` |
| Inertia.js or any full-page React routing | Explicitly out of scope — this milestone keeps Livewire/Filament as the primary frontend and React strictly as an embedded, `wire:ignore`-bridged island, not a competing routing/page-ownership layer | Keep React mount points scoped to individual widget/component DOM subtrees only |

## Stack Patterns by Variant

**If a chart widget needs live/polling data (e.g., the Día D live voting line chart):**
- Keep Livewire's `wire:poll` (or existing polling mechanism) driving the *data fetch* on the PHP/Blade side, and re-render only the `data-payload` attribute or dispatch a custom DOM/`window` event that the mounted React component listens for.
- Do **not** give React its own independent polling/fetch loop against Laravel endpoints — that would create two competing sources of truth (Livewire's existing polling infra vs. a new React-side fetch layer) for the same data.

**If a chart is a static/one-time render (most of the 12 MonoCharts chart types listed in the milestone, e.g., Sankey of `ValidationHistory` transitions, Treemap of territory):**
- Server-render the payload into the `data-payload` JSON attribute at Blade/Livewire render time; React mounts once and never needs to talk back to Livewire.

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|------------------|-------|
| `laravel-vite-plugin@2.1.0` (installed range `^2.0`) | `vite@^7.0.4` (installed) | Verified via published `peerDependencies`; no upgrade needed |
| `@vitejs/plugin-react@5.2.0` | `vite@^7.0.4` (installed) | Verified via published `peerDependencies` (`^4.2.0 ‖ ^5.0.0 ‖ ^6.0.0 ‖ ^7.0.0 ‖ ^8.0.0`) |
| `recharts@3.10.1` | `react@19.2.8` / `react-dom@19.2.8` / `react-is@19.2.8` | Verified via published `peerDependencies` (`^16.8.0 ‖ ^17.0.0 ‖ ^18.0.0 ‖ ^19.0.0` for all three) |
| `motion@13.1.1` | `react@19.2.8` / `react-dom@19.2.8` | Verified via published `peerDependencies` (`^18.0.0 ‖ ^19.0.0`, both marked optional so Motion also works without React) |
| `@vitejs/plugin-react@6.1.0` (latest, **not** recommended) | `vite@^8.0.0` only | Would force an unrelated Vite major upgrade — avoided |
| `laravel-vite-plugin@3.2.0` (latest, **not** recommended) | `vite@^8.0.0` only | Same reason — avoided |

## Sources

- npm registry (`registry.npmjs.org`) `dist-tags` and per-version manifest JSON for `react`, `react-dom`, `react-is`, `recharts`, `motion`, `framer-motion`, `laravel-vite-plugin`, `vite`, `@vitejs/plugin-react`, `@types/react`, `@types/react-dom` — fetched live 2026-08-20, HIGH confidence (primary source of truth for versions/peer deps)
- `unpkg.com/laravel-vite-plugin@2.1.0/dist/index.js` and `laravel-vite-plugin@3.2.0` manifest — read directly to confirm array-based entry-point support (`resolvePluginConfig`/`resolveInput`) and the v3 peer bump to Vite 8 — HIGH confidence
- Laravel 12.x official docs, `laravel.com/docs/12.x/vite` — fetched live for `@vite()` multi-entry Blade directive, React/`@vitejs/plugin-react`/`@viteReactRefresh` setup instructions — HIGH confidence
- WebSearch: "motion vs framer-motion npm package 2026 recommended import react" — corroborated by the npm manifest evidence that `framer-motion`'s own dependencies now point to `motion-dom`/`motion-utils` — MEDIUM→HIGH confidence (cross-verified against primary npm data, not relied on alone)
- Local inspection: current `package.json` at repo root (installed `vite@^7.0.4`, `laravel-vite-plugin@^2.0`, no existing React/chart libs, `resources/js/app.js` empty, Linux-native `optionalDependencies` pattern) — HIGH confidence (ground truth)

---
*Stack research for: React island (Recharts + Motion) inside Laravel 12 / Livewire 3 / Filament 4 / Vite 7 — v1.3 MonoCharts milestone*
*Researched: 2026-08-20*
