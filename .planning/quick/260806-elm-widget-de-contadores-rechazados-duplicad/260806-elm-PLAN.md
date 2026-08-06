---
phase: quick-260806-elm
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Widgets/RejectionsCountersOverview.php
  - app/Providers/Filament/ReportsPanelProvider.php
  - tests/Feature/Filament/RejectionsCountersOverviewTest.php
autonomous: true
requirements: [QUICK-260806-ELM]
user_setup: []

must_haves:
  truths:
    - "The reports panel dashboard shows a 3-tile widget: Rechazados, Duplicados, Fuera de Jurisdicción"
    - "Rechazados counts voters with status in {REJECTED_CENSUS, CENSUS_NOT_FOUND, CORRECTION_REQUIRED} OR a verification call with call_result in {REJECTED, INVALID_NUMBER, NOT_INTERESTED} — DUPLICATE and REJECTED_OUT_OF_SCOPE are excluded from this count"
    - "Duplicados counts only voters with status = DUPLICATE"
    - "Fuera de Jurisdicción counts only voters with status = REJECTED_OUT_OF_SCOPE"
    - "All three counts are scoped to the active campaign; a voter from a different campaign never contributes to any counter"
    - "With no active campaign selected, the widget renders zeros without throwing"
  artifacts:
    - path: "app/Filament/Widgets/RejectionsCountersOverview.php"
      provides: "3-stat StatsOverviewWidget (Rechazados / Duplicados / Fuera de Jurisdicción) scoped to CampaignContext::currentCampaign()"
      contains: "class RejectionsCountersOverview extends StatsOverviewWidget"
    - path: "app/Providers/Filament/ReportsPanelProvider.php"
      provides: "Widget registered in the reports panel alongside RejectionsReportTable/JurisdictionSummaryOverview"
      contains: "RejectionsCountersOverview::class"
    - path: "tests/Feature/Filament/RejectionsCountersOverviewTest.php"
      provides: "Pest coverage for correct counts, no-active-campaign, and cross-campaign isolation"
      contains: "RejectionsCountersOverview"
  key_links:
    - from: "app/Filament/Widgets/RejectionsCountersOverview.php"
      to: "App\\Services\\CampaignContext"
      via: "CampaignContext::currentCampaign() scoping every count"
      pattern: "CampaignContext::currentCampaign"
    - from: "app/Providers/Filament/ReportsPanelProvider.php"
      to: "App\\Filament\\Widgets\\RejectionsCountersOverview"
      via: "->widgets([...]) registration"
      pattern: "RejectionsCountersOverview::class"
---

<objective>
Add a new Filament "stat tiles" widget to the reports panel showing three independent, non-overlapping counters: Rechazados (status-rejected OR call-rejected, excluding Duplicados/Fuera de Jurisdicción), Duplicados (status = DUPLICATE), and Fuera de Jurisdicción (status = REJECTED_OUT_OF_SCOPE).

Purpose: give operators a fast at-a-glance summary of the three distinct rejection buckets that today only exist buried inside RejectionsReportTable's combined table and separate status filters — without changing that table's existing (intentionally broader) row-level criteria.
Output: `app/Filament/Widgets/RejectionsCountersOverview.php` registered in the reports panel, with Pest coverage.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Filament/Widgets/RejectionsReportTable.php
@app/Filament/Widgets/JurisdictionSummaryOverview.php
@app/Filament/Widgets/CampaignStatsOverview.php
@app/Providers/Filament/ReportsPanelProvider.php
@app/Enums/VoterStatus.php
@app/Enums/CallResult.php

<interfaces>
<!-- RejectionsReportTable.php's exact "rejected" criterion (status list + call_result list) — copy verbatim,
     but SPLIT OUT REJECTED_OUT_OF_SCOPE and never include DUPLICATE, per confirmed user decision: -->
```php
$rejectionCallResults = [
    CallResult::REJECTED->value,
    CallResult::INVALID_NUMBER->value,
    CallResult::NOT_INTERESTED->value,
];

// Rechazados counter uses ONLY these three statuses (REJECTED_OUT_OF_SCOPE excluded — own counter):
VoterStatus::REJECTED_CENSUS->value,
VoterStatus::CENSUS_NOT_FOUND->value,
VoterStatus::CORRECTION_REQUIRED->value,

->orWhereHas('verificationCalls', fn ($q2) => $q2->whereIn('call_result', $rejectionCallResults))
```

<!-- CampaignStatsOverview.php's no-active-campaign / url() pattern to mirror: -->
```php
$activeCampaign = CampaignContext::currentCampaign();
if (! $activeCampaign) {
    return Stat::make('Total de Apoyos', 0)
        ->description('No hay campaña seleccionada')
        ->descriptionIcon('heroicon-m-exclamation-triangle')
        ->color('warning');
}
// ...
Stat::make('Apoyos Confirmados', number_format($confirmed))
    ->url(VoterResource::getUrl('index', [
        'tableFilters' => ['status' => ['values' => [VoterStatus::CONFIRMED->value]]],
    ]));
```

