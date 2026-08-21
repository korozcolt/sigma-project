# Phase 21: Migrate Existing Charts to React/Recharts - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-20
**Phase:** 21-migrate-existing-charts-to-react-recharts
**Areas discussed:** Sparkline migration strategy, Visual fidelity level, Color palette, SurveyResultsWidget's dynamic chart type

---

## Sparkline migration strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated small ChartWidgets | Pull the sparkline out of the Stat, keep StatsOverviewWidget's numeric Stats as-is, place a new small React-mounted ChartWidget next to it in the panel's widgets([...]) array. Reuses the same proven Phase 20 pattern. | ✓ |
| Custom Stat-shaped Blade partial | Keep the sparkline embedded inside the Stat cell itself, matching today's exact layout. Requires a custom Stat rendering path since Stat has no wire:poll-per-item primitive. | |

**User's choice:** Dedicated small ChartWidgets (recommended option)
**Notes:** Matches research's own recommendation (ARCHITECTURE.md line 208) — lower risk, reuses proven plumbing.

---

## Visual fidelity level

| Option | Description | Selected |
|--------|-------------|----------|
| Full MonoCharts polish now | Adopt real MonoCharts composition now: nested card shell, rounded/monochrome bars, header/footer chrome, staggered entrance animation via Motion. | ✓ |
| Minimal re-skin only | Swap Chart.js for Recharts but keep today's current visual look, no new chrome/animation; full polish deferred to a later pass. | |

**User's choice:** Full MonoCharts polish now (recommended option)
**Notes:** Matches Phase 20's D-01 framing ("layered on starting Phase 21"); avoids a second re-skin pass later once Phase 22+ ships new charts with full MonoCharts styling.

---

## Color palette

| Option | Description | Selected |
|--------|-------------|----------|
| Adopt MonoCharts palette | Replace ad hoc hardcoded hex colors with MonoCharts' monochrome/rounded palette, consistent with the full-polish decision. | ✓ |
| Keep existing hex colors | Preserve today's specific colors (green for validated, blue for total, etc.) even under new visual chrome. | |

**User's choice:** Yes, adopt MonoCharts palette (recommended option)
**Notes:** Direct consequence of the visual fidelity decision — old ad hoc colors under new MonoCharts chrome would look mismatched.

---

## SurveyResultsWidget's dynamic chart type

| Option | Description | Selected |
|--------|-------------|----------|
| Keep dynamic switching, unchanged behavior | getChartKind() still returns 'pie'/'bar' based on question_type at render time. ChartRouter supports chartKind varying per-instance. No user-facing behavior change. | ✓ |
| Flag as deferred idea for later simplification | Don't decide now; note for a possible future product/UX change. | |

**User's choice:** Keep dynamic switching, unchanged behavior (recommended option)
**Notes:** Pure re-plumbing requirement, not a product decision — getData()/behavior must stay unchanged per MIGR-01.

---

## Claude's Discretion

- Exact naming/placement of the new small sparkline ChartWidget subclasses, and how the old Stat::chart() sparkline is removed/replaced during the swap.
- ChartRouter.tsx internal component structure and file layout under resources/js/charts/.
- Exact MonoCharts color tokens/CSS variables adopted for the new palette.
- Whether legend/tooltip/interaction-mode parity uses Recharts' native props or a MonoCharts-style custom tooltip component.
- Which panel(s) register the new small sparkline widgets.

## Deferred Ideas

- Any new chart type, data source, or business insight (belongs to Phase 22+).
- Simplifying/changing SurveyResultsWidget's dynamic pie/bar switching behavior — kept as-is per D-04, any change would be a separate product decision.
