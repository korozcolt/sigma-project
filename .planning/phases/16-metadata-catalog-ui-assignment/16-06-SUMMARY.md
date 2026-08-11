---
phase: 16-metadata-catalog-ui-assignment
plan: 06
subsystem: ui
tags: [livewire, volt, flux, metadata, bulk-assignment]

# Dependency graph
requires:
  - phase: 16-metadata-catalog-ui-assignment
    provides: "MetadataAssignmentService (16-01) — activeKeys(), findActiveKey(), subordinatesByIds(), assignMany()"
provides:
  - "Row selection + one-key/one-value bulk metadata assignment modal on the coordinador líderes Volt list"
  - "Row selection + one-key/one-value bulk metadata assignment modal on the articulador coordinadores Volt list"
  - "Pest coverage proving a tampered client-posted selection cannot write to a non-subordinate on either surface"
affects: [17-filter-sort-export-surfaces]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Volt page row-selection: plain checkbox array (wire:model.live) bound to a public array property, with a server-side updatedSelectAllOnPage() handler that re-resolves the page's own scoped query rather than trusting DOM state"
    - "Bulk write path re-filters client-posted ids through MetadataAssignmentService::subordinatesByIds() before any write — never a bare User::whereIn('id', ...)"

key-files:
  created:
    - tests/Feature/Metadata/VoltMetadataBulkAssignTest.php
  modified:
    - resources/views/livewire/coordinator/leaders.blade.php
    - resources/views/livewire/articulador/coordinators.blade.php

key-decisions:
  - "Selection scoping in updatedSelectAllOnPage() re-derives 'ids on this page' server-side from the extracted leadersQuery()/coordinatorsQuery() builder, never from client-supplied DOM checkbox state — prevents a paginated/filtered page from selecting rows outside the actor's own scope"
  - "with() rewritten as a pure extraction into leadersQuery()/coordinatorsQuery() with byte-identical scoping semantics (proven by 2 dedicated regression tests using assertSeeText/assertDontSeeText, plus the full pre-existing Coordinator/Articulador test suites passing unchanged)"
  - "Flash confirmation asserted via the Livewire Testable's rendered component HTML instead of assertSessionHas/session() — Livewire's SubsequentRender testing harness disables the StartSession middleware between chained ->set()/->call() calls, making session-facade assertions unreliable across a multi-step Testable chain even though the flash is genuinely present in the final render"

requirements-completed: [META-03, META-04]

# Metrics
duration: 25min
completed: 2026-08-11
---

# Phase 16 Plan 06: Volt Bulk Metadata Assignment Summary

**Row selection + a one-key/one-value bulk metadata modal on the coordinador líderes and articulador coordinadores Volt list pages, with every write re-filtered through `MetadataAssignmentService::subordinatesByIds()` against a tampered client-posted selection.**

## Performance

- **Duration:** ~25 min (including worktree repair: stale worktree missing Phase 16 entirely, required `git merge --ff-only main`, `.env` copy, `composer install`, `npm install && npm run build`)
- **Completed:** 2026-08-11T03:43:33Z
- **Tasks:** 3
- **Files modified:** 3 (2 Volt pages, 1 new Pest test file)

## Accomplishments
- Coordinador líderes list and articulador coordinadores list both gained per-row checkboxes, a page-level "Seleccionar todos" control, a selection bar that only appears when rows are selected, and a bulk metadata modal offering exactly one key + one value field (type-matched: select/numeric/date/text)
- Every bulk write is re-filtered server-side through `MetadataAssignmentService::subordinatesByIds()` before touching the database — a coordinador/articulador cannot assign metadata to a user outside their own direct team by editing the wire payload, closing the same cross-team leak class previously found and fixed in quick tasks 260804-i5f/260804-jbc
- 11 new Pest tests (54 assertions) cover the happy path on both surfaces, the tampered-selection denial on both surfaces, empty/invalid/inactive-key rejection, select-type option validation, selection-clear + flash confirmation, the select-all control, and a rendered-list regression proving the `with()` → `leadersQuery()`/`coordinatorsQuery()` extraction changed zero scoping behavior

## Task Commits

Each task was committed atomically:

1. **Task 1: Add selection state and bulk metadata modal to the coordinador líderes list** - `b5525a0` (feat)
2. **Task 2: Add the same selection and bulk modal to the articulador coordinadores list** - `997fb5e` (feat)
3. **Task 3: Pest coverage for Volt bulk assignment** - `7c57dd1` (test)

