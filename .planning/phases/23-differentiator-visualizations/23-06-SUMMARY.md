---
phase: 23-differentiator-visualizations
plan: 06
subsystem: ui
tags: [recharts, react, funnel-chart, filament-widgets]

# Dependency graph
requires:
  - phase: 23-differentiator-visualizations
    provides: FunnelChart.jsx (built in 23-01), VoterHappyPathFunnelChart (VIZ-06, built in 23-02)
provides:
  - FunnelChart.jsx now renders outside-right LabelList text unwrapped, on a single line, regardless of widget column width or funnel narrowing ratio
  - Real root-cause fix (not a test relaxation) for the VoterHappyPathFunnelChartTest gap tracked since 23-03
affects: [23-differentiator-visualizations, 24-day-d-live-voting-visualization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Custom LabelList `content` render function bypasses Recharts' Text.js clamp/word-wrap entirely for outside-positioned Cartesian labels (Funnel, and by extension Bar/etc.) when a fixed margin can't guarantee enough room for arbitrary label length"

key-files:
  created: []
  modified:
    - resources/js/charts/components/FunnelChart.jsx
    - .planning/phases/23-differentiator-visualizations/deferred-items.md

key-decisions:
  - "FunnelChart.jsx's LabelList position=\"right\" replaced with a custom `content` render function (FunnelRightLabel) that renders a plain unclamped <text>, since Recharts' clamp width for outside-right labels is a fixed proportion of the plot's own realWidth (set only by the ratio between adjacent funnel stage values) and cannot be reliably fattened via margin.right - increasing margin.right actually shrinks realWidth and shrinks that room further"
  - "margin.right reduced from the plan's originally-committed 168 down to a modest 24 (a gutter, not a label-fitting budget) since the clamp-width math showed a bigger margin works against label room, not for it"

requirements-completed: [VIZ-06]

# Metrics
duration: 10min
completed: 2026-08-21
---

# Phase 23 Plan 06: FunnelChart Label-Overflow Gap Closure Summary

**Fixed VoterHappyPathFunnelChart's real stage-label word-wrap bug in FunnelChart.jsx via an unclamped custom LabelList content renderer, after confirming in a real browser that the plan's originally-proposed margin.right fix did not actually work**

## Performance

- **Duration:** 10 min
- **Started:** 2026-08-21T16:50:00Z
- **Completed:** 2026-08-21T17:00:00Z
- **Tasks:** 2
- **Files modified:** 2 (FunnelChart.jsx, deferred-items.md)

## Accomplishments
- Diagnosed and fixed the real root cause of `VoterHappyPathFunnelChartTest`'s deterministic `assertSee('Pendiente de Revisión')` failure, tracked as a known gap since plan 23-03
- Confirmed via direct browser screenshot inspection that the plan's literal `margin.right=168` + `fontSize={12}` fix did not resolve the wrap (row 0's label still split across 3 `<tspan>` lines with no space between words)
- Traced the real cause through recharts@3.10.1's own source (`getCartesianPosition.js`, `Funnel.js`, `Label.js`, `LabelList.js`, `Text.js`) to a structural limitation: the outside-right label's clamp width is a fixed proportion of the plot's `realWidth`, itself shrunk by `margin.right` — meaning a bigger margin cannot reliably reserve fixed pixel space for an arbitrary label length, and can make the widest row's label room smaller
- Replaced the default `LabelList` render path with a custom `content` function that renders a plain `<text>` element at Recharts' own computed x/y, bypassing `Text.js`'s width-based word-wrap entirely — guaranteeing every stage label always renders as one line with correct spacing, independent of widget column width
- Verified the fix closes the gap deterministically: 2 consecutive `VoterHappyPathFunnelChartTest` passes, both shared `FunnelChart.jsx` consumers' tests (`CallContactabilityFunnelChartTest`, `MessageDeliveryFunnelChartTest`) still pass unchanged, and the full `tests/Browser/` suite (23/23) passes with zero new cross-test pollution
- VIZ-06 (and Phase 23's full 5/5 requirement set) is now genuinely closed — no test assertion was weakened or removed to get there

## Task Commits

Each task was committed atomically:

1. **Task 1: Reserve label width in FunnelChart.jsx** (the plan's literal margin.right=168 + fontSize={12} fix) - `0b04145` (fix)
2. **Task 2: Re-verify the fix closes the gap deterministically** - the literal Task 1 fix did not pass the required 2-consecutive-run verification (screenshot-confirmed word-wrap persisted); root-caused and re-fixed with an unclamped custom `content` renderer - `9811f94` (fix, deviation)

**Plan metadata:** `ad0fcdc` (docs: close out deferred-items.md gap-closure note)

## Files Created/Modified
- `resources/js/charts/components/FunnelChart.jsx` - `LabelList position="right"` replaced with a custom `content` render function (`FunnelRightLabel`) rendering a plain, unclamped `<text>`; `margin.right` reduced from the plan's 168 to a modest 24 gutter
- `.planning/phases/23-differentiator-visualizations/deferred-items.md` - appended a closure note under a new "Closed by plan 23-06" heading, documenting the real root cause and fix, preserving the 23-03/23-05 historical entries unchanged

## Decisions Made
- FunnelChart.jsx's outside-right label rendering now bypasses Recharts' built-in clamp/word-wrap machinery via a custom `content` render function, rather than relying on any `margin`/`fontSize` tuning — this is the only reliable way to guarantee a single-line label given Recharts' clamp-width formula for `position="right"` (see Deviations below for the full math).
- `margin.right` was reduced to 24 (from the plan's originally-committed 168) since a larger margin doesn't reserve label space in this position — it actively shrinks the very same `realWidth` the clamp-width proportion is derived from.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The plan's own proposed fix (margin.right=168 + fontSize=12) did not work; replaced with an unclamped custom LabelList content renderer**
- **Found during:** Task 2 (required 2-consecutive-run re-verification)
- **Issue:** After committing Task 1's literal fix and rebuilding assets, `VoterHappyPathFunnelChartTest`'s `assertSee('Pendiente de Revisión')` still failed. A browser screenshot of the actual rendered dashboard showed "Pendiente de Revisión" still wrapped across 3 `<tspan>` lines ("Pendiente" / "de" / "Revisión") with no space between the wrapped words — the real bug was never visual clipping, it was the DOM's rendered textContent reading `"PendientedeRevisión"`, which can never substring-match the assertion regardless of the widget's visible width. Reading recharts@3.10.1's source directly confirmed the structural cause: `getCartesianPosition.js`'s "right" case computes clamp `width = parentViewBox.right - x`, where `x` (and the trapezoid geometry it derives from) is proportional to the *same* `realWidth` (plot width minus `margin.left`/`margin.right`) the funnel itself is drawn at (`Funnel.js`'s `computeFunnelTrapezoids()`). Working out the exact ratio (`room = realWidth * (0.5 - 0.25 * (ratio_i + ratio_next_i))` for row `i`) showed the clamp room available to the widest (first) row is a fixed proportion of `realWidth` set purely by how much the funnel narrows between adjacent stages — and since `realWidth` itself *shrinks* as `margin.right` grows, increasing the margin further (per the plan's own documented contingency: "increase FUNNEL_MARGIN.right further, e.g. to 190-200") would have made the available room *smaller*, not bigger, for any campaign where the first two happy-path stages are close in count (the realistic, common case).
- **Fix:** Replaced the plain `<LabelList position="right" ... />` with a custom `content` render function (`FunnelRightLabel`) that renders a bare `<text>` element at the exact x/y Recharts computes for the label position (confirmed via `Label.js`: a function `content` receives only `x`/`y`, never the clamp `width`/`height` used by the default `Text` component's word-wrap path), so the label is now structurally incapable of wrapping — it always renders as a single line with its real spacing intact, regardless of the widest row's proportional clamp room. `margin.right` was reduced from 168 to 24 (a modest gutter, since the clamp-width math showed a bigger margin doesn't help, and could hurt).
- **Files modified:** `resources/js/charts/components/FunnelChart.jsx`
- **Verification:** `php artisan test tests/Browser/VoterHappyPathFunnelChartTest.php` passed on 2 consecutive runs; `CallContactabilityFunnelChartTest`/`MessageDeliveryFunnelChartTest` (the 2 other `FunnelChart.jsx` consumers) still passed unchanged; full `tests/Browser/` suite passed (23/23, zero new pollution)
- **Committed in:** `9811f94`

---

**Total deviations:** 1 auto-fixed (Rule 1 - the plan's own proposed fix, applied first exactly as written in Task 1's commit `0b04145`, was verified not to work and was corrected with a structurally different but narrowly-scoped fix to the same single file)
**Impact on plan:** The deviation stayed within the plan's own `files_modified` scope (`FunnelChart.jsx` only) and honored the plan's hard constraint not to weaken or remove the test's `assertSee('Pendiente de Revisión')` assertion. No scope creep — `CallContactabilityFunnelChart`/`MessageDeliveryFunnelChart` needed zero changes, matching the plan's original expectation.

## Issues Encountered
Same as the deviation above — the plan's literal fix required root-causing and iterating past what the plan itself had bounded ("increase FUNNEL_MARGIN.right further... until it passes deterministically for real"), because further increasing the margin would have made the underlying clamp-width math worse, not better. Resolved by reading recharts' own source to find a fix that structurally guarantees no wrap, rather than continuing to tune a margin value that could never reliably work for this label position.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- VIZ-06 fully closed with real, unweakened Browser test coverage — Phase 23's full 5/5 requirement set (VIZ-06 through VIZ-10) is now genuinely done, with its last known gap resolved
- `FunnelChart.jsx`'s custom unclamped-label pattern is available as precedent for any future outside-positioned Cartesian label that needs to guarantee no word-wrap regardless of available clamp room (e.g. if Phase 24's Día D chart or any future funnel/bar variant needs long labels in a narrow column)
- No blockers for Phase 24 (Día D Live Voting Visualization)

---
*Phase: 23-differentiator-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

- FOUND: resources/js/charts/components/FunnelChart.jsx
- FOUND: .planning/phases/23-differentiator-visualizations/deferred-items.md
- FOUND: .planning/phases/23-differentiator-visualizations/23-06-SUMMARY.md
- FOUND: commit 0b04145
- FOUND: commit 9811f94
- FOUND: commit ad0fcdc
