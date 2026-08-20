# Phase 20: React Island Infrastructure - Context

**Gathered:** 2026-08-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 20 delivers the plumbing that lets any future chart mount as an isolated React+Recharts+Motion component inside a Filament widget, safely surviving Livewire `wire:poll` ticks and SPA navigation, verified across all 5 panels (Admin, Coordinator, AreaCoordinator, Leader, Reports). It is proven with exactly one throwaway proof-of-concept widget. It is NOT about any specific chart's business data or visual polish — that belongs to Phase 21 (migrate existing charts) onward.

</domain>

<decisions>
## Implementation Decisions

### PoC scope and visual fidelity

- **D-01:** The Phase 20 throwaway PoC widget stays minimal/functional — a simple box proving the poll→dispatch→`root.render()`→unmount cycle works. It deliberately does NOT adopt MonoCharts' real visual composition (nested card, full-radius bars, header/footer chrome) yet. Rationale (user-selected, matches research's own phase-ordering logic): separates plumbing risk from visual risk — if something breaks, it's clear which of the two failed. Full MonoCharts visual composition is layered on starting Phase 21 when real charts are migrated.

### Theming

- **D-02:** No Filament panel currently calls `darkMode()` — verified directly in code (`app/Providers/Filament/*PanelProvider.php`, all 5 files) — so today's panels run light-only, no toggle. Despite that, the chart island must be built to support BOTH light and dark MonoCharts variants from the start (the shared `react-chart.blade.php`/React tree should accept a theme prop, not hardcode light-only), even though nothing in the panel currently switches it. This leaves the door open if panel-wide dark mode is added later, without a rework of the chart components themselves. Concretely: build both variants of the shared card shell now; wire the actual light/dark selection to a fixed "light" default (matching today's panel reality) until/unless panel dark mode ships.

### Failure/degraded state

- **D-03:** If the React bundle fails to load (missing Vite manifest, bad deploy) or the `wire:poll`→`dispatch()`→React bridge breaks in production, the widget must show an explicit, visible error state ("No se pudo cargar la gráfica" or equivalent) — never a silent blank, an indefinitely-spinning skeleton, or stale-looking data with no indication something is wrong. This follows directly from the project's standing constraint that inaccurate/misleading operational numbers are unacceptable — a broken chart must announce itself as broken, not look like a working chart showing zero or old data.

### Verification

- **D-04:** Beyond the automated Pest 4 Browser test required by INFRA-04, the user wants a human checkpoint: see the PoC widget running in a real browser (poll cycle updating data, navigating between panels not leaking a React root) before Phase 20 is considered done. This matches the user's established preference for browser-verifying UI changes before treating them as shipped — do not close out Phase 20 on automated tests alone; present the live PoC for manual confirmation first.

### Claude's Discretion

