# Pitfalls Research

**Domain:** React micro-frontend island (Recharts + Motion) mounted via `wire:ignore` inside existing Filament v4 ChartWidgets, across 5 panels, some widgets polling every 120s
**Researched:** 2026-08-20
**Confidence:** MEDIUM — Livewire/Alpine `wire:ignore` semantics and laravel-vite-plugin multi-entry behavior are HIGH confidence (official docs + GitHub issues verified). React-specific island-in-Livewire integration is a narrow, under-documented pattern with little first-party guidance from either Laravel or React — those sections are reasoned from React's documented lifecycle/mounting API plus Livewire's documented DOM-diffing behavior (MEDIUM), not from a canonical "how to do this" source.

## Critical Pitfalls

### Pitfall 1: `wire:poll` re-renders the DOM node the React root is mounted on, leaving a stale/orphaned React tree

**What goes wrong:**
Livewire's polling re-render runs a Livewire/Alpine "morph" (DOM diff) pass on the whole component tree, including inside `wire:ignore` boundaries in one specific way: `wire:ignore` (without `.self` — irrelevant here) tells Livewire's morph step to skip diffing the *children* of that element, but it does NOT stop Livewire from touching the element itself if the ChartWidget's own root re-renders and that element is not the outermost node, or if the widget's Blade template re-keys/regenerates the wrapper on every poll (e.g. because the wrapping div's attributes depend on widget state that changes). If the wrapper element itself gets replaced (not just skipped), the React root created via `createRoot(container)` becomes attached to a DOM node that Livewire has now detached and thrown away — React keeps rendering into a node nobody sees, and any new data payload the poll delivered (e.g. fresh chart data serialized into a `data-*` attribute or a Livewire property) never reaches the still-live-but-orphaned React tree because the code path that would call `root.render(newProps)` fired against the wrong node or didn't fire at all.

**Why it happens:**
Filament `ChartWidget`/custom Blade widgets re-render their entire view on every `wire:poll` tick by default — that's how Livewire polling works, it's a full component re-render, not a partial patch. Developers assume `wire:ignore` on the React mount div means "Livewire will never touch this again," but it only protects the *inner* DOM Livewire would otherwise diff against nothing (since React owns it) — it does not create a stable channel for getting new data *into* React. Teams often build the island expecting one-way "mount once, ignore forever" behavior, then are surprised when the chart never updates after the first 120s poll, or updates only after a full page reload.

**How to avoid:**
- Put `wire:ignore` on a **stable outer wrapper div** that itself never changes attributes/keys across renders (no `wire:key` that changes, no conditional class binding from Livewire state) — Livewire must have zero reason to replace that node.
- Do not pass chart data through the DOM (`data-*` attributes re-read on poll) as the update channel — it doesn't fire predictably. Instead, expose an explicit JS bridge: dispatch a Livewire browser event (`Livewire.dispatch('chart-data-updated', {...})` from the widget's `getData()`/`updated()` lifecycle, or a lightweight `Livewire.hook('morph.updated', ...)` listener) that calls `root.render(<Chart data={newData} />)` on the *existing* React root, imperatively, independent of whether Livewire decided to morph the wrapper.
- Alternative (often simpler for this widget count): keep the wrapper as a genuinely static Blade partial rendered once, and let the React component itself poll data via a lightweight fetch to a dedicated JSON endpoint (or Livewire's own `$wire.entangle`/`$wire.call()` from Alpine) on its own interval — decoupling React's refresh cycle from Livewire's poll/morph cycle entirely avoids the coupling problem.
- Never call `createRoot()` more than once for the same physical container across the widget's lifetime; guard with a check (e.g. store the root instance on the DOM node itself, `container._reactRoot`) so a re-mount (e.g. Livewire's `livewire:navigated` SPA transition) reuses the same root via `root.render()` instead of creating a second root on the same node (React will warn and can produce duplicate listeners/memory growth).

**Warning signs:**
- Chart visually never updates on poll tick even though Network tab shows the Livewire poll request succeeding with new data.
- Browser console shows `You are calling ReactDOMClient.createRoot() on a container that has already been passed to createRoot() before` after several poll cycles.
- Memory profiler (Chrome DevTools) shows detached DOM node count climbing every ~120s while the tab is open (classic orphaned-root leak signature).

**Phase to address:**
Phase that builds the React↔Livewire data bridge / island infrastructure — before migrating any of the 3 existing polling ChartWidgets. This is the single highest-risk item in the whole milestone because all 3 first-wave widgets (`ValidationProgressChart`, `TerritorialDistributionChart`, `SurveyResultsWidget`) poll at 120s, and the Día D live line chart is explicitly polling-driven per the milestone's own feature list.

---

### Pitfall 2: `root.unmount()` never called, leaking React roots/listeners when Livewire removes or replaces the widget's DOM subtree

