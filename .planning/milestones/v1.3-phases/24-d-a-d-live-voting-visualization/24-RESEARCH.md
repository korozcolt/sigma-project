# Phase 24: Día D Live Voting Visualization - Research

**Researched:** 2026-08-21
**Domain:** Laravel `Cache::remember()` (database driver), hourly time-series aggregation, Filament ChartWidget + React-island polling pipeline
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Caching / Pre-Aggregation Strategy
- **D-01:** Use `Cache::remember()` with on-demand recalculation, not a scheduled job and not event-driven invalidation via a `VoteRecord` observer. Rationale: no new scheduler infrastructure needed, and it avoids coupling the vote-marking hot path (already treated as a high-risk flow per project constraints) to cache invalidation for a read-only widget. Only the first request after expiry pays the query cost; concurrent admins during the same window hit cache.
- **D-02:** Cache TTL is **30 seconds**. Chosen to feel "live" for a Día D dashboard while absorbing concurrent-polling bursts from multiple admins/operators watching simultaneously. The roadmap's own "accumulated hourly" bucketing means a few seconds of staleness is imperceptible at the chart's granularity.
- **Cache scope note (Claude's discretion for exact key format):** The cache key must be campaign-scoped (per the project's strict campaign-isolation constraint) and should account for the active `ElectionEvent` (via `campaign_id` + `is_active` election event, per `app/Models/ElectionEvent.php`) so results don't bleed across election events/simulations within the same campaign.

#### Widget Polling Interval
- **D-03:** The widget's `pollingInterval` is **30s**, matching the cache TTL exactly. Rationale: every poll tick aligns with a real possible cache refresh — no polls are "wasted" re-requesting data known not to have changed. This deliberately diverges from `DiaDStatsOverview`'s existing `10s` polling (that widget still does direct uncached counts and is out of scope for this phase).

#### Placement
- **D-04:** The chart lives on the dedicated **`DiaD` Filament page** (`app/Filament/Pages/DiaD.php`), alongside the existing `DiaDStatsOverview` and `DiaDTerritorialProgressTable` widgets — not on the general Admin dashboard. Rationale: this is where admins/operators already look during the election-day operational window; keeps all Día D context in one place.

#### Time-Series Scope
- **D-05:** The chart shows exactly **one line**: campaign-wide cumulative vote count accumulated hourly (`VoteRecord.voted_at` bucketed by hour, running total). No per-territory, per-coordinador, or per-leader breakdown in this chart — that would be a new capability duplicating `DiaDTerritorialProgressTable`'s existing purpose and is out of this phase's scope (see Deferred Ideas).

### Claude's Discretion
- Exact cache key naming/format (must include campaign_id + active election_event_id per the scope note above)
- Exact chart kind/component reuse: `resources/js/charts/components/LineChart.jsx` already exists (used by `ValidationProgressChart`) — reuse it rather than building a new chart component unless it's structurally incompatible with an hourly-cumulative time series
- Empty/pre-Día-D state copy (no vote records yet — before polls open)
- Exact aggregation query shape (e.g., whether hours with zero new votes still appear as a flat continuation of the running total)

### Deferred Ideas (OUT OF SCOPE)

- **Per-territory/per-coordinador breakdown within the live line chart** — suggested during discussion as an alternative scope, explicitly declined (D-05) in favor of matching the roadmap's simple single-line scope. `DiaDTerritorialProgressTable` already serves this need. Could become its own future phase/idea if a multi-line comparative view is ever requested.
</user_constraints>

## Summary

This phase adds exactly one new widget — a campaign-scoped, hourly-cumulative line chart of `VoteRecord.voted_at` — to the existing `DiaD` Filament page, reusing 100% of the React-island chart infrastructure built in Phases 20-23 (`ChartRouter.jsx` → `LineChart.jsx`, `ChartCard.jsx`, `react-chart.blade.php`, the Alpine/React bridge). No new frontend chart-kind component is needed: `line` already exists and consumes exactly the `{labels, datasets}` shape this widget will produce.