<!-- VoterStatus enum values relevant here (App\Enums\VoterStatus): REJECTED_CENSUS, REJECTED_OUT_OF_SCOPE,
     CENSUS_NOT_FOUND, CORRECTION_REQUIRED, DUPLICATE (backed string enum, ->value gives the DB column value). -->

<!-- Voter model has a verificationCalls() relation already used identically in RejectionsReportTable.php. -->
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create RejectionsCountersOverview widget and register it in the reports panel</name>
  <files>app/Filament/Widgets/RejectionsCountersOverview.php, app/Providers/Filament/ReportsPanelProvider.php</files>
  <behavior>
    - No active campaign (CampaignContext::currentCampaign() is null): getStats() returns 3 Stat objects with value 0, no exception.
    - Active campaign with a voter status=REJECTED_CENSUS, one status=CENSUS_NOT_FOUND, one status=CORRECTION_REQUIRED, one PENDING_REVIEW with a rejected VerificationCall, one DUPLICATE, one REJECTED_OUT_OF_SCOPE, one CONFIRMED: Rechazados=4, Duplicados=1, Fuera de Jurisdicción=1 (CONFIRMED not counted anywhere).
    - A voter with status=DUPLICATE never counts toward Rechazados; a voter with status=REJECTED_OUT_OF_SCOPE never counts toward Rechazados or Duplicados (no overlap between the 3 counters).
    - A voter belonging to a different campaign (any qualifying status) never contributes to any of the 3 counts for the active campaign.
  </behavior>
  <action>
    Create `app/Filament/Widgets/RejectionsCountersOverview.php` extending `Filament\Widgets\StatsOverviewWidget`, following `JurisdictionSummaryOverview.php`'s and `CampaignStatsOverview.php`'s exact conventions (namespace `App\Filament\Widgets`, explicit `use` imports for `App\Enums\CallResult`, `App\Enums\VoterStatus`, `App\Filament\Resources\Voters\VoterResource`, `App\Models\Voter`, `App\Services\CampaignContext`, `Filament\Widgets\StatsOverviewWidget`, `Filament\Widgets\StatsOverviewWidget\Stat`, `Illuminate\Database\Eloquent\Builder`).

    Set `protected static ?int $sort = 6;` (same tier as RejectionsReportTable, which this widget summarizes) and a `protected ?string $heading = 'Contadores de Rechazos';`.

    In `getStats(): array`:
    - Resolve `$activeCampaign = CampaignContext::currentCampaign();`. If null, return 3 `Stat::make(...)` with value `0` (Rechazados, Duplicados, Fuera de Jurisdicción), each with a "No hay campaña seleccionada" description on the first tile only (mirror CampaignStatsOverview's `getTotalVotersStat()` no-campaign branch).
    - Build `$rejectionCallResults` array exactly as in RejectionsReportTable.php (`CallResult::REJECTED`, `CallResult::INVALID_NUMBER`, `CallResult::NOT_INTERESTED`).
    - `$rechazados = Voter::query()->where('campaign_id', $activeCampaign->id)->where(fn (Builder $q) => $q->whereIn('status', [VoterStatus::REJECTED_CENSUS->value, VoterStatus::CENSUS_NOT_FOUND->value, VoterStatus::CORRECTION_REQUIRED->value])->orWhereHas('verificationCalls', fn ($q2) => $q2->whereIn('call_result', $rejectionCallResults)))->count();` — deliberately excludes DUPLICATE and REJECTED_OUT_OF_SCOPE (confirmed user decision, no overlap between counters).
    - `$duplicados = Voter::query()->where('campaign_id', $activeCampaign->id)->where('status', VoterStatus::DUPLICATE->value)->count();`
    - `$fueraJurisdiccion = Voter::query()->where('campaign_id', $activeCampaign->id)->where('status', VoterStatus::REJECTED_OUT_OF_SCOPE->value)->count();`
    - Return 3 `Stat::make()` tiles with `number_format()` values, a short description + `descriptionIcon` + `color` each (danger for Rechazados and Fuera de Jurisdicción, warning for Duplicados — matching `VoterStatus::DUPLICATE`/`REJECTED_OUT_OF_SCOPE`/`REJECTED_CENSUS`'s own `getColor()` values), and — mirroring CampaignStatsOverview's `->url()` pattern since it already uses `->url()` on its Stats — a `->url(VoterResource::getUrl('index', ['tableFilters' => ['status' => ['values' => [...]]]]))` per tile: Duplicados and Fuera de Jurisdicción link with their single status value; Rechazados links with the 3-status array `[REJECTED_CENSUS, CENSUS_NOT_FOUND, CORRECTION_REQUIRED]` (acceptable partial match — the call-result branch has no table filter equivalent, same limitation implicitly accepted by RejectionsReportTable's own combined criteria).

    Register the widget in `app/Providers/Filament/ReportsPanelProvider.php`: add `use App\Filament\Widgets\RejectionsCountersOverview;` (keep the `use` block alphabetically sorted per Pint/CLAUDE.md) and add `RejectionsCountersOverview::class,` to the `->widgets([...])` array immediately before `RejectionsReportTable::class,` (same ordering precedent as `JurisdictionSummaryOverview::class` sitting right before `JurisdictionReportTable::class`).
  </action>
  <verify>
    <automated>vendor/bin/pint --dirty --test app/Filament/Widgets/RejectionsCountersOverview.php app/Providers/Filament/ReportsPanelProvider.php</automated>
  </verify>
  <done>RejectionsCountersOverview.php exists, extends StatsOverviewWidget, returns 3 non-overlapping campaign-scoped counters (0s when no active campaign), and is registered in ReportsPanelProvider next to RejectionsReportTable/JurisdictionSummaryOverview.</done>
</task>

<task type="auto">
  <name>Task 2: Pest coverage — correct counts, no-active-campaign, cross-campaign isolation</name>
  <files>tests/Feature/Filament/RejectionsCountersOverviewTest.php</files>
  <action>
    Create via `php artisan make:test --pest RejectionsCountersOverviewTest` (or write directly), mirroring `tests/Feature/Filament/JurisdictionSummaryOverviewTest.php`'s and `tests/Feature/Filament/RejectionsReportTableTest.php`'s exact setup style: `uses()->group('dashboard-widgets')`; `beforeEach` creates+assigns a `super_admin` role via `Role::firstOrCreate` and `actingAs`; use `Illuminate\Support\Facades\Session::put('campaign_context.campaign_id', ...)` + `Session::put('campaign_context.mode', 'single')` to set the active campaign (same pattern as both sibling tests — do not use `CampaignContext::setCampaignId()` directly, per the sibling convention).

    Write at least these tests:
    1. **Correct counts** — create one campaign; create voters covering: `REJECTED_CENSUS`, `CENSUS_NOT_FOUND`, `CORRECTION_REQUIRED`, one `PENDING_REVIEW` voter with a `VerificationCall::factory()->rejected()->create(['voter_id' => ...])` (mirrors `RejectionsReportTableTest`'s call-rejection setup), one `DUPLICATE`, one `REJECTED_OUT_OF_SCOPE`, and one `CONFIRMED` (noise, must not be counted anywhere). Use `Livewire::test(RejectionsCountersOverview::class)->instance()` + `(new ReflectionMethod($instance, 'getStats'))->invoke($instance)` (same reflection pattern as `JurisdictionSummaryOverviewTest`) and assert `$stats[0]->getValue() === number_format(4)` (Rechazados), `$stats[1]->getValue() === number_format(1)` (Duplicados), `$stats[2]->getValue() === number_format(1)` (Fuera de Jurisdicción).
    2. **No active campaign** — do not set the campaign_context session; assert all 3 stats resolve to `number_format(0)` without an exception (e.g. wrap the reflection call, or simply assert `Livewire::test(RejectionsCountersOverview::class)->assertOk()` plus the 3 zero values).
    3. **Cross-campaign isolation** — create a second campaign with one voter per qualifying status (`REJECTED_CENSUS`, `DUPLICATE`, `REJECTED_OUT_OF_SCOPE`) NOT belonging to the active campaign; assert the active campaign's 3 counts are unaffected (e.g. still 0 or unchanged from a baseline set in the active campaign) — prove the other campaign's voters never leak into the counts.

    Need `App\Models\Campaign`, `App\Models\Voter`, `App\Models\VerificationCall`, `App\Enums\VoterStatus`, `App\Enums\UserRole`, `App\Filament\Widgets\RejectionsCountersOverview`, `Livewire\Livewire`, `Spatie\Permission\Models\Role`, `Illuminate\Support\Facades\Session` — alphabetical `use` order per CLAUDE.md.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Filament/RejectionsCountersOverviewTest.php</automated>
  </verify>
  <done>3+ passing Pest tests proving correct non-overlapping counts, safe no-active-campaign zero-state, and campaign isolation.</done>
</task>

</tasks>

<verification>
- `php artisan test tests/Feature/Filament/RejectionsCountersOverviewTest.php` passes.
- `vendor/bin/pint --dirty` reports no outstanding issues across all 3 modified/created files.
- Manual (post-execution, per standing project preference — browser-verify before considering this production-ready): visit `/reports`, confirm the "Contadores de Rechazos" tile row appears near the "Informe de Rechazos" table, with plausible non-negative counts and no PHP error.
</verification>

<success_criteria>
- New widget shows exactly 3 non-overlapping counters (Rechazados / Duplicados / Fuera de Jurisdicción), campaign-scoped, zero-safe with no active campaign.
- Widget registered in ReportsPanelProvider alongside RejectionsReportTable/JurisdictionSummaryOverview.
- RejectionsReportTable.php and JurisdictionSummaryOverview.php are unmodified (read-only references).
- All listed tests pass; Pint clean; no new Composer dependencies.
</success_criteria>

<output>
After completion, create `.planning/quick/260806-elm-widget-de-contadores-rechazados-duplicad/260806-elm-SUMMARY.md`
</output>
