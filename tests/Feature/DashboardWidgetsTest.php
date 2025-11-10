<?php

use App\Enums\VoterStatus;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Filament\Widgets\TerritorialDistributionChart;
use App\Filament\Widgets\TopLeadersTable;
use App\Filament\Widgets\ValidationProgressChart;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;
use Livewire\Livewire;

uses()->group('dashboard-widgets');

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    // Crear estructura territorial
    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $this->municipality->id]);

    // Crear campaña activa
    $this->campaign = Campaign::factory()->create(['status' => 'active']);
});

test('campaign stats overview widget displays correctly', function () {
    Livewire::test(CampaignStatsOverview::class)
        ->assertOk();
});

test('campaign stats overview shows total voters stat', function () {
    // Crear algunos votantes
    Voter::factory()->count(10)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSee('Total de Votantes')
        ->assertSee('10');
});

test('campaign stats overview shows confirmed voters percentage', function () {
    // Crear votantes confirmados y pendientes
    Voter::factory()->count(8)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSee('Votantes Confirmados')
        ->assertSee('8');
});

test('campaign stats overview shows active leaders count', function () {
    // Crear líderes con votantes
    $leader1 = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $leader2 = User::factory()->create(['municipality_id' => $this->municipality->id]);

    $leader1->campaigns()->attach($this->campaign);
    $leader2->campaigns()->attach($this->campaign);

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader1->id,
    ]);

    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader2->id,
    ]);

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSee('Líderes Activos')
        ->assertSee('2');
});

test('campaign stats overview handles no active campaign', function () {
    // Cambiar estado de la campaña
    $this->campaign->update(['status' => 'draft']);

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSee('No hay campaña activa');
});

test('territorial distribution chart widget displays correctly', function () {
    Livewire::test(TerritorialDistributionChart::class)
        ->assertOk();
});

test('territorial distribution chart shows data for municipalities', function () {
    // Crear municipios adicionales
    $muni2 = Municipality::factory()->create(['department_id' => $this->municipality->department_id]);
    $muni3 = Municipality::factory()->create(['department_id' => $this->municipality->department_id]);

    // Crear votantes en diferentes municipios
    Voter::factory()->count(15)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    Voter::factory()->count(10)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $muni2->id,
    ]);

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $muni3->id,
    ]);

    Livewire::test(TerritorialDistributionChart::class)
        ->assertOk()
        ->assertSee('Distribución Territorial de Votantes');
});

test('territorial distribution chart handles no active campaign', function () {
    $this->campaign->update(['status' => 'draft']);

    Livewire::test(TerritorialDistributionChart::class)
        ->assertOk();
});

test('top leaders table widget displays correctly', function () {
    Livewire::test(TopLeadersTable::class)
        ->assertOk();
});

test('top leaders table shows leaders ordered by voters count', function () {
    // Crear líderes
    $leader1 = User::factory()->create([
        'name' => 'Líder 1',
        'municipality_id' => $this->municipality->id,
    ]);
    $leader2 = User::factory()->create([
        'name' => 'Líder 2',
        'municipality_id' => $this->municipality->id,
    ]);
    $leader3 = User::factory()->create([
        'name' => 'Líder 3',
        'municipality_id' => $this->municipality->id,
    ]);

    $leader1->campaigns()->attach($this->campaign);
    $leader2->campaigns()->attach($this->campaign);
    $leader3->campaigns()->attach($this->campaign);

    // Crear votantes
    Voter::factory()->count(15)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader1->id,
    ]);

    Voter::factory()->count(25)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader2->id,
    ]);

    Voter::factory()->count(10)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader3->id,
    ]);

    Livewire::test(TopLeadersTable::class)
        ->assertCanSeeTableRecords([$leader1, $leader2, $leader3])
        ->assertSee('Líder 2')
        ->assertSee('25');
});

test('top leaders table does not show users without voters', function () {
    $leader = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $userWithoutVoters = User::factory()->create(['municipality_id' => $this->municipality->id]);

    $leader->campaigns()->attach($this->campaign);
    $userWithoutVoters->campaigns()->attach($this->campaign);

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader->id,
    ]);

    Livewire::test(TopLeadersTable::class)
        ->assertCanSeeTableRecords([$leader])
        ->assertCanNotSeeTableRecords([$userWithoutVoters]);
});

test('validation progress chart widget displays correctly', function () {
    Livewire::test(ValidationProgressChart::class)
        ->assertOk();
});

test('validation progress chart shows data for last 30 days', function () {
    // Crear votantes con diferentes fechas de validación
    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'call_verified_at' => now()->subDays(15),
    ]);

    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'call_verified_at' => now()->subDays(5),
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'call_verified_at' => null,
    ]);

    Livewire::test(ValidationProgressChart::class)
        ->assertOk()
        ->assertSee('Progreso de Validación');
});

test('validation progress chart handles no active campaign', function () {
    $this->campaign->update(['status' => 'draft']);

    Livewire::test(ValidationProgressChart::class)
        ->assertOk();
});

test('all widgets render correctly without errors', function () {
    Livewire::test(CampaignStatsOverview::class)->assertOk();
    Livewire::test(TerritorialDistributionChart::class)->assertOk();
    Livewire::test(TopLeadersTable::class)->assertOk();
    Livewire::test(ValidationProgressChart::class)->assertOk();
});

test('widgets have correct sort order', function () {
    expect(CampaignStatsOverview::getSort())->toBe(0)
        ->and(ValidationProgressChart::getSort())->toBe(1)
        ->and(TerritorialDistributionChart::getSort())->toBe(2)
        ->and(TopLeadersTable::getSort())->toBe(3);
});