The only genuinely new mechanic in this phase is `Cache::remember()`. Nothing in `app/Filament/Widgets/` uses it today — the one precedent in the whole codebase is `App\Livewire\SaldosBadge::mount()`, a Livewire component (not a widget) caching an external API balance for 1 hour with a plain non-scoped key. That confirms syntax but not the scoping pattern; this phase must construct its own campaign+event-scoped key. `config/cache.php`'s default store is `database` (`CACHE_STORE=database` in production `.env`), backed by the already-migrated `cache`/`cache_locks` tables (`database/migrations/0001_01_01_000001_create_cache_table.php`) — zero setup needed. Tests run with `CACHE_STORE=array` (set in `phpunit.xml`), which is safe and isolated per test process.

`VoteRecord` already carries `campaign_id` and `election_event_id` as direct columns (no join needed), and a composite index `['election_event_id', 'voted_at']` (migration `2025_11_27_030617`) already exists — no new migration is required for this phase's aggregation query. `ElectionEvent` has `HasCampaignContext`, which auto-applies a campaign-scoping global scope, so `ElectionEvent::where('is_active', true)->first()` (the exact call `DiaD.php` already uses in `markVoted()`) is already implicitly scoped to the active campaign context.

**Primary recommendation:** Add a `DiaDLiveVotingChart extends ChartWidget` (view `filament.widgets.react-chart`, `getChartKind() => 'line'`, `pollingInterval = '30s'`) whose `getData()` wraps a `Cache::remember("diad-live-voting:{$campaignId}:{$eventId}", now()->addSeconds(30), ...)` closure. Inside the closure, bucket `VoteRecord.voted_at` by hour **in PHP/Carbon** (not raw SQL `DATE_FORMAT`/`strftime`) — mirroring the exact pattern already proven in `RejectionReasonsStackedAreaChart` (23-03) to avoid MySQL/sqlite date-function disagreement (documented there as "Pitfall 2"). Register it on `DiaD::getHeaderWidgets()` alongside the existing two widgets, and add it to `AppServiceProvider::PAGE_SCOPED_WIDGETS` (mandatory for any widget attached via a Page's `getHeaderWidgets()` rather than a panel's global `->widgets([...])` array — this is a well-documented, repeatedly-hit bug class in this codebase's history).

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `Illuminate\Support\Facades\Cache` | Laravel 12 (bundled) | 30s TTL memoization of the aggregation query | Framework-native, zero new dependency; `database` driver already configured and migrated |
| `filament/filament` ChartWidget | v4 (bundled) | Base class for the new widget | Every existing chart widget in this codebase extends it |
| `resources/js/charts/components/LineChart.jsx` | already in repo | Renders the cumulative line | Reused verbatim per CONTEXT.md D-05/discretion — already consumes `{labels, datasets}` |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Illuminate\Support\Carbon` | bundled | Hour-bucketing + running-total accumulation in PHP | Always, for this aggregation — avoids DB-driver-specific SQL date truncation |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP/Carbon hour-bucketing | Raw SQL `GROUP BY` with `DATE_FORMAT()`/`strftime()` driver switch (the pattern `BirthdayWidget` uses for day-of-month sorting) | Marginally cheaper on very large row counts, but reintroduces the exact MySQL/sqlite divergence risk `RejectionReasonsStackedAreaChart` was fixed to avoid one phase ago. Not worth it: this query only runs once per 30s (via cache), so PHP-side grouping cost is irrelevant to the "avoid per-tick query load" requirement — that's solved entirely by `Cache::remember`, not by the aggregation's own efficiency. |
| `Cache::remember()` on-demand | Scheduled job pre-computing the aggregate every N seconds | Explicitly rejected by CONTEXT.md D-01 — avoids new scheduler infrastructure and avoids coupling the vote-marking hot path to cache invalidation. |

**Installation:** None — `Cache`, `Carbon`, and Filament `ChartWidget` are all already present in the codebase. No `composer`/`npm` changes.

**Version verification:** No new packages introduced; existing `laravel/framework` v12 and `filament/filament` v4 versions (per CLAUDE.md) are unchanged by this phase.

## Architecture Patterns

### Recommended Project Structure
```
app/Filament/Widgets/
├── DiaDStatsOverview.php              # existing, uncached, 10s poll — reference only
├── DiaDTerritorialProgressTable.php   # existing, uncached, 15s poll — reference only
└── DiaDLiveVotingChart.php            # NEW — cached, 30s poll, kind='line'
```
No new frontend files needed. One small edit: `resources/js/charts/components/ChartCard.jsx`'s `EMPTY_STATE_COPY` map needs one new key (e.g. `no_active_event`) for the pre-Día-D empty state (see Common Pitfalls).

### Pattern 1: Campaign+event-scoped `Cache::remember()` inside `getData()`
**What:** The `ChartWidget::getData()` method wraps its query in `Cache::remember()`, keyed by both `campaign_id` and the active `election_event_id`.
**When to use:** This widget only — it is the first cached widget in the codebase, so there is no existing widget to copy verbatim; this is the pattern to establish.
**Example:**
```php
// Source: app/Filament/Widgets/ValidationProgressChart.php (empty-state pattern)
//         + app/Livewire/SaldosBadge.php:66 (Cache::remember syntax)
//         + app/Filament/Widgets/RejectionReasonsStackedAreaChart.php (PHP-side date bucketing)
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

protected function getData(): array
{
    $activeCampaign = CampaignContext::currentCampaign();

    if (! $activeCampaign) {
        return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_campaign'];
    }

    $activeEvent = ElectionEvent::where('is_active', true)->first(); // already campaign-scoped via CampaignContextScope

    if (! $activeEvent) {
        return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_active_event'];
    }

    $cacheKey = "diad-live-voting:{$activeCampaign->id}:{$activeEvent->id}";

    return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($activeCampaign, $activeEvent) {
        $rows = VoteRecord::query()
            ->where('campaign_id', $activeCampaign->id)
            ->where('election_event_id', $activeEvent->id)
            ->select('voted_at')
            ->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_votes_yet'];
        }

        // Bucket in PHP/Carbon, not raw SQL — mirrors RejectionReasonsStackedAreaChart's
        // documented fix for MySQL/sqlite date-function disagreement.
        $byHour = $rows->groupBy(fn ($r) => Carbon::parse($r->voted_at)->format('Y-m-d H:00'));
        $hourLabels = $byHour->keys()->sort()->values();

        $runningTotal = 0;
        $cumulative = $hourLabels->map(function ($hour) use ($byHour, &$runningTotal) {
            $runningTotal += $byHour[$hour]->count();

            return $runningTotal;
        });

        return [
            'labels' => $hourLabels->map(fn ($h) => Carbon::parse($h)->format('H:00'))->toArray(),
            'datasets' => [[
                'label' => 'Votos acumulados',
                'data' => $cumulative->toArray(),
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'fill' => true,
            ]],
        ];
    });
}
```
**Note (Claude's discretion per CONTEXT.md):** The example above only emits hours that had at least one vote (sparse buckets). If the planner wants "hours with zero new votes still shown as a flat continuation" (the open question CONTEXT.md flags), fill the gap between `$hourLabels->first()` and `now()` with every intermediate hour, carrying the last known running total forward for hours with 0 new votes. Either is a valid, small implementation choice — not a blocker, but the plan should pick one explicitly.

### Pattern 2: `getType()`/`getChartKind()` delegation (existing convention)
**What:** Every migrated chart widget implements `getType(): string { return $this->getChartKind(); }` plus a `getChartKind(): string` returning the literal kind string, and sets `protected string $view = 'filament.widgets.react-chart';`.
**When to use:** Always, for any new `ChartWidget` in this codebase (established in Phase 21).
**Example:** See `ValidationProgressChart.php` lines 102-110 (read in full during this research) — copy this exact shape.

### Pattern 3: Page-scoped widget registration (two places, not one)
**What:** A widget attached via a Filament Page's `getHeaderWidgets()`/`getFooterWidgets()` array (not a panel's global `->widgets([...])`) must ALSO be added to `AppServiceProvider::PAGE_SCOPED_WIDGETS` (line 46-60) or its `wire:poll` tick throws `Livewire\Exceptions\ComponentNotFoundException` on the very first poll after initial render (works fine on first paint, breaks on poll).
**When to use:** This widget, always — `DiaD.php` registers via `getHeaderWidgets()`, not panel-global.
**Example:**
```php
// app/Filament/Pages/DiaD.php
protected function getHeaderWidgets(): array
{
    return [
        DiaDStatsOverview::class,
        DiaDTerritorialProgressTable::class,
        DiaDLiveVotingChart::class, // NEW
    ];
}
```
```php
// app/Providers/AppServiceProvider.php — add both the `use` import (alphabetical) and the array entry
use App\Filament\Widgets\DiaDLiveVotingChart;
// ...
private const PAGE_SCOPED_WIDGETS = [
    // ... existing entries ...
    DiaDLiveVotingChart::class,
];
```
This bug class has been hit and fixed at least 5 times in this milestone's history (`RevalidationProgressWidget`, `SurveyResultsWidget`, `CallCenterStatsWidget`, `CallCenterCallsSparklineWidget`, etc. — see `.planning/STATE.md` Phase 21 Plan 04/06 decisions) — treat it as certain to bite if skipped, not a maybe.

### Anti-Patterns to Avoid
- **Raw SQL `GROUP BY DATE_FORMAT(voted_at, ...)` / `strftime(...)`:** Works on MySQL (prod) but disagrees with sqlite (tests), or vice-versa. `RejectionReasonsStackedAreaChart` was already burned by this exact class of bug for week-bucketing; do the bucketing in PHP/Carbon instead.
- **Caching without campaign+event scoping:** A bare `Cache::remember('diad-live-voting', ...)` key would leak one campaign's/event's vote counts into another's chart — a direct violation of the project's strict campaign-isolation constraint (CLAUDE.md, CONTEXT.md scope note).
- **Registering only on the Page's `getHeaderWidgets()` and forgetting `AppServiceProvider::PAGE_SCOPED_WIDGETS`:** Silent first-render success, then a poll-tick crash. See Pattern 3.
- **`wire:poll` interval shorter than or misaligned with the cache TTL:** CONTEXT.md D-03 already locks this at 30s = TTL exactly; do not diverge to "feel more live" — every mismatched poll below TTL just re-fetches the same cached value for no benefit, and a poll above TTL delays surfacing a fresh recalculation.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Read-through caching with expiry | A custom static-property or file-based memoization scheme | `Cache::remember($key, $ttl, $callback)` | Already the framework-idiomatic tool, already backed by a migrated `database` cache store, already has a proven syntax precedent in this codebase (`SaldosBadge.php`) |
| Line chart rendering | A new Recharts component | `resources/js/charts/components/LineChart.jsx` via `ChartRouter.jsx`'s `line` kind | Already built, already tested (`ValidationProgressChartTest.php`), already consumes the exact `{labels, datasets}` shape this widget will produce |
| Cumulative/running-total math | Any bespoke SQL window function (`SUM() OVER (ORDER BY hour)`) — sqlite support for window functions is version-dependent and adds another MySQL/sqlite parity risk | A simple PHP `array_reduce`/running `$runningTotal +=` loop over sorted hour buckets | Trivial in PHP, zero DB-portability risk, and the row count for a single Día D window (hours × one campaign) is small enough that PHP-side computation costs nothing measurable |

**Key insight:** This phase's "expensive per-tick query" problem is solved entirely at the `Cache::remember()` layer, not by micro-optimizing the aggregation query itself. Once the query only runs once per 30-second window (regardless of how many admins are polling), the aggregation can be as simple/readable as the existing `RejectionReasonsStackedAreaChart` precedent without any real performance risk.

## Common Pitfalls

### Pitfall 1: MySQL vs sqlite date-function divergence
**What goes wrong:** A `GROUP BY DATE_FORMAT(voted_at, '%Y-%m-%d %H:00:00')` (MySQL) query works locally against the `sigma_betha_backup` MySQL DB but throws or returns wrong buckets when the test suite runs against sqlite (`phpunit.xml` forces `DB_CONNECTION=sqlite`, `:memory:`).
**Why it happens:** MySQL and sqlite use different date-function names/behavior (`DATE_FORMAT` vs `strftime`), and even `BirthdayWidget`'s driver-switch approach (functional, but verbose) shows this is a recurring source of bugs in this codebase.
**How to avoid:** Bucket in PHP/Carbon (Pattern 1 above), exactly like `RejectionReasonsStackedAreaChart` (23-03) already does for week-bucketing. Confirmed HIGH confidence — this is a codebase-verified precedent, not speculation.
**Warning signs:** Any raw SQL string containing `DATE_FORMAT`, `strftime`, or `DB::raw` with a date-truncation expression in a new widget's `getData()`.

### Pitfall 2: Page-scoped widget missing from `AppServiceProvider::PAGE_SCOPED_WIDGETS`
**What goes wrong:** Widget renders correctly on first page load, then `Livewire\Exceptions\ComponentNotFoundException` is thrown on the very first `wire:poll` tick.
**Why it happens:** Livewire's alias↔class resolution only auto-registers classes under `config('livewire.class_namespace')` (`App\Livewire`). `App\Filament\Widgets\*` classes attached via a Page's `getHeaderWidgets()`/`getFooterWidgets()` (as opposed to a panel's global `->widgets([...])`) resolve fine on `mount()` (called with the FQCN directly) but fail on the snapshot-driven poll request unless explicitly registered via `Livewire::component()`.
**How to avoid:** Add the new widget class to both the `use` imports and the `PAGE_SCOPED_WIDGETS` array in `app/Providers/AppServiceProvider.php`, and add it to `PageScopedWidgetRegistrationTest.php`'s `->with([...])` dataset (see Code Examples).
**Warning signs:** Widget content is visible on first load in a manual browser check, but the browser console shows a Livewire error after ~30s (this phase's poll interval).

### Pitfall 3: `ChartCard.jsx` empty state falls back to a generic message
**What goes wrong:** Before Día D opens (zero `VoteRecord` rows) or before any `ElectionEvent` is marked active, the chart silently shows "No hay datos para el período seleccionado." (the `default` empty-state copy) instead of a Día-D-specific message.
**Why it happens:** `EMPTY_STATE_COPY` in `resources/js/charts/components/ChartCard.jsx` is a fixed map keyed by the widget's `emptyReason` field; unrecognized keys fall through to `default`.
**How to avoid:** Add new key(s) to `EMPTY_STATE_COPY` for this widget's two distinct empty states — no active election event (`no_active_event`) and an active event with zero votes so far (`no_votes_yet`) — and emit the matching `emptyReason` string from `getData()`. This is a small, in-scope frontend edit (one object literal, no new component).
**Warning signs:** A Browser test asserting a Día-D-specific empty-state string fails because the generic default text renders instead.

### Pitfall 4: `isChartDataEmpty()` treats an all-null/all-empty dataset as empty — confirm this widget's shape is covered
**What goes wrong:** None expected — verified during this research. `isChartDataEmpty('line', data)` (in `chartjs-adapter.js`) checks `labels.length === 0 || datasets.length === 0`, which is exactly the shape this widget emits for its empty states (`labels: [], datasets: []`). No adapter change needed.
**Confidence:** HIGH — read directly from `resources/js/charts/lib/chartjs-adapter.js`.

### Pitfall 5: `array` cache store in tests is per-process, not per-request
**What goes wrong:** A naive Feature/Unit test might expect `Cache::remember()` to "not hit the DB twice" across what look like two separate calls, but forget that Laravel's `array` cache store (`CACHE_STORE=array` in `phpunit.xml`) is scoped to the single PHP process running that test — which is exactly what's needed here, but worth being explicit about in the test's assertions (e.g., assert the underlying model query only ran once via `DB::listen()` or a spy, not assert cross-request persistence).
**How to avoid:** Write the cache-effectiveness test as: seed N `VoteRecord`s, call `getData()` twice within the same test, assert identical output AND assert the query only executed once (e.g., via `\Illuminate\Support\Facades\DB::listen()` counting `vote_records` queries, or by seeding additional records between the two calls and asserting the second call still returns the pre-seed count).

## Code Examples

### Reference: existing polling widget's Pest Unit test (pattern to follow, adapted)
```php
// Source: tests/Unit/DiaDStatsOverviewTest.php (existing, verbatim structure)
declare(strict_types=1);