**What goes wrong:**
Livewire does not know a React tree exists inside a `wire:ignore` boundary. When Livewire removes that DOM subtree entirely — component removed from a conditional (`@if`), tab/panel switch that unmounts the widget, full-page SPA navigation via `wire:navigate`, or the user simply navigating away — the DOM node is destroyed by Livewire/the browser without React ever being told to clean up. `createRoot()` attaches event delegation, effect cleanup hooks, timers/intervals used by chart animation libraries (Recharts' internal resize observers, Motion's animation frame loops), and those keep running or leak because nothing called `root.unmount()`. Over a session with multiple panel navigations (very plausible here: 5 panels, users switching between Admin/Coordinator/Reports), this compounds into growing memory and, worse, React "setState on unmounted root" warnings or animation-frame loops that never stop, degrading performance the longer a SPA-navigated session runs.

**Why it happens:**
Livewire's `wire:navigate` (SPA-style navigation) and Alpine's own teardown are both designed around Alpine components (which have documented lifecycle hooks Livewire integrates with, e.g. `Alpine.destroy`). React has no automatic hook into Livewire's/Alpine's destroy lifecycle unless the integration code explicitly wires one. This is a well-known category of bug for every "mount a JS framework island inside another framework" pattern (Turbo+React has the identical class of bug, documented extensively in the Rails/Hotwire community) — but there's no first-party guidance for the Livewire+React combination specifically, so teams building this integration for the first time often skip writing the teardown hook entirely because nothing errors immediately.

**How to avoid:**
- Write a small reusable mount helper (one file, used by every chart component) that: (1) checks for an existing root on the container and reuses it, (2) registers a Livewire/Alpine teardown hook to call `root.unmount()`. Use Alpine's `x-init`/`destroy()` pattern via an `x-data` wrapper around the `wire:ignore` div (Alpine already integrates with Livewire's morph/removal lifecycle), so `Alpine.data('reactIsland', () => ({ init() { /* mount */ }, destroy() { root.unmount() } }))` gets called when Alpine tears the element down — this is the most reliable hook available, more reliable than trying to listen for Livewire's own removal.
- Additionally listen for `livewire:navigate` (fired before an SPA navigation swaps the page) and proactively unmount all live React roots tracked in a small registry, since `wire:navigate` can swap out the entire `<body>` without Alpine's per-element `destroy()` always being guaranteed to run first for every nested tree in every Livewire/Alpine version combo — belt-and-suspenders here is cheap and worth it given this ships across 5 panels.
- In dev, verify with React DevTools' "Highlight updates" plus manual navigation stress-testing (rapid switching between panels/widgets) rather than trusting it "looks fine" on first load.

**Warning signs:**
- Console warning: `Warning: Can't perform a React state update on an unmounted component` (or root) after navigating away from a page with a chart and back.
- Chrome DevTools Memory tab: taking two heap snapshots before/after 10 panel navigations shows retained `Fiber`/`FiberRoot` objects growing linearly.
- Animation stutter/CPU usage that increases the longer a session stays open in one browser tab without a hard refresh.

**Phase to address:**
Same infrastructure phase as Pitfall 1 — the mount/unmount helper needs to be built once, correctly, and reused by all downstream chart migrations. Retrofitting teardown logic after 6+ widgets already use a naive mount pattern is expensive; get this right in the foundational phase.

---

### Pitfall 3: React's synthetic event delegation intercepts/conflicts with Livewire and Alpine's native DOM listeners on ancestor elements

**What goes wrong:**
React (both legacy and the current root-based API) attaches most synthetic event listeners at the root container via delegation, not directly on each element — click/change/etc. bubble up through React's synthetic system before (or interleaved with) reaching native listeners Livewire/Alpine attached on ancestor elements (e.g. `wire:click` on a parent card wrapping the chart, or an Alpine `x-on:click.outside` used for a dropdown/modal that happens to wrap or sit near the chart). Two concrete failure patterns: (1) a native `stopPropagation()` call inside the React tree (or React's own internal handling) can prevent a Livewire `wire:click` listener on an ancestor from ever firing, because React's synthetic events run in its own dispatch pass that doesn't always compose cleanly with native bubbling depending on exact DOM structure; (2) conversely, Livewire/Alpine's morph step re-attaching native listeners on ancestor elements during a poll re-render can, in edge cases, disrupt the assumptions React's event delegation makes about a stable ancestor chain if the *ancestor* (not just the ignored child) gets replaced.

**Why it happens:**
This is a documented, general "mount framework X inside framework Y" gotcha (same class of issue reported for React-in-Turbo/Hotwire and React-in-Vue integrations) — synthetic event systems assume they own the tree they're delegating within, and most cross-framework advice under-specifies the actual DOM boundary. Because SIGMA's charts sit inside Filament widget cards that may have their own click handlers (e.g. "view details" wrapper, dropdown menus, tooltips using Alpine), the two systems' event listeners are more likely to compete for the same physical event than in a fully isolated island.

