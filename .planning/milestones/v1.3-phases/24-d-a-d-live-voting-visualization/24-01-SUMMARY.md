---
phase: 24-d-a-d-live-voting-visualization
plan: 01
subsystem: ui
tags: [filament, chartwidget, recharts, livewire-polling, cache, dia-d]

# Dependency graph
requires:
  - phase: 20-react-island-infrastructure
    provides: React/Recharts island mechanism (ChartRouter, ChartCard, wire:ignore bridge)
  - phase: 23-differentiator-visualizations
    provides: PHP/Carbon date-bucketing precedent (RejectionReasonsStackedAreaChart) and the page-scoped-widget ComponentNotFoundException fix pattern
provides:
  - "DiaDLiveVotingChart: campaign+event-scoped, Cache::remember()'d 30s-TTL hourly-cumulative line chart of Dia D voting progress"
  - "Two new ChartCard.jsx empty-state copy keys: no_active_event, no_votes_yet"
affects: [v1.3-milestone-completion]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Page-scoped ChartWidget with Cache::remember() keyed by campaign_id+election_event_id to decouple query load from concurrent polling admin count"
    - "PHP/Carbon hour-bucketing with flat-continuation backfill for zero-activity buckets (never raw SQL DATE_FORMAT/strftime)"

key-files:
  created:
    - app/Filament/Widgets/DiaDLiveVotingChart.php
    - tests/Unit/DiaDLiveVotingChartTest.php
    - tests/Browser/DiaDLiveVotingChartTest.php
  modified:
    - app/Filament/Pages/DiaD.php
    - app/Providers/AppServiceProvider.php
    - tests/Feature/PageScopedWidgetRegistrationTest.php
    - resources/js/charts/components/ChartCard.jsx

key-decisions:
  - "Cache key is diad-live-voting:{campaign_id}:{election_event_id} so a poll-tick cache hit can never leak across campaigns or election events"
  - "Zero-vote hours backfill as a flat continuation of the running total rather than being omitted, so a stalled voting hour reads as a visible flat line, not an ambiguous gap"

requirements-completed: [DAYD-05]

# Metrics
duration: ~20min
completed: 2026-08-21
---

# Phase 24 Plan 01: Día D Live Voting Chart Summary

**Cached, campaign+event-scoped hourly-cumulative Recharts line chart of Día D voting progress, polling every 30s without re-running its aggregation query on every tick.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-08-21
- **Tasks:** 2 completed
- **Files modified:** 7 (3 created, 4 modified)