use App\Filament\Widgets\DiaDLiveVotingChart;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\VoteRecord;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns cumulative hourly data without error', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $event = ElectionEvent::factory()->for($campaign)->create(['is_active' => true]);
    VoteRecord::factory()->for($campaign)->for($event, 'electionEvent')->create(['voted_at' => now()]);

    $widget = new DiaDLiveVotingChart();
    $method = new ReflectionMethod(DiaDLiveVotingChart::class, 'getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['labels', 'datasets']);
});
```

### Reference: page-scoped widget registration regression test (must extend, not just copy)
```php
// tests/Feature/PageScopedWidgetRegistrationTest.php — ADD DiaDLiveVotingChart::class
// to the existing ->with([...]) dataset (13 entries today, becomes 14). Do not create a
// new test file for this — this is the established single regression guard for the
// entire "ComponentNotFoundException on poll" bug class.
```

### Reference: Browser test for a cached/polling chart (adapt from `ValidationProgressChartTest.php`)
```php
// Source: tests/Browser/ValidationProgressChartTest.php (existing, verbatim structure to adapt)
it('renders DiaDLiveVotingChart as a real Recharts line chart with real campaign data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $event = ElectionEvent::factory()->for($campaign)->create(['is_active' => true]);
    $admin = User::factory()->withoutTwoFactor()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    VoteRecord::factory()->for($campaign)->for($event, 'electionEvent')->count(5)->create();

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dia-d')); // confirm actual route name at plan time

    $page->assertVisible('[data-chart-kind="line"]');
    $page->assertNoJavaScriptErrors();
});
```
**Open question flagged below:** confirm the exact route name Filament generates for `DiaD.php` (likely `filament.admin.pages.dia-d`, but not directly verified in this research pass — verify with `list-routes` at plan/implementation time).

## Runtime State Inventory

Not applicable — this is a greenfield addition (one new widget), not a rename/refactor/migration phase. Omitted per format guidance.

## Open Questions

1. **Should hours with zero new votes appear as a flat continuation of the running total, or be omitted (sparse)?**
   - What we know: CONTEXT.md explicitly flags this as Claude's discretion (D-05 area). The `LineChart.jsx` component itself has no opinion — it renders whatever `labels`/`data` arrays it's given.
   - What's unclear: Whether a sparse chart (only hours with activity) or a fully-backfilled chart (every hour from first vote to now, flat-lined where no new votes occurred) is more useful for an operator glancing at the page.
   - Recommendation: Backfill is more visually honest for a "live progress" chart (a flat line reads as "stalled," which is meaningful signal; a gap reads as "no data," which is ambiguous). The planner should pick one explicitly in the plan rather than leave it implicit in the code.

2. **Exact Filament route name for the `DiaD` page.**
   - What we know: `app/Filament/Pages/DiaD.php` has `protected string $view = 'filament.pages.dia-d';` and `navigationLabel`/`title` of "Jornada Electoral (Día D)".
   - What's unclear: The exact generated route name (Filament auto-generates from the class name, typically `filament.{panel}.pages.dia-d`, but this wasn't directly confirmed against `list-routes` output in this research pass).
   - Recommendation: Run `list-routes` (Laravel Boost tool, filtered to `dia-d`) at plan/implementation time to confirm before writing the Browser test's `route()` call.

3. **Whether the widget's `$sort` order should slot before/after `DiaDTerritorialProgressTable`.**
   - What we know: CONTEXT.md D-04 only says "alongside" the existing two widgets, not a specific position.
   - What's unclear: Visual ordering preference (chart above or below the territorial table).
   - Recommendation: Claude's discretion, low-stakes — append as the third `getHeaderWidgets()` entry (chart last) unless the UI-SPEC (if one is produced) says otherwise.

## Environment Availability

No external dependencies beyond the framework/database already in use (Laravel `Cache` facade, `database` cache driver, existing `cache`/`cache_locks` migrations, existing MySQL local backup DB `sigma_betha_backup`). No new services, CLIs, or packages needed. Skipped per format guidance (purely a code/config-level addition to an existing, fully-provisioned stack).

## Project Constraints (from CLAUDE.md)

- **Import statements:** Always explicit `use` statements; never namespace aliases or inline `\Fully\Qualified\Class` references. Applies to the new widget's `Cache`, `Carbon`, `VoteRecord`, `ElectionEvent`, `CampaignContext` imports.
- **PHP conventions:** Curly braces always; constructor property promotion where applicable (not needed here — `ChartWidget` has no constructor to add); explicit return types and parameter type hints on all new methods (`getData(): array`, `getChartKind(): string`, `getType(): string`).
- **Laravel conventions:** Avoid `DB::` facade / raw SQL where Eloquent suffices — reinforces the PHP/Carbon-bucketing recommendation over raw `DB::raw()` date-truncation. Never `env()` outside config files (N/A here — `CACHE_STORE` is already read via `config('cache.default')` internally by the `Cache` facade, no direct `env()` call needed in the widget).
- **Filament v4:** All actions extend `Filament\Actions\Action` (N/A — no actions in this widget). Icons via `Filament\Support\Icons\Heroicon` enum (N/A — chart widget has no icon).
- **Testing:** "Every change must have a test." A Unit test (`getData()` shape + cache-hit behavior per Pitfall 5) and a Browser test (real rendered chart, matching `ValidationProgressChartTest.php`'s pattern) are both required, consistent with `INFRA-04`'s already-established precedent of one Pest 4 Browser test per shipped chart widget.
- **Pint:** Run `vendor/bin/pint --dirty` before finalizing.
- **GSD workflow enforcement:** All file edits must go through `/gsd:execute-phase` (already the context this research feeds).

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| DAYD-05 | An admin/operator sees a live-updating line chart of Día D voting progress (`VoteRecord.voted_at` accumulated hourly), backed by a cached/pre-aggregated campaign-scoped endpoint that avoids expensive per-tick `COUNT` queries under concurrent election-day load | Pattern 1 (`Cache::remember()` inside `getData()`, campaign+event-scoped key) delivers the caching requirement; the PHP/Carbon hour-bucketing + running-total example delivers the "accumulated hourly" aggregation; Pattern 2/3 deliver correct Filament/Livewire wiring so the chart actually renders and polls without breaking; `LineChart.jsx` reuse delivers the "line chart" rendering requirement with zero new frontend component work |
</phase_requirements>

## Sources

### Primary (HIGH confidence — read directly from this codebase)
- `app/Models/VoteRecord.php` — columns, casts, relations
- `app/Models/ElectionEvent.php` — `is_active`, `campaign_id`, `HasCampaignContext`
- `database/migrations/2025_11_27_024002_create_vote_records_table.php` — base indexes (`voted_at`, `campaign_id`, `[voter_id, campaign_id]`)
- `database/migrations/2025_11_27_030617_add_election_event_id_to_vote_records_table.php` — composite index `[election_event_id, voted_at]` (already covers this phase's query)
- `database/migrations/0001_01_01_000001_create_cache_table.php` — confirms `database` cache driver has its table already migrated
- `config/cache.php` — confirms `default => env('CACHE_STORE', 'database')`, `database` store config (no extra connection/table override needed)
- `app/Filament/Widgets/ValidationProgressChart.php` — `getType()`/`getChartKind()` delegation pattern, empty-state pattern, `line` kind precedent
- `app/Filament/Widgets/DiaDStatsOverview.php`, `DiaDTerritorialProgressTable.php` — existing Día D widget conventions (polling, `applyVoterScope`/`scopedVoterQuery`, `CampaignContext::currentCampaign()`)
- `app/Filament/Widgets/RejectionReasonsStackedAreaChart.php` — the PHP/Carbon date-bucketing precedent, with an explicit in-code comment documenting why raw SQL was rejected
- `app/Filament/Pages/DiaD.php` — `getHeaderWidgets()`, `markVoted()`'s exact `ElectionEvent::where('is_active', true)->first()` call (precedent to reuse)
- `app/Models/Concerns/HasCampaignContext.php` — confirms `ElectionEvent` queries are auto-scoped via `CampaignContextScope`
- `app/Services/CampaignContext.php` — `currentCampaign()`/`currentCampaignId()` resolution logic
- `app/Providers/AppServiceProvider.php` — `PAGE_SCOPED_WIDGETS` const array and its registration loop (`Livewire::component()`)
- `resources/js/charts/ChartRouter.jsx`, `resources/js/charts/components/LineChart.jsx`, `resources/js/charts/lib/chartjs-adapter.js`, `resources/js/charts/components/ChartCard.jsx`, `resources/views/filament/widgets/react-chart.blade.php` — full end-to-end frontend wiring, confirmed the `line` kind and `{labels, datasets}` shape are already fully supported
- `vendor/filament/widgets/src/ChartWidget.php` — confirms `getCachedData()` is unrelated in-request memoization, not a conflict with `Cache::remember()`
- `app/Livewire/SaldosBadge.php` — only existing `Cache::remember()` call in the codebase (syntax reference)
- `database/factories/VoteRecordFactory.php` — factory shape for test seeding
- `tests/Unit/DiaDStatsOverviewTest.php`, `tests/Browser/ValidationProgressChartTest.php`, `tests/Feature/PageScopedWidgetRegistrationTest.php` — test patterns to follow/extend
- `phpunit.xml` — confirms `CACHE_STORE=array` and `DB_CONNECTION=sqlite`/`:memory:` for all test runs
- `.planning/STATE.md` (Phase 21 Plan 04/06, Phase 22 Plan 04, Phase 23 Plan 03/05 decision entries) — documents the `PAGE_SCOPED_WIDGETS` bug class and the MySQL/sqlite date-function pitfall as recurring, previously-fixed issues in this exact codebase
- `.planning/config.json` — confirms `workflow.nyquist_validation: false` (Validation Architecture section correctly omitted)

### Secondary (MEDIUM confidence)
- None — all findings for this phase were verifiable directly against the codebase; no external/WebSearch sources were needed given the phase is entirely internal-pattern-reuse.

### Tertiary (LOW confidence)
- Exact Filament-generated route name for `DiaD.php` (`filament.admin.pages.dia-d` assumed by convention, not directly confirmed via `list-routes`) — flagged in Open Questions.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies; `Cache`/`Carbon`/`ChartWidget` all already in use elsewhere in this exact codebase
- Architecture: HIGH — every pattern (caching-in-getData, getType/getChartKind delegation, PAGE_SCOPED_WIDGETS registration) is copied from working, tested code already in this repo
- Pitfalls: HIGH — all 5 pitfalls are either directly-observed codebase precedents (documented bug fixes in `.planning/STATE.md`) or directly-read source confirming a non-issue (Pitfall 4)

**Research date:** 2026-08-21
**Valid until:** Stable — no external ecosystem dependency; valid until this codebase's chart infrastructure or cache configuration changes (no expiry pressure)