**How to avoid:**
- Keep the `wire:ignore` boundary as high as practically possible (wrap the *entire* widget card content in the ignored/React-owned subtree, not just an inner `<div id="chart">`), so there is no Livewire/Alpine-owned interactive element (buttons, dropdowns, `wire:click`) living *inside* the React subtree that could have its native listener shadowed by React's delegation.
- Do not attach `wire:click`/`x-on:click` to any DOM node that is itself inside the React-rendered tree — if a chart needs to trigger a Livewire action (e.g. "drill through" from a chart segment to a filtered table, mentioned as a target pattern in prior SIGMA dashboard work), do it via an explicit bridge: React calls `window.Livewire.find(id).call('methodName', args)` (or dispatches a browser CustomEvent that a Livewire component listens for with `#[On(...)]`) rather than relying on native DOM event bubbling to cross the boundary.
- Avoid nesting the React root inside an Alpine `x-data` scope that also has sibling `x-on` handlers on overlapping elements; keep Alpine-controlled interactive chrome (dropdown menus, filter toggles) as *siblings* of the React mount point, not ancestors/descendants of it.
- Test click-through behavior specifically where a chart sits near an existing dropdown/action menu (several widgets in this codebase have header actions) — don't just test the chart in isolation.

**Warning signs:**
- A `wire:click` button that renders correctly but silently does nothing when clicked, only reproducible when it's positioned near/inside the React-owned subtree.
- Alpine dropdown/tooltip that closes/opens erratically only on pages with the React chart present.
- Duplicate event firing (single click triggering both a Livewire action and a React handler) after a poll re-render.

**Phase to address:**
The widget-card/layout-integration phase (whichever phase decides where exactly the `wire:ignore` boundary sits relative to each widget's existing header/action chrome) — should be explicitly designed and reviewed, not left as an implementation detail per-widget, since it affects all 11 target visualizations.

---

### Pitfall 4: Vite multi-entry setup causes the React chunk to load on panels/pages that don't need it, or to not load at all on some panels

**What goes wrong:**
`laravel-vite-plugin` supports multiple entries in one `input: [...]` array, and Laravel's `@vite([...])` Blade directive can selectively include only the entries a given page needs — but Filament's panel/widget Blade views are rendered through Filament's own layout system, not hand-written page templates, so there is no single obvious place to add a targeted `@vite(['resources/js/charts.js'])` call per-panel. Two failure modes are common: (1) developers add the new entry to the *global* app layout (`@vite` call shared by all 5 panels) "to be safe," which means every panel — including ones with zero chart widgets on a given page — pays the React+Recharts+Motion bundle download/parse cost on every page load; (2) developers try to scope it to only pages that have the widget, get the Blade/Filament render-hook wiring wrong (Filament v4's `FilamentAsset::register()` / panel-level `renderHook()` API is the correct mechanism, not raw `@vite`), and end up with the entry missing entirely on some of the 5 panels — most likely the ones that reuse an existing shared widget class (`TerritorialDistributionChart` is explicitly reused across Admin/Coordinator/AreaCoordinator/Leader panels per existing role-scoping code) but where the panel's own `PanelProvider` was never updated to register the asset.
Separately: `laravel-vite-plugin` v2 writes ALL entries into a single manifest — this is fine for `npm run build`, but if any one entry fails to build (e.g. a TypeScript/JSX syntax error in the new chart code) the whole `vite build` can fail and block deployment of unrelated Livewire-only pages, since Laravel's Vite plugin has historically treated manifest generation as all-or-nothing per build.

