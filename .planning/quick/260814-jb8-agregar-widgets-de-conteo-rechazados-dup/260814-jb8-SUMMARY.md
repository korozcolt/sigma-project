---
phase: quick-260814-jb8
plan: 01
subsystem: ui
tags: [filament, livewire, campaign-isolation, pest, dashboard-widgets]

requires: []
provides:
  - "RejectionsCountersOverview and JurisdictionSummaryOverview widgets on /admin (Escritorio)"
  - "DuplicatesReportTable::disputedDocumentNumbers() as the shared source of truth for the disputed-duplicate query"
affects: [rejections-counters-overview, jurisdiction-summary-overview, duplicates-report-table, admin-panel-provider]

tech-stack:
  added: []
  patterns:
    - "Reusable public static query-extraction method on a TableWidget, called from a sibling StatsOverviewWidget in the same namespace with no cross-widget use import needed"

key-files:
  created: []
  modified:
    - app/Filament/Widgets/DuplicatesReportTable.php
    - app/Filament/Widgets/RejectionsCountersOverview.php
    - app/Providers/Filament/AdminPanelProvider.php
    - tests/Feature/Filament/RejectionsCountersOverviewTest.php
    - tests/Feature/Filament/AdminPanelProviderTest.php

key-decisions:
  - "RejectionsCountersOverview's Duplicados stat now counts disputed document_number groups (DuplicatesReportTable's existing D-06 cross-campaign definition) instead of the legacy resolved VoterStatus::DUPLICATE status count — user confirmed the disputed-groups definition is correct for this stat too."
  - "Duplicados stat's ->url() removed entirely (not repointed) — a document_number-group criterion has no single VoterResource status filter to express it, and the one surface that does render this data (DuplicatesReportTable, on /reports) requires REPORTS_VIEWER, which not every /admin viewer of this widget has."
  - "RejectionsCountersOverview and JurisdictionSummaryOverview added to AdminPanelProvider only — ReportsPanelProvider's existing registration of both widgets is untouched. This is an addition to /admin, not a move."

patterns-established:
  - "Shared disputed-duplicate query extracted as DuplicatesReportTable::disputedDocumentNumbers(?Campaign $activeCampaign): Collection — any future surface needing the same 'cédulas en disputa' definition should call this instead of reimplementing the query."

requirements-completed: [JB8-DUP-DEFINITION, JB8-DASHBOARD-WIDGETS]

duration: ~20min
completed: 2026-08-14
---

# Quick Task 260814-jb8: Agregar widgets de conteo (rechazados/duplicados) al panel Admin — Summary

**RejectionsCountersOverview and JurisdictionSummaryOverview now render on /admin alongside /reports, and RejectionsCountersOverview's "Duplicados" stat was fixed to reuse DuplicatesReportTable's disputed-groups query instead of the legacy resolved-status count.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-08-14
- **Tasks:** 2/2
- **Files modified:** 5

## Accomplishments
- Fixed a real definitional bug surfaced during the prior session's DuplicatesReportTable drill-through work: RejectionsCountersOverview's "Duplicados" stat was counting `VoterStatus::DUPLICATE` (a resolved, single-row status) instead of the disputed-document_number-group definition DuplicatesReportTable already uses — the two widgets disagreed on what "duplicate" means for the same campaign's data.
- Extracted that shared query into `DuplicatesReportTable::disputedDocumentNumbers(?Campaign $activeCampaign): Collection`, a public static method, and made RejectionsCountersOverview call it directly — one source of truth, no reimplementation.
- Removed the Duplicados stat's now-inapplicable `->url()` link (documented in code why: no single VoterResource filter can express a cross-campaign document_number-group criterion, and the one place that does render this data requires a role not every /admin viewer has).
- Registered both `RejectionsCountersOverview` and `JurisdictionSummaryOverview` on the Admin panel (`/admin`, "Escritorio"), matching their existing relative ordering on `/reports`. `ReportsPanelProvider`'s registration of both widgets is untouched — this is an addition, not a move.

## Task Commits

1. **Task 1: Extract shared disputed-duplicates query; fix RejectionsCountersOverview's Duplicados definition** — `e120cde` (fix, TDD)
2. **Task 2: Register both widgets on the Admin panel (Escritorio) + coverage** — `419cd7f` (feat)

