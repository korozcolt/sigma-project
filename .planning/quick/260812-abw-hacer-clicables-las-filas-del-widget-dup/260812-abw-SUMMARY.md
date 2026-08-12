---
phase: quick-260812-abw
plan: 01
subsystem: ui
tags: [filament, livewire, campaign-isolation, pest]

requires: []
provides:
  - "Conditional drill-through on DuplicatesReportTable, gated on campaign isolation"
affects: [duplicates-report-table, widget-drill-through]

tech-stack:
  added: []
  patterns:
    - "recordUrl closure gated on $record->campaign_id === $activeCampaign->id, reusing an already-computed variable instead of re-querying"

key-files:
  created: []
  modified:
    - app/Filament/Widgets/DuplicatesReportTable.php
    - tests/Feature/WidgetDrillThroughTest.php

key-decisions:
  - "Rows are clickable only when the record's own campaign_id matches the active campaign; D-06 cross-campaign sibling rows stay non-clickable — no change to the widget's row-SET query or the cross-campaign exception itself."
  - "User chose this option (partial drill-through, zero isolation risk) over a read-only modal for all rows, after the assistant explained why the widget was originally left fully static."

patterns-established:
  - "Per-row conditional recordUrl, closure captures a variable already computed at the top of table() instead of re-deriving it — avoids a second CampaignContext::currentCampaign() call."

requirements-completed: [ABW-DUP-DRILL]

duration: ~25min
completed: 2026-08-12
---

# Quick Task 260812-abw: Hacer clicables las filas del widget DuplicatesReportTable — Summary

**DuplicatesReportTable rows now link to the voter's Filament view page when the row belongs to the active campaign; cross-campaign D-06 sibling rows remain intentionally non-clickable.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-08-12
- **Tasks:** 2/2
- **Files modified:** 2

## Accomplishments
- Fixed a UX inconsistency (flagged by the user while using the live app): every other report-panel widget already drilled through to a voter's detail page; DuplicatesReportTable was the one exception, by design, because some of its rows can belong to a different campaign than the active one (D-06 cross-campaign isolation exception).
- Added a conditional `->recordUrl()` that links only when `$record->campaign_id` matches the active campaign, preserving the isolation guarantee for cross-campaign sibling rows.
- Replaced the old single "no drill-through, by design" test with two explicit Pest cases covering both branches.

## Task Commits

1. **Task 1: Conditional recordUrl on DuplicatesReportTable, gated on active-campaign match** — `0b1a298` (test), `c04861e` (feat)
2. **Task 2: Pint + regression check** — verification only, no additional commit (both changed files already covered by Task 1's commits; Pint and full regression run clean, no formatting changes needed)

## Files Created/Modified
- `app/Filament/Widgets/DuplicatesReportTable.php` — added `use App\Filament\Resources\Voters\VoterResource;` and a `->recordUrl()` closure chained after `->headerActions([...])`, reusing the existing `$activeCampaign` variable (no second `CampaignContext::currentCampaign()` call). Returns `VoterResource::getUrl('view', ['record' => $record])` when `$record->campaign_id === $activeCampaign->id`, otherwise `null`.
- `tests/Feature/WidgetDrillThroughTest.php` — removed the single "duplicates report table rows have no drill-through, by design" test; added two tests using the cross-campaign fixture pattern (session-switch-create-switch-back) already established in `DuplicatesReportTableTest.php`: one asserting a non-null URL for an active-campaign row, one asserting `null` for a different-campaign sibling row.

## Decisions Made
- Kept the class-level docblock on `DuplicatesReportTable` untouched — it documents the row-SET/query-level D-06 exception, which this task does not touch (only per-row clickability changed).
- Did not call `CampaignContext::currentCampaign()` a second time inside the `recordUrl` closure — reused the `$activeCampaign` variable already computed at the top of `table()`.

## Deviations from Plan
None — plan executed exactly as written.

## Issues Encountered
- The execution worktree (`agent-a49d5b00b66df008e`) was stale at spawn (missing `vendor/`, `.env`, `node_modules/`, `public/build/`, and this quick task's own PLAN.md, which had never been committed anywhere for `git merge` to pick up). Resolved via the established workaround: `git merge --ff-only` from `main`, `.env` copy, `composer install`.
- This SUMMARY.md was reconstructed by the orchestrator after the fact: the executor created it inside the worktree but never committed it (by design — PLAN.md/SUMMARY.md are meant to be bundled into the orchestrator's final `docs(quick-260812-abw): ...` commit per the quick-task workflow), and the worktree was removed (`git worktree remove --force`) before that final commit happened, taking the uncommitted file with it. Content reconstructed from the executor's final task-notification report plus a fresh diff/test read of the merged commits — no information loss, but noting the process gap: **future quick tasks executed via `isolation: "worktree"` should have their PLAN.md/SUMMARY.md copied out of the worktree (or committed) before the worktree is removed.**

## User Setup Required
None.

## Full Test Suite / Environment Notes
- `php artisan test tests/Feature/Filament/DuplicatesReportTableTest.php tests/Feature/WidgetDrillThroughTest.php` — 15 passed, 31 assertions (re-confirmed independently by the orchestrator after merge, not just trusted from the executor's report).
- `vendor/bin/pint --test` clean on both changed files.
- `git status --short` post-merge confirms no files changed beyond this task's `files_modified` (plus the pre-existing, unrelated `.claude/settings.local.json` modification and untracked scratch files present since session start).

## Next Phase Readiness
Code + tests complete and merged to `main`. Per the user's standing "browser-verify before prod" preference, a real-browser click-through of the "Informe de Duplicados" widget (own-campaign row opens the voter view; a different-campaign sibling row has no link) is recommended before this ships to sigma-betha/Aldemar production — not yet performed in this session.

---
*Phase: quick-260812-abw*
*Completed: 2026-08-12*