## Accomplishments
- `DiaDLiveVotingChart` ships as the 3rd header widget on the `DiaD` Filament page, showing a live-updating line of cumulative hourly votes for the currently active campaign + active `ElectionEvent`
- The widget's aggregation query is wrapped in `Cache::remember()` with a 30s TTL, keyed per campaign+event — proven by a real cache-hit Unit test (a second `getData()` call within the TTL window returns the pre-seed total even after new `VoteRecord`s are inserted)
- Distinct Spanish empty-state copy for "no campaign", "no active event", and "no votes yet", each proven by dedicated Unit tests and (for the Día-D-specific `no_active_event` case) a real Browser test
- The widget is registered in `AppServiceProvider::PAGE_SCOPED_WIDGETS`, preventing the known `ComponentNotFoundException` class of bug on the `wire:poll` follow-up request (the 14th entry in this milestone's regression guard)
- Real rendered Recharts line chart verified end-to-end via a Pest v4 Chromium Browser test, matching the INFRA-04 per-widget Browser test convention

## Task Commits

Each task was committed atomically:

1. **Task 1: Build DiaDLiveVotingChart widget + wire registration** (TDD: RED then GREEN)
   - `531fb27` (test) — failing Unit test covering all 5 required cases
   - `11eac4e` (feat) — widget implementation + `DiaD.php`/`AppServiceProvider.php`/`PageScopedWidgetRegistrationTest.php` wiring
2. **Task 2: Add empty-state copy + npm build + Browser test** - `8b5b53b` (feat)

**Plan metadata:** committed separately (this SUMMARY + STATE/ROADMAP/REQUIREMENTS update)

## Files Created/Modified
- `app/Filament/Widgets/DiaDLiveVotingChart.php` - `ChartWidget` with campaign+event-scoped `Cache::remember()`'d hourly-cumulative `getData()`, `kind='line'`, `pollingInterval='30s'`
- `app/Filament/Pages/DiaD.php` - `DiaDLiveVotingChart::class` added as the 3rd `getHeaderWidgets()` entry
- `app/Providers/AppServiceProvider.php` - `DiaDLiveVotingChart::class` added to `PAGE_SCOPED_WIDGETS`
- `tests/Feature/PageScopedWidgetRegistrationTest.php` - dataset extended to 14 entries
- `tests/Unit/DiaDLiveVotingChartTest.php` - 5 tests: empty states (x3), hourly backfill/running-total math, cache-hit behavior
- `tests/Browser/DiaDLiveVotingChartTest.php` - 2 tests: real rendered line chart with seeded data, `no_active_event` empty state
- `resources/js/charts/components/ChartCard.jsx` - `no_active_event`/`no_votes_yet` empty-state copy keys added to `EMPTY_STATE_COPY`

## Decisions Made
- Cache key scoped to both `campaign_id` and `election_event_id` (`diad-live-voting:{campaign_id}:{election_event_id}`) — matches the plan's key_links requirement that the cache can never leak across campaigns or election events, and naturally invalidates itself when a new event is activated (new key) rather than requiring manual cache-busting.
- Zero-vote hours backfill as a flat continuation of the cumulative running total rather than being omitted from the series, per 24-RESEARCH.md's documented open question — a flat line communicates "stalled" (a real, meaningful operational signal for admins watching Día D momentum), while a gap in the x-axis would read as ambiguous missing data.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed the plan's own literal Unit test fixtures to use distinct voters per VoteRecord**
- **Found during:** Task 1, RED step (writing `tests/Unit/DiaDLiveVotingChartTest.php` verbatim per the plan)
- **Issue:** The plan's literal test code for the "backfills zero-vote hours" and "caches the aggregation" cases created 3 `VoteRecord`s for the *same* `Voter` within the *same* `ElectionEvent`. `vote_records` carries a real DB-level unique constraint on `(voter_id, election_event_id)` (migration `2026_07_23_160000_add_unique_constraint_to_vote_records_table.php`, the same constraint the Día D vote-marking flow itself relies on for duplicate-vote prevention). Running the plan's literal fixture verbatim threw `UniqueConstraintViolationException` on the 2nd/3rd insert, before the widget class even existed — this was never reachable regardless of correct implementation.
- **Fix:** Seeded 3 distinct `Voter`s (via `Voter::factory()->count(3)->create(...)`) in both affected tests, assigning one `VoteRecord` per voter at the same intended `voted_at` timestamps the plan specified. Chart-data assertions (`[2, 2, 3]` backfill, `[1]`→`[1]` cache-hit) are unchanged — only the fixture's voter-identity plumbing changed, not the aggregation logic under test.
- **Files modified:** `tests/Unit/DiaDLiveVotingChartTest.php`
- **Verification:** All 5 Unit tests pass (`php artisan test --filter=DiaDLiveVotingChartTest`, 5 passed / 7 assertions).
- **Committed in:** `531fb27` (Task 1, RED commit)

---

**Total deviations:** 1 auto-fixed (1 bug, test-fixture-only — zero production code deviation from the plan's literal `DiaDLiveVotingChart.php` code block)
**Impact on plan:** No scope creep. The widget implementation, `DiaD.php`/`AppServiceProvider.php`/`ChartCard.jsx` edits, and the Browser test all match the plan's literal code verbatim.

## Issues Encountered
None beyond the deviation documented above.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DAYD-05 is fully satisfied: admin/operator sees a live-updating, cached, campaign+event-scoped line chart of cumulative hourly Día D votes on the `DiaD` page, confirmed by 5 Unit tests + 2 Browser tests + the 14-entry `PageScopedWidgetRegistrationTest` regression guard, all passing.
- Phase 24 is now the last phase of the v1.3 Visualización de Datos MonoCharts milestone — with this plan complete, all 5 v1.3 phases (20-24) are done pending the standard `verify_phase_goal` / milestone-completion step.
- No known stubs or deferred data-wiring gaps in this plan's scope.

---
*Phase: 24-d-a-d-live-voting-visualization*
*Completed: 2026-08-21*

## Self-Check: PASSED

All created files confirmed present on disk (`app/Filament/Widgets/DiaDLiveVotingChart.php`, `tests/Unit/DiaDLiveVotingChartTest.php`, `tests/Browser/DiaDLiveVotingChartTest.php`) and all modified files confirmed present (`app/Filament/Pages/DiaD.php`, `app/Providers/AppServiceProvider.php`, `resources/js/charts/components/ChartCard.jsx`). All 3 task commit hashes (`531fb27`, `11eac4e`, `8b5b53b`) confirmed present in git history.