_No separate "docs: complete plan" metadata commit yet — STATE.md/ROADMAP.md updates follow this SUMMARY._

## Files Created/Modified
- `resources/views/livewire/coordinator/leaders.blade.php` - Extracted `leadersQuery()`, added selection state (`selectedLeaderIds`, `selectAllOnPage`), the bulk metadata modal, and `assignBulkMetadata()` which re-filters through `subordinatesByIds()`
- `resources/views/livewire/articulador/coordinators.blade.php` - Mirror of the coordinador page: `coordinatorsQuery()` extraction, `selectedCoordinatorIds`, identical bulk modal and write path
- `tests/Feature/Metadata/VoltMetadataBulkAssignTest.php` - 11 tests covering both surfaces (new file)

## Decisions Made
- The `leadersQuery()`/`coordinatorsQuery()` extractions are pure refactors — the original `with()` bodies (search, coordinator filter, `area_coordinator_user_id` scoping) were preserved character-for-character in the new protected methods; only the nested `if`/`else` in `leaders.blade.php` was flattened to `elseif` (behaviorally identical, confirmed by the full pre-existing `Coordinator`/`Articulador` Pest suites passing unchanged)
- `updatedSelectAllOnPage()` re-runs the scoped query server-side (`$this->leadersQuery()->latest()->paginate(15)->pluck('id')`) rather than trusting whatever checkbox ids the DOM currently holds — this is the same principle as the mandatory `subordinatesByIds()` re-filter on write, applied to the read/select-all path too
- Chose `flux:checkbox` bound directly to the array property over `flux:checkbox.all`/`flux:checkbox.group`, per RESEARCH.md's explicit "Don't Hand-Roll" guidance and the plan's own reasoning: the group wrapper would change this page's existing card/`divide-y` row markup, and the "select all on this page" set must be resolved server-side, not inferred from the DOM
- Flash confirmation test uses `$component->html()` rather than `assertSessionHas`/`session()` — investigated and confirmed Livewire's `Testable::update()` (used by every chained `->set()`/`->call()`) goes through `RequestBroker::temporarilyDisableExceptionHandlingAndMiddleware()`, which disables the `StartSession` middleware for subsequent renders, so session-facade-based assertions do not reliably reflect state set mid-chain even though `session()->flash(...)` executes correctly and the value is genuinely present in the component's own re-render

## Deviations from Plan

None - plan executed exactly as written. The one investigation (Livewire session-testing behavior, documented above under Decisions Made) was a test-implementation detail resolved without altering any acceptance criterion, interface, or production code path specified by the plan.

## Issues Encountered
- Worktree (`agent-a2708849edd27f6bc`) was stale at session start — missing Phase 16 entirely (all of 16-01 through 16-05's completed work, including the `MetadataAssignmentService` this plan depends on), plus `.env`, `vendor/`, `node_modules/`, `public/build/`. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install`, `npm install && npm run build`.
- `Volt::test(...)->assertSessionHas('success')` initially failed even immediately after the successful `assignBulkMetadata()` call, despite `assertHasNoErrors()` and `assertSet(...)` on the same chain passing — traced to Livewire's testing `RequestBroker` disabling middleware (including `StartSession`) on `SubsequentRender` requests, not a bug in the production code. Fixed by asserting against the component's own rendered `html()` output instead, which correctly reflects the flash message set during that same render cycle.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- META-03 and META-04 are now fully implemented across all three panels required by 16-CONTEXT.md D-05 (admin/Filament in 16-01/16-02, coordinador/articulador Volt bulk assignment in this plan)
- Phase 17 (Filter/Sort/Export Surfaces) can proceed — it depends on this phase's `user_metadata_values` data shape, which is unchanged by this plan (only the UI/write-path for populating it was added)
- No blockers or concerns carried forward

---
*Phase: 16-metadata-catalog-ui-assignment*
*Completed: 2026-08-11*

## Self-Check: PASSED

- FOUND: resources/views/livewire/coordinator/leaders.blade.php
- FOUND: resources/views/livewire/articulador/coordinators.blade.php
- FOUND: tests/Feature/Metadata/VoltMetadataBulkAssignTest.php
- FOUND commit: b5525a0
- FOUND commit: 997fb5e
- FOUND commit: 7c57dd1