## Files Created/Modified
- `app/Filament/Widgets/DuplicatesReportTable.php` — added `public static function disputedDocumentNumbers(?Campaign $activeCampaign): Collection`, extracted verbatim from the inline query previously built at the top of `table()`; `table()` now calls `self::disputedDocumentNumbers($activeCampaign)`. Class docblock and query semantics unchanged.
- `app/Filament/Widgets/RejectionsCountersOverview.php` — `$duplicados` now computed via `DuplicatesReportTable::disputedDocumentNumbers($activeCampaign)->count()` (no new `use` import needed, same namespace). Duplicados stat description changed to "Cédulas duplicadas activas en disputa (incluye otras campañas)"; its `->url()` call removed with an inline comment explaining why.
- `app/Providers/Filament/AdminPanelProvider.php` — added `RejectionsCountersOverview::class` and `JurisdictionSummaryOverview::class` to `->widgets([...])`, inserted at the same relative position they already occupy in `ReportsPanelProvider` (before `RejectionsReportTable`/`JurisdictionReportTable` respectively). Two new alphabetically-ordered `use` imports.
- `tests/Feature/Filament/RejectionsCountersOverviewTest.php` — updated the two existing fixtures that used a lone `VoterStatus::DUPLICATE` voter to instead create genuine duplicate pairs (one same-campaign pair; one entirely-inside-other-campaign pair, using the session-switch pattern from `DuplicatesReportTableTest`). Added two new tests: one proving the legacy-status-vs-group distinction (lone DUPLICATE-status voter with a unique document_number does not count; a genuine cross-campaign group of 1 does), and one asserting `$stats[1]->getUrl()` is null.
- `tests/Feature/Filament/AdminPanelProviderTest.php` — added a test asserting `Filament::getPanel('admin')->getWidgets()` contains both `RejectionsCountersOverview::class` and `JurisdictionSummaryOverview::class`.

## Decisions Made
- Kept `DuplicatesReportTable`'s class-level D-06 docblock untouched — the extraction only moves the query into a named method, it does not change its semantics or the cross-campaign exception it documents.
- `JurisdictionSummaryOverview.php` required zero code changes — it already existed with correct campaign-scoping and its own `canView()` gate (hidden for Nacional-scope campaigns); only its panel registration was missing.

## Deviations from Plan
None — plan executed exactly as written, including the exact fixture/test additions specified in the plan's `<action>` block.

## Issues Encountered
- This worktree (`agent-a57ff897f6293e6bf`) was one commit behind `main` at session start — missing this quick task's own `260814-jb8-PLAN.md` (which had just been committed to `main` as `b3b7bca`), plus `vendor/`, `.env`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install --no-interaction`, and copied `public/build/` from the main checkout (this plan makes no frontend asset changes, so `npm run build` was not needed).

## User Setup Required
None.

## Full Test Suite / Environment Notes
- `php artisan test --filter=RejectionsCountersOverviewTest` — 5 passed, 12 assertions.
- `php artisan test --filter=AdminPanelProviderTest` — 2 passed, 3 assertions.
- `php artisan test --group=dashboard-widgets` — 78 passed, 207 assertions (full regression across every widget test file in this session's scope, including `DuplicatesReportTableTest`, `JurisdictionSummaryOverviewTest`, `WidgetDrillThroughTest`).
- `vendor/bin/pint --dirty` clean on both task commits (3 files, then 2 files).
- `git diff --stat app/Providers/Filament/ReportsPanelProvider.php` confirmed empty — `/reports` registration untouched.

## Next Phase Readiness
Code + tests complete. Per the user's standing "browser-verify before prod" preference, a real-browser visit to `/admin` as a super admin with an active campaign that has at least one genuinely disputed cédula (confirming "Contadores de Rechazos" and "Resumen de Jurisdicción" render with correct values, and that the Duplicados stat is not a clickable link) is recommended before this ships to sigma-betha/Aldemar production — not yet performed in this session, since no live dev server / real campaign data was available in this ephemeral worktree.

---
*Phase: quick-260814-jb8*
*Completed: 2026-08-14*

## Self-Check: PASSED

All 6 claimed files confirmed present (5 modified + this SUMMARY.md). Both task commits (`e120cde`, `419cd7f`) confirmed present in `git log`.
