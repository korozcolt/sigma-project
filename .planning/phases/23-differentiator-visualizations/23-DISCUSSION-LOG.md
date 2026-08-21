# Phase 23: Differentiator Visualizations - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-21
**Phase:** 23-differentiator-visualizations
**Areas discussed:** Happy-path funnel (VIZ-06), Sankey curation (VIZ-07), Treemap drill-down (VIZ-08), Heatmap caller×hora (VIZ-09), Stacked-area rejection reasons (VIZ-10)

---

## Happy-path funnel (VIZ-06)

| Option | Description | Selected |
|--------|-------------|----------|
| Roadmap's 4-stage example | PENDING_REVIEW → VERIFIED_CENSUS → CONFIRMED → VOTED, skipping VERIFIED_REGISTRADURIA/VERIFIED_CALL | ✓ |
| Full verification chain | All 6 stages including VERIFIED_REGISTRADURIA and VERIFIED_CALL | |
| Something else | Freeform | |

**User's choice:** Roadmap's 4-stage example
**Notes:** Not every voter passes through VERIFIED_REGISTRADURIA/VERIFIED_CALL, so including them would break monotonic narrowing.

| Option | Description | Selected |
|--------|-------------|----------|
| Small counter row below the funnel | Branch states shown as a compact stat row, similar chrome to RejectionsCountersOverview | ✓ |
| Excluded from this widget entirely | Branch states not shown here at all | |
| Something else | Freeform | |

**User's choice:** Small counter row below the funnel

| Option | Description | Selected |
|--------|-------------|----------|
| Branch counter row | DID_NOT_VOTE counted alongside other terminal states in the stat row | ✓ |
| Separate 5th funnel stage | DID_NOT_VOTE as its own trailing funnel stage | |

**User's choice:** Branch counter row

| Option | Description | Selected |
|--------|-------------|----------|
| Campaign-wide, admin-only | Matches VIZ-01/02 precedent, no role-scoping | ✓ |
| Role-scoped like territorial chart | Leader/coordinator/area_coordinator narrowing | |

**User's choice:** Campaign-wide, admin-only

---

## Sankey curation (VIZ-07)

| Option | Description | Selected |
|--------|-------------|----------|
| Top-N by volume | GROUP BY count, keep top N, collapse rest into "Otros" | ✓ |
| Fixed, product-defined transition set | Hand-picked transitions regardless of volume | |
| Something else | Freeform | |

**User's choice:** Top-N by volume

| Option | Description | Selected |
|--------|-------------|----------|
| Synthetic "Nuevo" source node | All voter creations flow from one synthetic node | ✓ |
| Excluded from the Sankey | previous_status=null transitions left out | |

**User's choice:** Synthetic "Nuevo" source node

| Option | Description | Selected |
|--------|-------------|----------|
| Count all occurrences into one edge | Flat GROUP BY count, no per-voter dedup | ✓ |
| Count each voter's transition once | DISTINCT-per-voter dedup | |

**User's choice:** Count all occurrences into one edge

| Option | Description | Selected |
|--------|-------------|----------|
| All history, campaign-scoped | No date filter, matches VIZ-04 precedent | ✓ |
| Bounded window (e.g. last 90 days) | Recent-only filter | |

**User's choice:** All history, campaign-scoped

---

## Treemap drill-down (VIZ-08)

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, keep role-scoping | Same leader/coordinator/area_coordinator narrowing as TerritorialDistributionChart | ✓ |
| No, admin-only campaign-wide | Simplify, removes existing role visibility | |

**User's choice:** Yes, keep role-scoping

| Option | Description | Selected |
|--------|-------------|----------|
| Click a tile to zoom in, breadcrumb to go back | Departamento→Municipio→Barrio nest-mode with breadcrumb | ✓ |
| Something else | Freeform | |

**User's choice:** Click a tile to zoom in, breadcrumb to go back

| Option | Description | Selected |
|--------|-------------|----------|
| Replace in place | Swaps TerritorialDistributionChart's getData()/getChartKind() | ✓ |
| New widget alongside | Keep old bar chart, add treemap separately | |

**User's choice:** Replace in place

| Option | Description | Selected |
|--------|-------------|----------|
| Show all barrios, let treemap squarify handle it | Full fidelity, no cap | ✓ |
| Top-N barrios + "Otros" tile | Capped leaf tiles | |

**User's choice:** Show all barrios, let treemap squarify handle it

---

## Heatmap caller×hora (VIZ-09)

| Option | Description | Selected |
|--------|-------------|----------|
| Contact rate (%) | Successful contacts ÷ total attempts per cell | ✓ |
| Raw successful-contact count | Simple count, conflates busy vs effective | |
| Something else | Freeform | |

**User's choice:** Contact rate (%)

| Option | Description | Selected |
|--------|-------------|----------|
| Scroll container, show all callers | Full fidelity, vertical scroll | ✓ |
| Top-N callers by call volume | Capped rows | |

**User's choice:** Scroll container, show all callers

| Option | Description | Selected |
|--------|-------------|----------|
| Business hours only (e.g. 7am-9pm) | Narrower, avoids mostly-empty columns | ✓ |
| Full 24 hours | Complete axis, no assumption baked in | |

**User's choice:** Business hours only (e.g. 7am-9pm)

| Option | Description | Selected |
|--------|-------------|----------|
| Distinct "no data" shade | Zero-attempt cells visually separate from real 0% cells | ✓ |
| Same scale as 0% | Zero attempts and zero successes render identically | |

**User's choice:** Distinct "no data" shade

---

## Stacked-area rejection reasons (VIZ-10)

| Option | Description | Selected |
|--------|-------------|----------|
| VoterStatus rejection states only | REJECTED_CENSUS/REJECTED_OUT_OF_SCOPE/CENSUS_NOT_FOUND/CORRECTION_REQUIRED, own series each | ✓ |
| Match RejectionsCountersOverview's mixed definition | Also folds in CallResult-based rejections | |

**User's choice:** VoterStatus rejection states only

| Option | Description | Selected |
|--------|-------------|----------|
| Weekly buckets, all campaign history | Smoothed trend, matches all-history precedent | ✓ |
| Daily buckets, last 90 days | Finer granularity, bounded window | |

**User's choice:** Weekly buckets, all campaign history

---

## Claude's Discretion

- Exact dashboard/resource placement for the 4 non-treemap widgets (Admin dashboard, following VoterStatusDonutChart/RejectionsCountersOverview precedent)
- New ChartRouter.jsx kind implementations for sankey/treemap/heatmap/stacked-area (and confirming whether funnel already exists from Phase 22)
- Exact query/service structure for each new aggregation
- Business-hours boundary specifics for the heatmap's hour axis
- Empty-state/error-state behavior (follows existing Phase 20/21/22 standard)
- PAGE_SCOPED_WIDGETS registration if any widget is page-scoped
- Recharts native Sankey/Treemap component API wiring details

## Deferred Ideas

- True symmetric ThemeRiver streamgraph (v2 VIZ-11) — not raised as in-scope, confirmed deferred
- Literal trapezoid funnel of all 12 VoterStatus states (v2 VIZ-12) — not raised as in-scope, confirmed deferred
- Bounded date-range filtering for Sankey or heatmap — not requested, both stay unbounded
- Click-to-drill-through to a filtered record list (beyond the treemap's own zoom navigation) — not requested, consistent with Phase 22 precedent
