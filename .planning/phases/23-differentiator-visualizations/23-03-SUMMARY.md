---
phase: 23-differentiator-visualizations
plan: 03
subsystem: ui
tags: [filament, recharts, react, chartjs, admin-dashboard, validation-history]

# Dependency graph
requires:
  - phase: 23-differentiator-visualizations (plan 01)
    provides: React chart island infrastructure (ChartRouter.jsx, SankeyChart.jsx, StackedAreaChart.jsx, react-chart.blade.php)
  - phase: 23-differentiator-visualizations (plan 02)
    provides: AdminPanelProvider's ->widgets([...]) array state after VoterHappyPathFunnelChart/VoterLifecycleBranchCountersOverview registration
provides:
  - ValidationHistorySankeyChart admin widget (VIZ-07) - curated top-N + Otros Sankey of ValidationHistory state transitions
  - RejectionReasonsStackedAreaChart admin widget (VIZ-10) - weekly-bucketed 4-series stacked area of rejection reasons
  - A migration making validation_histories.previous_status nullable (unblocks the "Nuevo" synthetic source node decision for any future consumer)
affects: [23-04, 23-05, any future phase touching AdminPanelProvider's widgets array or ValidationHistory]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ValidationHistory campaign-scoping join always qualifies columns with the real table name 'validation_histories' (plural, no $table override on the model) - not the singular class-derived name"
    - "Recharts Sankey custom node/link render props must explicitly carry Recharts' own semantic className (e.g. recharts-sankey-node) since customizing node/link bypasses Recharts' automatic default className application"

key-files:
  created:
    - app/Filament/Widgets/ValidationHistorySankeyChart.php
    - app/Filament/Widgets/RejectionReasonsStackedAreaChart.php
    - database/migrations/2026_08_21_160104_make_previous_status_nullable_on_validation_histories_table.php
    - tests/Browser/ValidationHistorySankeyChartTest.php
    - tests/Browser/RejectionReasonsStackedAreaChartTest.php
    - .planning/phases/23-differentiator-visualizations/deferred-items.md
  modified:
    - app/Providers/Filament/AdminPanelProvider.php
    - resources/js/charts/components/SankeyChart.jsx

key-decisions:
  - "ValidationHistory has no $table override, so its real table is validation_histories (plural) - both widgets' join/select/groupBy/whereIn qualifiers were fixed from the plan's literal 'validation_history' (singular) to match"
  - "validation_histories.previous_status made nullable via a new migration, mirroring the existing validated_by-nullable migration on the same table, to unblock D-06's null-previous_status-as-Nuevo-node decision from ever being persistable"
  - "SankeyChart.jsx's custom node Rectangle element now carries an explicit className='recharts-sankey-node' since Recharts only auto-applies that class on its own un-customized default node renderer"

requirements-completed: [VIZ-07, VIZ-10]

# Metrics
duration: 55min
completed: 2026-08-21
---

# Phase 23 Plan 03: ValidationHistory Sankey + Rejection Reasons Stacked Area Summary

**Admin-only Sankey of curated ValidationHistory state transitions (top-8 + per-source Otros collapse, synthetic Nuevo node) and a weekly 4-series stacked-area of rejection reasons, both wired into AdminPanelProvider alongside 23-02's widgets**

## Performance

- **Duration:** 55 min
- **Started:** 2026-08-21T15:21:00Z
- **Completed:** 2026-08-21T16:16:45Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments
- `ValidationHistorySankeyChart` (VIZ-07): top-N-by-volume Sankey with a per-source-node "Otros" collapse and a synthetic "Nuevo" node for initial-registration transitions
- `RejectionReasonsStackedAreaChart` (VIZ-10): 4 fixed-order VoterStatus rejection series bucketed by week in PHP/Carbon (never raw SQL, avoiding MySQL/sqlite week-numbering drift)
- Both widgets registered on the Admin dashboard, verified with real-Chromium Browser tests asserting genuine `.recharts-sankey-node`/`.recharts-sankey-link`/`.recharts-area` elements, not just container presence

## Task Commits

1. **Task 1: ValidationHistorySankeyChart.php** - `7f75c09` (feat)
2. **Task 2: RejectionReasonsStackedAreaChart.php** - `a674a01` (feat)
3. **Task 3: Register both on Admin dashboard + Browser tests** - `491dabe` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Filament/Widgets/ValidationHistorySankeyChart.php` - Curated Sankey aggregation of ValidationHistory transitions
- `app/Filament/Widgets/RejectionReasonsStackedAreaChart.php` - Weekly-bucketed rejection-reason stacked area
- `app/Providers/Filament/AdminPanelProvider.php` - Registers both widgets after 23-02's VoterLifecycleBranchCountersOverview, before BirthdayWidget
- `resources/js/charts/components/SankeyChart.jsx` - Custom node Rectangle now carries an explicit `recharts-sankey-node` className
- `database/migrations/2026_08_21_160104_make_previous_status_nullable_on_validation_histories_table.php` - Makes `previous_status` nullable
- `tests/Browser/ValidationHistorySankeyChartTest.php` - Real-Chromium proof of non-degenerate Sankey render
- `tests/Browser/RejectionReasonsStackedAreaChartTest.php` - Real-Chromium proof of stacked-area render across 2 weeks
- `.planning/phases/23-differentiator-visualizations/deferred-items.md` - Logs an unrelated pre-existing failure found during regression sweep

## Decisions Made
- `previous_status` nullability gap: rather than silently dropping D-06 (23-CONTEXT.md's explicit "null previous_status renders as Nuevo node" decision), added a small, precedented migration to make it actually persistable — matches this same table's existing `validated_by`-nullable migration pattern exactly.
- Fixed the table-qualifier bug (`validation_history` → `validation_histories`) in both widgets' queries rather than adding a `protected $table` override to the `ValidationHistory` model — the model's implicit pluralized table name is already the established convention used everywhere else in the codebase; the bug was in the plan's literal query code, not the model.
- Explicit `className` on `SankeyChart.jsx`'s custom `Rectangle` node rather than reverting to Recharts' un-customized default node renderer — preserves the intended themed fill/radius styling while restoring the semantic `recharts-sankey-node` class real Browser tests (this plan's and any future consumer's) need to select against.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Both widgets' queries qualified columns with the wrong table name**
- **Found during:** Task 3 (Browser test verification)
- **Issue:** The plan's literal code for both `ValidationHistorySankeyChart::getData()` and `RejectionReasonsStackedAreaChart::getData()` qualified join/select/groupBy/whereIn columns as `'validation_history.*'` (singular), but `ValidationHistory` has no `$table` override, so its real table is `validation_histories` (plural, per `2025_11_03_171233_create_validation_histories_table.php`). Every query threw `SQLSTATE[HY000]: no such column: validation_history.previous_status`.
- **Fix:** `sed`-replaced all `'validation_history.` qualifiers with `'validation_histories.` in both widget files.
- **Files modified:** `app/Filament/Widgets/ValidationHistorySankeyChart.php`, `app/Filament/Widgets/RejectionReasonsStackedAreaChart.php`
- **Verification:** Both Browser tests pass; a disposable debug Feature test (deleted before commit) confirmed `getData()` returns correct non-empty payloads.
- **Committed in:** `491dabe`

**2. [Rule 1/3 - Blocking schema gap] `validation_histories.previous_status` was NOT NULL, blocking D-06's documented behavior**
- **Found during:** Task 3 (Browser test verification)
- **Issue:** 23-CONTEXT.md/23-RESEARCH.md's D-06 explicitly requires `previous_status = null` to render as a synthetic "Nuevo" node, and the plan's own test fixture explicitly creates such a row — but the schema's `previous_status` column was `NOT NULL`, so the insert itself threw a constraint violation. No code path anywhere in the codebase had ever needed to write a null `previous_status` before this plan.
- **Fix:** Added `database/migrations/2026_08_21_160104_make_previous_status_nullable_on_validation_histories_table.php`, making the column nullable — mirrors this same table's existing `2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php` migration exactly (same table, same nullability-for-a-documented-use-case rationale).
- **Files modified:** new migration file
- **Verification:** `php artisan migrate` ran clean; `ValidationHistorySankeyChartTest`'s null-`previous_status` fixture row now inserts and renders as the "Nuevo" node.
- **Committed in:** `491dabe`

**3. [Rule 1 - Bug] `SankeyChart.jsx`'s custom node bypassed Recharts' automatic `recharts-sankey-node` className**
- **Found during:** Task 3 (Browser test verification)
- **Issue:** `node_modules/recharts/lib/chart/Sankey.js`'s `renderNodeItem()` only applies `className: "recharts-sankey-node"` on its own un-customized default `<Rectangle>` fallback; `SankeyChart.jsx` (built in 23-01, not part of this plan's `files_modified`) passes a custom `<Rectangle radius={4} fill={...} />` element, which Recharts clones via `React.cloneElement()` without adding that class — so `.recharts-sankey-node` never matched anything, even with fully correct data (confirmed by dumping the live page's `outerHTML`, which showed real nodes/links data embedded but rendered as bare `class="recharts-rectangle"` elements).
- **Fix:** Added an explicit `className="recharts-sankey-node"` to the custom `<Rectangle>` element in `resources/js/charts/components/SankeyChart.jsx`. `Rectangle`'s own implementation merges this via `clsx('recharts-rectangle', className)`, so both classes now co-exist on the rendered element.
- **Files modified:** `resources/js/charts/components/SankeyChart.jsx`
- **Verification:** `ValidationHistorySankeyChartTest` passes, asserting real `.recharts-sankey-node`/`.recharts-sankey-link` elements with zero JS errors.
- **Committed in:** `491dabe`

**4. [Rule 1 - Bug] `RejectionReasonsStackedAreaChartTest`'s own fixture couldn't produce a visible Recharts `Area` path**
- **Found during:** Task 3 (Browser test verification)
- **Issue:** `node_modules/recharts/lib/cartesian/Area.js` only renders its `<path class="recharts-area">` when a series has more than 1 data point (`points?.length > 1`). The plan's literal test fixture created exactly one `ValidationHistory` row, producing exactly one week bucket — so even with fully correct widget data, zero `.recharts-area` elements could ever render.
- **Fix:** Seeded a second `ValidationHistory` rejection row 2 weeks apart (`created_at` override) so the series has 2 distinct week buckets.
- **Files modified:** `tests/Browser/RejectionReasonsStackedAreaChartTest.php`
- **Verification:** Test passes, asserting a real `.recharts-area` element with zero JS errors.
- **Committed in:** `491dabe`

**5. [Rule 3 - Blocking, environment] `public/build` was stale after this worktree's routine main-checkout copy**
- **Found during:** Task 3 (Browser test verification, before the above 4 fixes were even isolated)
- **Issue:** Following this project's established stale-worktree workaround, `public/build` was copied from the shared main checkout rather than freshly built. That copy predated Phase 23's chart-kind additions (`sankey`/`stacked-area` strings were entirely absent from the built JS bundle), so both widgets always rendered the generic "Sin datos" empty state client-side regardless of the actual server-provided data.
- **Fix:** Ran `npm run build` in this worktree once `node_modules` was installed, producing a fresh bundle that includes the Phase 23 chart-kind additions.
- **Files modified:** none tracked (public/build is gitignored)
- **Verification:** Post-rebuild, the live page's embedded `initialData` and rendered DOM matched; both Browser tests subsequently passed.
- **Committed in:** n/a (build artifact, not tracked)

---

**Total deviations:** 5 auto-fixed (3 Rule 1 bugs in widget/component code, 1 Rule 1 test-fixture fix, 1 Rule 3 blocking schema gap, 1 Rule 3 blocking environment issue)
**Impact on plan:** All fixes were necessary for the plan's own stated done-criteria and success-criteria to be genuinely true (not just container-presence-level). No scope creep — no feature, styling, or behavior was added beyond what the plan specified.

## Issues Encountered

During the full `tests/Browser/` regression sweep (post-fix), `tests/Browser/VoterHappyPathFunnelChartTest.php` (owned by plan 23-02, unrelated to this plan's `files_modified`) failed on `assertSee('Pendiente de Revisión')`. Investigated by stashing all of this plan's uncommitted work and rebuilding assets at that exact prior commit state — the failure reproduced identically, proving it predates and is unrelated to this plan's changes. Left unfixed per the scope-boundary rule (pre-existing failure in a file outside this plan's own scope) and logged to `.planning/phases/23-differentiator-visualizations/deferred-items.md` for whoever picks up VIZ-06 follow-up work.

Additionally, this worktree (`worktree-agent-a7deb454e4e104c81`) was stale at session start — 100 commits behind `main`, missing Phases 20-23 entirely (including this plan's own `23-03-PLAN.md`), plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry, `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, `npm install`, an initial `public/build` copy from the main checkout (later found stale and rebuilt fresh — see Deviation 5). `package-lock.json`'s spurious worktree-name diff from `npm install` was reverted via `git checkout -- package-lock.json` before any commits, per established precedent.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

VIZ-07 and VIZ-10 are both live on the Admin dashboard with real-Chromium test coverage. The `validation_histories.previous_status` nullability fix and the `ValidationHistory` table-qualifier convention (`validation_histories`, plural) are now available/documented for any later phase that queries this model. No blockers for 23-04/23-05.

---
*Phase: 23-differentiator-visualizations*
*Completed: 2026-08-21*

## Self-Check: PASSED

All 8 key files confirmed present on disk (widgets, migration, Browser tests, AdminPanelProvider.php, SankeyChart.jsx, deferred-items.md). All 3 task commits (`7f75c09`, `a674a01`, `491dabe`) confirmed present in git history.