**Why it happens:**
Filament v4 has its own asset-registration system (`FilamentAsset::register([Js::make(...)])` in a Panel/plugin's `boot()`) specifically so panels can each opt into different asset sets — but it's less commonly documented than plain `@vite()`, so it's easy for a first-time Filament+Vite integrator to reach for the simpler, wrong tool (global `@vite` in the shared layout) since it "just works" in the sense that nothing errors, it's just wasteful and, worse, silently correct-looking until someone audits Network tab payload size per panel.

**How to avoid:**
- Register the chart-island entry via Filament's asset system scoped per-panel (`FilamentAsset::register([Vite::make('resources/js/charts.tsx')], 'sigma/charts')` or equivalent, loaded conditionally inside each `PanelProvider::panel()` that actually has chart widgets registered) rather than the app-wide Blade layout — verify with each of the 5 `PanelProvider` classes explicitly, since the milestone's own target list confirms shared widget classes span multiple panels and it would be easy to update only the Admin panel provider and forget AreaCoordinator/Leader/Reports.
- Keep the React/Recharts/Motion entry as a genuinely separate Vite `input` from `app.js` (already the pattern here — `resources/js/app.js` is the sole JS entry today) so a build failure in the new chart code doesn't necessarily need to block deploys of the base app bundle; confirm this assumption against the actual `vite build` failure behavior before relying on it (test a deliberate syntax error locally and observe whether `npm run build` fails atomically or per-entry).
- Audit Network tab payload on a non-chart page (e.g. a plain CRUD resource page in each panel) after shipping, to confirm the React bundle is NOT being downloaded there.
- Watch Tailwind v4 utility-class collision: Recharts/Motion components often ship their own inline styles or CSS-in-JS-adjacent class names, but the more likely SIGMA-specific conflict is Tailwind's `@source`/content-scanning in v4 potentially not scanning `.tsx`/`.jsx` files by default if they live outside the paths Tailwind v4's automatic content detection covers — verify `resources/js/**/*.{jsx,tsx}` (wherever the React code lives) is actually included so Tailwind utility classes used *inside* React components get generated, otherwise chart wrapper styling silently produces unstyled/unclassed output in production (works in dev via Vite's JIT-ish behavior, breaks in the production Tailwind build if content scanning misses the new file glob).

**Warning signs:**
- Non-chart pages (e.g. a plain Voter list page) showing a multi-hundred-KB JS chunk in Network tab that wasn't there before this milestone.
- A specific panel (e.g. AreaCoordinator, which mirrors Coordinator but was historically the one that gets forgotten in cross-panel rollouts per this project's own history — see `TopLeadersTable`/`CampaignStatsOverview` gaps found in Phase 18/19) rendering a blank widget area or console 404 for a chart JS chunk while other panels work fine.
- Tailwind classes present in React component `className` props rendering with zero effect in production build only.

**Phase to address:**
Infrastructure phase (Vite entry + Filament asset registration setup), with an explicit per-panel checklist item — this project has a proven history (Phase 18/19 gap closures) of exactly this shape of bug: a shared widget/mechanism correctly built but not wired into every panel that needs it. Treat "verify asset registration on all 5 panels" as a first-class phase success criterion, not an assumption.

---

### Pitfall 5: False "hydration mismatch"-shaped bugs that aren't actually hydration (there is no SSR here) but look identical and get misdiagnosed

**What goes wrong:**
Because this is a client-only mount (`ReactDOM.createRoot(container).render(...)`, no `hydrateRoot`, no server-rendered React markup to reconcile against), there is no SSR/hydration step and therefore no possibility of a genuine React hydration-mismatch warning. However, a distinct but visually/behaviorally similar failure mode can occur and easily gets misdiagnosed as "hydration": Filament's Blade template renders a placeholder/skeleton `<div>` for the chart container (e.g. a loading spinner or the old Chart.js canvas markup left in place during incremental migration), then React mounts into that same container and replaces its children — if the Blade-rendered placeholder markup and React's initial render both reference overlapping DOM (e.g. React assumes an empty container but Blade left child nodes in it, such as an `<x-filament::loading-indicator>` inside the mount `<div>`), `createRoot().render()` will simply wipe and replace those children (which is normal, correct React behavior, not a bug) — but a developer coming from Next.js/SSR instincts may spend time debugging a "hydration mismatch" that structurally cannot occur here, wasting time on the wrong mental model.
A second, real (not false-positive) risk in this exact area: if the Blade wrapper is *itself* re-rendered by a Livewire poll (see Pitfall 1) while React has already mounted, and the developer's mount code does something like `if (!container.hasChildNodes())` to decide whether to call `createRoot` vs. reuse an existing root, a Livewire-driven DOM replacement can reset `hasChildNodes()` to true (Livewire re-renders the skeleton) causing the mount code to incorrectly skip re-creating the root, or incorrectly create a second one — this is a real bug but is caused by state-detection logic, not hydration.

**Why it happens:**
Team members most familiar with React likely have SSR/Next.js-flavored mental models (hydration warnings are one of the most common React footguns in that world), and will pattern-match any "DOM looked different than React expected" symptom to hydration even when the actual mechanism here is Livewire's morph/replace behavior colliding with React's mount-time DOM assumptions — a different, Livewire-specific mechanism that needs a different fix (see Pitfall 1/2's mount-once/reuse-root guidance).

**How to avoid:**
- Explicitly document (in code comments on the mount helper, and in the phase's implementation notes) that there is no SSR/hydration in this integration, so any DOM-mismatch-shaped bug should be triaged as a Livewire-morph/mount-lifecycle issue, not a hydration issue — this saves real debugging time given the team is new to React on this project.
- Keep the Blade-rendered placeholder markup for the chart mount point as minimal as possible (an empty `<div wire:ignore id="chart-x"></div>` with no nested loading indicator, no skeleton content) so there's nothing for React's initial render to "conflict" with, and use React's own loading state (rendered by React itself while data fetches) instead of a Blade-rendered skeleton — this also sidesteps the root-detection ambiguity entirely.
- Use a dedicated marker (e.g. `container.dataset.reactMounted = 'true'` set right after `createRoot()`) as the source of truth for "has this container already been mounted," rather than inferring it from `hasChildNodes()`/DOM content, which is exactly the kind of state Livewire can mutate underneath you.

**Warning signs:**
- Any bug report/investigation that uses the word "hydration" in this codebase should be treated as a signal to re-check the actual mechanism, since true hydration mismatches are structurally impossible here.
- Chart flickers/resets to a loading state on every poll tick even when the underlying data hasn't changed — sign of root re-creation rather than `root.render()` reuse.

**Phase to address:**
Infrastructure phase, as a documentation/convention note alongside the mount/unmount helper (Pitfall 1/2) — cheap to prevent, costly (in wasted debugging time) if not flagged up front.

---

### Pitfall 6: Pest 4 Browser tests assert on server-rendered HTML timing assumptions that don't hold for client-rendered React content

**What goes wrong:**
Pest 4's Browser testing (Playwright-backed) auto-waits for elements to be attached/visible/stable before most assertions (`assertSee()`, `click()`, etc.), which genuinely does cover most of the "React hasn't rendered yet" race condition automatically — this is a real strength, not a gap, and is well-suited to this migration. The actual pitfalls are narrower and more specific: (1) Recharts renders chart content into SVG, and specific data-driven text (e.g. a data label, a tooltip value, an axis tick) may be positioned/rendered lazily or only on hover/interaction — `assertSee('42%')` for a value that only appears in a tooltip on `:hover` will fail unless the test explicitly triggers the hover/interaction first, which is a real behavior difference from the old server-rendered Chart.js/Blade approach where such text might have been present in the static DOM (e.g. a legend) even without interaction; (2) Motion (Framer Motion successor) animations mean an element can be present in the DOM but visually mid-transition (opacity/transform still animating) when Playwright's "visible" check passes — usually harmless since Playwright checks bounding box + visibility not animation completion, but can cause visual-regression-style flakiness if a test takes a screenshot assertion mid-animation; (3) Filament's existing Livewire/Volt test helpers (`livewire()`, `Livewire::test()`) operate against the server-rendered Blade/Livewire component tree and its public properties/methods — they have no visibility into React component state at all, so any assertion that needs to "reach into" the chart (e.g. verifying a specific data point rendered correctly) MUST go through the Pest Browser/Playwright layer (DOM-level assertions), not the Livewire testing layer; a team mixing both styles on the same feature (common early on, before the pattern settles) will end up with Livewire tests that pass by asserting on the *data* passed to the widget/Livewire property, while never actually verifying the chart *rendered* that data correctly in the browser — a false sense of coverage.

**Why it happens:**
Existing SIGMA test suite conventions (per CLAUDE.md and prior phases) lean heavily on `livewire()`/`Livewire::test()` for Filament resource/widget coverage — that's the established, familiar pattern for this codebase. It's natural to reach for it first when writing "did the chart widget work" tests, and easy to stop at "the widget's `getData()` method returned the right array" without realizing that's necessarily incomplete for a React-rendered chart, since the actual rendering step now happens entirely outside PHP/Livewire's reach.

**How to avoid:**
- Establish a clear test-layer convention up front: Livewire/Pest Feature tests verify the *data contract* (what props/data get handed to the React component — e.g. assert the Livewire property or the JSON payload embedded for the bridge is correct), while Pest Browser tests verify the *rendered result* (the chart actually shows the right visual/text content in a real Chromium session) — treat these as complementary, not either/or, and require both for each migrated widget given this project's stated "voter and Day D flows require test protection" quality bar.
- For chart data assertions in Browser tests, prefer asserting on stable, always-visible DOM content (axis labels, legend text, a `data-testid`/`aria-label` attribute placed deliberately on chart elements by the React code) over interaction-dependent content (tooltips) where practical; when tooltip content genuinely needs testing, explicitly `hover()` the relevant SVG element first via Playwright's locator API before asserting.
- For Motion-animated elements, prefer waiting on a stable end-state signal (e.g. assert final data value/text, or add a `data-animation-complete` marker toggled by Motion's `onAnimationComplete` callback for tests to key off) rather than screenshot-based visual assertions, to avoid animation-timing flakiness; if visual regression testing is wanted, disable/shorten animations under a `?test=1`/env-flag mode.
- Recharts renders SVG — Playwright's text-content assertions (`assertSee`) work fine against SVG `<text>` nodes, but CSS selector-based assertions need SVG-aware selectors; verify this against a real spike before writing the full test suite for all 11 target visualizations, don't assume DOM assertion patterns from HTML apply identically.

**Warning signs:**
- A "passing" test suite where every browser test for a chart widget only asserts the widget/page loaded without error, never asserting on a specific rendered data value — a coverage-theater smell.
- Flaky Browser tests that pass/fail inconsistently specifically on the polling-driven or animated widgets (Día D live line chart, funnel/donut with entrance animations) but not on static ones — signals animation-timing or poll-timing races in the test itself, not necessarily a product bug.
- Livewire `Livewire::test()` assertions used as the *only* coverage for a migrated chart widget with no corresponding Pest Browser test — should be treated as incomplete coverage per this project's existing "highest-risk flows require test protection" constraint.

**Phase to address:**
Establish the test-layer convention in the infrastructure phase (before the first widget migration ships), then enforce it per-widget in each subsequent chart-migration phase's own success criteria — don't defer test-strategy decisions to whichever phase happens to touch testing last.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|--------------------|-----------------|------------------|
| Register the new Vite entry globally in the shared app layout instead of per-panel via `FilamentAsset` | Faster to wire up, works everywhere immediately | Every panel/page pays the React bundle cost even where no chart exists; masks missing-panel bugs (Pitfall 4) since it "just works" everywhere | Never for production — acceptable only as a throwaway local dev spike before building the real per-panel registration |
| Reading fresh poll data off `data-*` attributes instead of building a real event/bridge channel | Looks simpler, no new JS event wiring needed | Silently stale charts after first poll tick (Pitfall 1); very hard to debug later since nothing errors | Never — this is the exact trap Pitfall 1 describes |
| Skipping `root.unmount()` teardown wiring for the first widget migrated ("we'll add it later") | Ships the first chart faster | Every subsequent widget likely copies the same pattern (it's the reference implementation); retrofitting across 11 visualizations later is much more expensive than doing it once correctly upfront | Never — build the mount/unmount helper once in the infra phase, reuse everywhere |
| Using screenshot/visual-regression Browser tests as the primary coverage for animated (Motion) charts | Feels thorough, "sees" the actual rendered chart | High flakiness from animation timing; slows CI; false failures erode trust in the suite | Acceptable only as a supplementary spot-check, never as primary coverage for animated elements |
| Leaving old Chart.js-based `ChartWidget` markup in place alongside new React mount point "just in case," instead of a clean cutover per widget | Feels like a safety net during migration | Creates exactly the DOM-conflict conditions described in Pitfall 5 (stale skeleton markup colliding with React's mount assumptions) | Acceptable only behind a feature flag that fully removes/replaces the Blade markup when the flag flips, never as permanently coexisting markup |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|------------------|--------------------|
| Livewire `wire:poll` + React root | Assuming `wire:ignore` alone keeps the React tree "live" and up to date across polls | Build an explicit data-push bridge (Livewire browser event → `root.render()`), verified per Pitfall 1 |
| Livewire SPA navigation (`wire:navigate`) + React root cleanup | Assuming Livewire/Alpine automatically tears down React roots when navigating away | Wire an explicit Alpine `destroy()`/`livewire:navigate` listener that calls `root.unmount()` (Pitfall 2) |
| Filament v4 panel asset registration | Using plain `@vite()` in a shared layout instead of `FilamentAsset::register()` scoped per panel | Register the chart entry per `PanelProvider` that actually has chart widgets; verify across all 5 panels explicitly |
| Tailwind v4 content scanning + React `.tsx`/`.jsx` files | Assuming Tailwind v4's automatic content detection already covers wherever the new React files live | Explicitly verify (or add via `@source`) that the React source directory is included in Tailwind's scan glob |
| Recharts SVG rendering + Playwright DOM assertions | Writing HTML-oriented CSS selectors that don't match against SVG `<text>`/`<g>` structure | Spike one chart's Browser test early to confirm selector/assertion patterns work against Recharts' actual SVG output before writing the other 10 |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|------------------|
| New `createRoot()` call on every poll tick instead of reusing existing root | Growing detached-DOM-node count in DevTools Memory profiler; visible chart flicker every poll | Guard mount code with an existing-root check (store root reference on the container node or in a module-level registry keyed by widget id) | Noticeable after minutes on any 120s-polling widget in a long-lived tab; severe on the Día D 10s/live-poll widget mentioned in the milestone (`VoteRecord.voted_at` live line, likely fast-polling on election day itself when the tab may stay open for hours) |
| Shipping Recharts + Motion as part of the app-wide bundle instead of a route/panel-scoped chunk | Slower first paint on every panel, even ones with zero charts | Keep the chart entry as a separate Vite input, loaded only where needed (Pitfall 4) | Becomes noticeable once more than a couple of the 11 target visualizations ship and the bundle grows; worst on lower-bandwidth field conditions relevant to campaign/Day D usage |
| Re-computing/re-serializing full chart datasets on every 120s poll even when underlying data hasn't changed | Unnecessary server load + payload size on widgets like `TerritorialDistributionChart` reused across 5 panels concurrently by many users | Consider caching `getData()` output for the polling interval window, or comparing a lightweight version/hash before pushing new data into React | Matters more as concurrent active users across panels grows; low risk at current campaign-team scale but cheap to prevent now |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Serializing chart data into a global `window.__CHART_DATA__` variable or an inline `<script>` blob without campaign scoping context | A React component reading global window state instead of scoped Livewire/Blade-passed props could leak data across the campaign-isolation boundary this project treats as non-negotiable, especially since some widgets are literally reused across 5 different panels with different role-based scoping | Always pass chart data through the same campaign/role-scoped channel the existing widget's `getData()` already uses (Livewire component properties or a per-widget-instance data payload), never a single shared global variable that multiple concurrently-mounted widget instances (e.g. same widget class on two different panels in different browser tabs of different users) could collide on |
| Trusting client-side React state as a source of truth for any drill-through/action that mutates data | A chart segment click that calls back into a Livewire action needs the same server-side authorization the widget's PHP class already enforces | Route any chart-triggered action through the existing Livewire/Filament authorization path (policies, campaign scoping), never let React itself decide what a click is "allowed" to do beyond triggering a properly-authorized server call |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-------------------|
| Chart shows a jarring full flash/reset (flicker to empty/loading state) on every poll tick due to root re-creation (Pitfall 1/5) | Operators watching a Día D live chart during election day see it "reset" every interval, undermining trust in the live-data feature the milestone is explicitly building to expose | Reuse the existing root and animate data transitions (Recharts + Motion support this natively) instead of a full remount per poll |
| Loading skeleton mismatch between Blade's placeholder and React's own loading state, producing a double-flash (Blade skeleton → blank → React skeleton → content) | Feels janky, slower than the old Chart.js widgets it's replacing | Single source of loading UI — either Blade renders nothing but the mount point and React owns all loading states, or vice versa, never both |
| Chart animations (Motion) fire distractingly on every 120s poll refresh even when data hasn't materially changed | Operators repeatedly re-triggered by animation on a dashboard they're passively monitoring find it distracting over a long session | Only animate on genuine data changes (diff old vs new values) or on true mount, not on every poll regardless of whether data changed |

## "Looks Done But Isn't" Checklist

- [ ] **Chart updates after poll:** Often looks done because the chart renders correctly on first page load — verify by leaving the tab open across at least 2 full poll intervals (e.g. 240s+ for the 120s widgets) and confirming the chart visually updates with new data, not just that no error appears.
- [ ] **Cross-panel asset registration:** Often looks done after testing only the Admin panel — verify the chart renders (and its JS/CSS actually loads, check Network tab) on all 5 panels that register the relevant widget class (Admin, Coordinator, AreaCoordinator, Leader, Reports), per this project's own documented history of exactly this class of gap (Phase 18/19).
- [ ] **Root cleanup on navigation:** Often looks done because a single page load/unload via full browser refresh works fine — verify specifically via Livewire's `wire:navigate` SPA-style in-app navigation (if enabled in this app) between a chart page and a non-chart page and back, several times, checking for console warnings and memory growth.
- [ ] **Role-scoped chart data correctness:** Often looks done because the chart renders *something* for each role — verify the actual data values differ correctly per role (e.g. AreaCoordinator sees only their transitive team's data, not campaign-wide), mirroring the exact gap class already found and fixed for `CampaignStatsOverview`/`TerritorialDistributionChart` in Phase 19 — the React rewrite must not silently regress that already-hard-won scoping correctness.
- [ ] **Tooltip/interaction-only data has a non-interactive equivalent path for tests and accessibility:** Often looks done visually (hover shows the tooltip) but has zero automated coverage and zero screen-reader-accessible equivalent — verify a Browser test actually exercises the hover/interaction path, not just static content.
- [ ] **Build failure isolation:** Often assumed but unverified — actually break the new chart entry's build (deliberate syntax error) locally and confirm whether `npm run build` fails atomically (blocking deploys of the unrelated Livewire-only app bundle) or per-entry, so the team knows the real blast radius of a broken chart PR before it happens in CI/production.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|-----------------|-------------------|
| Stale/orphaned React root after poll (Pitfall 1) | MEDIUM | Retrofit the mount helper's root-reuse + explicit data-bridge pattern into the affected widget; requires re-testing every panel that reuses the widget class |
| Missing `root.unmount()` causing leaks (Pitfall 2) | MEDIUM | Add the Alpine `destroy()`/`livewire:navigate` teardown hook to the shared mount helper (fixes all widgets using it at once, since it's centralized) |
| Wrong Vite asset scoping (global instead of per-panel, or missing on a panel) | LOW | Move the `@vite`/`FilamentAsset` registration to the correct per-panel location; no data/schema impact, pure asset-loading fix |
| Event bubbling conflict between React and Livewire/Alpine (Pitfall 3) | MEDIUM-HIGH | May require restructuring the DOM boundary (widen or narrow the `wire:ignore` scope) and re-wiring any cross-boundary actions through the explicit bridge pattern instead of native DOM events — touches markup, not just JS |
| Coverage-theater tests (Livewire-only, no Browser assertion on rendered chart) | LOW-MEDIUM | Add the missing Pest Browser test per widget; doesn't require product code changes, just test-suite completion |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|--------------------|-----------------|
| Stale React root on `wire:poll` (Pitfall 1) | Infrastructure/island-foundation phase | Leave a migrated polling widget open across 2+ poll intervals in a real browser and confirm visual data update, plus a Pest Browser test asserting rendered content changes after a triggered poll |
| Missing `root.unmount()` (Pitfall 2) | Infrastructure/island-foundation phase | Memory-profile a session with repeated in-app navigation across panels; confirm no growing detached-node count and no unmounted-root console warnings |
| React/Livewire event conflicts (Pitfall 3) | Widget-layout/DOM-boundary design phase, reviewed before first widget migration | Manual click-through test of every interactive element (dropdowns, drill-through actions) on a page containing a migrated chart, across at least 2 widgets that have adjacent Filament header actions |
| Vite multi-entry / per-panel asset gaps (Pitfall 4) | Infrastructure phase, explicit per-panel checklist | Network tab audit on a non-chart page per panel (bundle absent) and a chart page per panel (bundle present, chart renders) — all 5 panels |
| False hydration-shaped bugs (Pitfall 5) | Infrastructure phase (documentation/convention) | Code review checklist item on the mount helper: "no `hasChildNodes()`-based mount detection, uses explicit marker instead" |
| Coverage-theater testing gap (Pitfall 6) | Infrastructure phase (test-layer convention) then enforced per widget-migration phase | Each widget-migration phase's success criteria explicitly requires both a Livewire/Feature data-contract test AND a Pest Browser rendered-content test |

## Sources

- [Livewire wire:poll official docs (v3.x)](https://livewire.laravel.com/docs/3.x/wire-poll) — polling behavior, `.keep-alive` modifier — HIGH confidence
- [Livewire v3 wire:ignore not working — GitHub Discussion #5813](https://github.com/livewire/livewire/discussions/5813) — confirms `wire:ignore` semantics changed/are less documented in v3 vs v2 — MEDIUM confidence
- [Livewire v3 Lazy, Islands & Deferred Loading — Mohamed Said](https://msaied.com/articles/livewire-v3-lazy-components-islands-and-deferred-loading-in-practice) — general island-pattern context in Livewire v3 — MEDIUM confidence
- [wire:ignore with dynamic data — GitHub Issue #1878](https://github.com/livewire/livewire/issues/1878) — real-world case of `wire:ignore` + changing data — MEDIUM confidence
- [Livewire Interfering With AlpineJS Data Reactivity — GitHub Discussion #7788](https://github.com/livewire/livewire/discussions/7788) — precedent for morph-vs-third-party-JS conflicts, recommends `wire:ignore.self` — MEDIUM confidence
- [Livewire and Alpine.js x-on:click event listeners not synchronized after DOM update — GitHub Issue #2046](https://github.com/livewire/livewire/issues/2046) — precedent for event-listener desync after Livewire re-renders — MEDIUM confidence
- [Laravel 12.x Vite docs](https://laravel.com/docs/12.x/vite) — multi-entry, manifest behavior — HIGH confidence
- [laravel/vite-plugin Issue #212 — manifest not building properly on first deploy](https://github.com/laravel/vite-plugin/issues/212) — real-world manifest generation gotcha — MEDIUM confidence
- [Pest Browser Testing official docs](https://pestphp.com/docs/browser-testing) — auto-waiting behavior, timeout defaults — HIGH confidence
- [Pest 4 Browser Testing: One Suite for Unit, Feature, and the Browser — Shocm's Blog (2026-07-04)](https://shocm.me/posts/2026-07-04-pest-4-browser-testing-laravel/) — recent (current) practical usage patterns — MEDIUM confidence
- [Pest 4 Browser Testing with Playwright in Laravel — RichDynamix](https://richdynamix.com/articles/pest-4-browser-testing-playwright-laravel) — practical patterns — MEDIUM confidence
- Project codebase inspection (`app/Filament/Widgets/ValidationProgressChart.php`, `TerritorialDistributionChart.php`, `SurveyResultsWidget.php`, `app/Providers/Filament/*PanelProvider.php`, `vite.config.js`, `package.json`) — confirms current `ChartWidget`/Chart.js baseline, 120s polling intervals on the two named widgets, and 5-panel registration — HIGH confidence (direct source inspection)
- `.planning/PROJECT.md` — confirms Phase 18/19 precedent of cross-panel scoping gaps on these exact shared widget classes, informing the "looks done but isn't" cross-panel checklist item — HIGH confidence (direct source inspection)
- General framework-island precedent (React-in-Turbo/Hotwire, React-in-Vue mount/unmount and event-delegation issues) — reasoned from well-established, widely-documented cross-framework integration patterns rather than a single citable source specific to this Livewire+React combination — MEDIUM confidence, flagged as the weakest-sourced section; validate against actual behavior with a small spike before committing to the full migration plan

---
*Pitfalls research for: React micro-frontend island (Recharts + Motion) inside Livewire/Filament v4 multi-panel admin app*
*Researched: 2026-08-20*
