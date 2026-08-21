<?php

use App\Enums\UserRole;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Filament\Widgets\TerritorialDistributionChart;
use App\Filament\Widgets\TopLeadersTable;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses()->group('ownership-scoped-widgets');

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);

    $this->coordinatorA = User::factory()->create();
    $this->coordinatorA->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorA->campaigns()->attach($this->campaign);

    $this->coordinatorB = User::factory()->create();
    $this->coordinatorB->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorB->campaigns()->attach($this->campaign);

    $this->leaderA1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorA->id]);
    $this->leaderA1->assignRole(UserRole::LEADER->value);
    $this->leaderA1->campaigns()->attach($this->campaign);

    $this->leaderA2 = User::factory()->create(['coordinator_user_id' => $this->coordinatorA->id]);
    $this->leaderA2->assignRole(UserRole::LEADER->value);
    $this->leaderA2->campaigns()->attach($this->campaign);

    $this->leaderB1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorB->id]);
    $this->leaderB1->assignRole(UserRole::LEADER->value);
    $this->leaderB1->campaigns()->attach($this->campaign);

    $this->leaderB2 = User::factory()->create(['coordinator_user_id' => $this->coordinatorB->id]);
    $this->leaderB2->assignRole(UserRole::LEADER->value);
    $this->leaderB2->campaigns()->attach($this->campaign);

    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leaderA1->id,
    ]);

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leaderA2->id,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leaderB1->id,
    ]);

    Voter::factory()->count(7)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leaderB2->id,
    ]);

    $this->areaCoordinatorA = User::factory()->create();
    $this->areaCoordinatorA->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinatorA->campaigns()->attach($this->campaign);
    $this->coordinatorA->update(['area_coordinator_user_id' => $this->areaCoordinatorA->id]);

    $this->areaCoordinatorB = User::factory()->create();
    $this->areaCoordinatorB->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinatorB->campaigns()->attach($this->campaign);
    $this->coordinatorB->update(['area_coordinator_user_id' => $this->areaCoordinatorB->id]);
});

function actAsWithCampaign(User $user, Campaign $campaign): void
{
    test()->actingAs($user);
    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');
}

test('coordinator sees only their own team in TopLeadersTable', function () {
    actAsWithCampaign($this->coordinatorA, $this->campaign);

    Livewire::test(TopLeadersTable::class)
        ->assertCanSeeTableRecords([$this->leaderA1, $this->leaderA2])
        ->assertCanNotSeeTableRecords([$this->leaderB1, $this->leaderB2]);
});

test('admin sees all leaders in TopLeadersTable, unrestricted', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    actAsWithCampaign($admin, $this->campaign);

    Livewire::test(TopLeadersTable::class)
        ->assertCanSeeTableRecords([
            $this->leaderA1,
            $this->leaderA2,
            $this->leaderB1,
            $this->leaderB2,
        ]);
});

test('leader sees only their own registered voters in CampaignStatsOverview', function () {
    actAsWithCampaign($this->leaderA1, $this->campaign);

    $ownCount = $this->leaderA1->registeredVoters()->count();
    $campaignTotal = $this->campaign->voters()->count();

    expect($ownCount)->not->toBe($campaignTotal);

    // assertSeeText/assertDontSeeText strip all tags (and their attributes) before
    // comparing, avoiding false-positive substring matches inside unrelated markup
    // (wire:snapshot checksums, SVG icon paths) that a raw HTML search would hit.
    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSeeText(number_format($ownCount))
        ->assertDontSeeText(number_format($campaignTotal));
});

test('coordinator sees only their team\'s registered voters in CampaignStatsOverview', function () {
    actAsWithCampaign($this->coordinatorA, $this->campaign);

    $teamCount = Voter::whereIn('registered_by', $this->coordinatorA->leaders()->pluck('id'))->count();
    $campaignTotal = $this->campaign->voters()->count();

    expect($teamCount)->not->toBe($campaignTotal);

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSeeText(number_format($teamCount))
        ->assertDontSeeText(number_format($campaignTotal));
});

test('admin sees campaign-wide total in CampaignStatsOverview, unchanged', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    actAsWithCampaign($admin, $this->campaign);

    $campaignTotal = $this->campaign->voters()->count();

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSeeText(number_format($campaignTotal));
});

test('articulador sees only their team\'s registered voters in CampaignStatsOverview', function () {
    actAsWithCampaign($this->areaCoordinatorA, $this->campaign);

    $teamTotal = 8; // leaderA1 (3) + leaderA2 (5)
    $campaignTotal = $this->campaign->voters()->count(); // 3+5+2+7 = 17

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSeeText(number_format($teamTotal))
        ->assertDontSeeText(number_format($campaignTotal));
});

test('a second articulador does not see the first articulador\'s team total in CampaignStatsOverview', function () {
    actAsWithCampaign($this->areaCoordinatorB, $this->campaign);

    $teamTotalB = 9; // leaderB1 (2) + leaderB2 (7)
    $teamTotalA = 8; // leaderA1 (3) + leaderA2 (5)

    Livewire::test(CampaignStatsOverview::class)
        ->assertOk()
        ->assertSeeText(number_format($teamTotalB))
        ->assertDontSeeText(number_format($teamTotalA));
});

test('articulador sees only their team\'s municipalities in TerritorialDistributionChart', function () {
    actAsWithCampaign($this->areaCoordinatorA, $this->campaign);

    $instance = Livewire::test(TerritorialDistributionChart::class)->instance();

    // getData() is protected on the base Filament ChartWidget, so it is invoked
    // via reflection rather than a direct method call.
    $method = new ReflectionMethod($instance, 'getData');
    $method->setAccessible(true);
    $data = $method->invoke($instance);

    // Post-VIZ-08 shape: getData() returns a 3-level nested {tree: [...]} (Departamento ->
    // Municipio -> Barrio) instead of a flat {datasets, labels} bar shape - sum every leaf's
    // "value" across the whole tree to recover the same team-scoped voter total.
    $sumTree = function (array $nodes) use (&$sumTree): int {
        return array_sum(array_map(
            fn (array $node) => isset($node['children']) ? $sumTree($node['children']) : ($node['value'] ?? 0),
            $nodes
        ));
    };

    expect($sumTree($data['tree']))->toBe(8);
});