- Exact directory/file naming under `resources/js/charts/` and the Alpine bridge component's internal naming.
- Which panel hosts the throwaway PoC widget (Admin is the natural choice — it's where the widgets being migrated in Phase 21 already live, easiest to verify quickly).
- Exact mechanics of the `Vite::withEntryPoints()` render-hook registration per `PanelProvider`, as long as it's verified present on all 5 panels per INFRA-03.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this milestone's primary technical grounding)
- `.planning/research/ARCHITECTURE.md` — the verified bridge mechanism (wire:ignore + Filament's own dispatch/checksum channel + Alpine bridge + `root.render()` reconciliation, reverse-engineered from this repo's vendored Filament source), Vite multi-entry setup, `ChartWidget` vs plain `Widget` subclass strategy, new/modified file list, build order
- `.planning/research/PITFALLS.md` — the 6 critical pitfalls this phase must prevent centrally (stale/orphaned roots on poll, leaked roots on SPA navigation, event-delegation conflicts, missing per-panel asset registration, coverage-theater tests, false-hydration confusion)
- `.planning/research/STACK.md` — exact verified package versions (`react@19.2.8`, `react-dom@19.2.8`, `recharts@3.10.1`, `motion@13.1.1`, `react-is@19.2.8`, `@vitejs/plugin-react@^5.2.0`) and the Vite/laravel-vite-plugin compatibility matrix (do NOT upgrade Vite/laravel-vite-plugin past what's compatible with the pinned `vite@^7.0.4`)
- `.planning/research/SUMMARY.md` — executive synthesis and phase-ordering rationale

### Requirements and roadmap
- `.planning/REQUIREMENTS.md` — INFRA-01 through INFRA-04 (this phase's exact requirement text)
- `.planning/ROADMAP.md` — Phase 20 section (goal, success criteria, dependencies)

### Project constraints
- `.planning/PROJECT.md` — Current Milestone section (v1.3 scope) and Constraints section (Architecture: harden Laravel/Filament/Livewire in place; Operations: reporting/widgets must reflect campaign reality — inaccurate numbers unacceptable, directly informs D-03)

No other external specs/ADRs apply — this is the first phase of a new milestone with no prior-phase CONTEXT.md in this domain (v1.2's CONTEXT.md files at `.planning/phases/12-17` are about the hierarchy/metadata domain, not frontend/charts, and carry no applicable decisions forward).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Filament/Widgets/RevalidationProgressWidget.php` — existing precedent for a Filament `Widget` subclass with a fully custom Blade view instead of `ChartWidget`, proving custom-render widgets are an accepted pattern in this codebase
- `app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php` — both already use `pollingInterval = '120s'`, the exact polling cadence pattern the React bridge must integrate with
- `vendor/filament/widgets/src/ChartWidget.php` — `$view` property confirmed `protected`, not `final`; subclasses can repoint it to a custom Blade view while inheriting the polling/checksum/`dispatch()` plumbing (per ARCHITECTURE.md)

### Established Patterns
- 5 `*PanelProvider.php` files (`app/Providers/Filament/`) each already use `PanelsRenderHook::BODY_END` for the existing `motion-init.blade.php` vanilla-JS UX script — same render-hook mechanism the new Vite chart entry should register through per panel (verified pattern, not the `motion` npm package — naming collision noted, keep distinct)
- No `darkMode()` call in any PanelProvider today — panels are light-only (verified during this discussion, informs D-02)

### Integration Points
- Each `*PanelProvider.php` that ships chart widgets (today: Admin, Reports; expanding per Phase 21+ migration) needs the new Vite entry registered via a render hook — never the shared global layout
- `tests/Browser/` — existing Pest 4 Browser test directory and `loginRealBrowserUser()` shared helper (promoted to `tests/Pest.php` during Phase 19) — reuse this helper for INFRA-04's browser test rather than reinventing login

</code_context>

<specifics>
## Specific Ideas

No specific visual reference beyond "MonoCharts" itself (already researched in depth — see ARCHITECTURE.md/FEATURES.md). No particular library alternative was requested. The one explicit behavioral reference: "no debe verse como un chart roto mostrando ceros" (D-03) — an operator must always be able to tell a broken chart from a chart legitimately showing zero/empty data.

</specifics>

<deferred>
## Deferred Ideas

- Sparkline migration strategy (embedded `Stat::make()->chart()` vs. dedicated small `ChartWidget`s for `CampaignStatsOverview`/`CallCenterStatsWidget`/`SurveyStatsOverview`) — flagged in ARCHITECTURE.md as an open question, belongs to Phase 21 (MIGR-02) discussion, not Phase 20 infra.
- Full MonoCharts visual composition (nested card radius, opacity-based series, staggered entrance animation, custom tooltip) — deliberately deferred out of the Phase 20 PoC per D-01; applies starting Phase 21.
- Dark-mode toggle for the Filament panel itself (not just the chart components) — out of scope entirely for this milestone; charts are just built theme-flexible per D-02 in case it's added later.

### Reviewed Todos (not folded)

None — `gsd-tools todo match-phase 20` returned zero matches.

</deferred>

---

*Phase: 20-react-island-infrastructure*
*Context gathered: 2026-08-20*
