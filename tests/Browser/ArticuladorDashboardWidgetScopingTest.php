<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

it('shows an articulador only their own team\'s data on the /articulador dashboard, not the full campaign or another articulador\'s team', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    $areaCoordinatorA = User::factory()->withoutTwoFactor()->create(['email' => 'articulador-a@example.com', 'password' => 'password']);
    $areaCoordinatorA->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinatorA->campaigns()->attach($campaign->id);

    $coordinatorA = User::factory()->create(['area_coordinator_user_id' => $areaCoordinatorA->id]);
    $coordinatorA->assignRole(UserRole::COORDINATOR->value);
    $coordinatorA->campaigns()->attach($campaign->id);

    // Explicit, digit-4-free phone/email avoids a false-positive substring match against
    // the single-digit assertDontSee(4)/assertDontSee(15) checks below — Faker's random
    // phone/email generation would otherwise intermittently render a visible "4" or "15"
    // digit sequence unrelated to the actual voter-count scoping being tested here.
    $leaderA1 = User::factory()->create([
        'coordinator_user_id' => $coordinatorA->id,
        'name' => 'Leader Team A',
        'email' => 'leader-team-a@example.com',
        'phone' => '300 111 1111',
    ]);
    $leaderA1->assignRole(UserRole::LEADER->value);
    $leaderA1->campaigns()->attach($campaign->id);

    Voter::factory()->count(4)->create(['campaign_id' => $campaign->id, 'registered_by' => $leaderA1->id]);

    $areaCoordinatorB = User::factory()->create();
    $areaCoordinatorB->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinatorB->campaigns()->attach($campaign->id);

    $coordinatorB = User::factory()->create(['area_coordinator_user_id' => $areaCoordinatorB->id]);
    $coordinatorB->assignRole(UserRole::COORDINATOR->value);
    $coordinatorB->campaigns()->attach($campaign->id);

    $leaderB1 = User::factory()->create([
        'coordinator_user_id' => $coordinatorB->id,
        'name' => 'Leader Team B',
        'email' => 'leader-team-b@example.com',
        'phone' => '300 222 2222',
    ]);
    $leaderB1->assignRole(UserRole::LEADER->value);
    $leaderB1->campaigns()->attach($campaign->id);

    Voter::factory()->count(11)->create(['campaign_id' => $campaign->id, 'registered_by' => $leaderB1->id]);

    loginRealBrowserUser($areaCoordinatorA);

    $page = visit(route('filament.area_coordinator.pages.dashboard'));

    // CampaignStatsOverview: team A total (4) shown, name-based checks below prove
    // per-team scoping. No digit-substring assertion here — since Phase 21 migrated
    // TerritorialDistributionChart to Recharts/SVG, its axis ticks are real DOM text
    // (unlike Chart.js's canvas output, see comment below), so a bare
    // assertSee/assertDontSee(number_format($n)) can collide with an unrelated tick label.

    // TopLeadersTable: team A's leader row, not team B's
    $page->assertSee('Leader Team A');
    $page->assertDontSee('Leader Team B');

    // TerritorialDistributionChart: smoke-test it rendered without a server error
    // (per-articulador correctness for this widget's aggregate query is asserted at
    // the Livewire level in 19-01's OwnershipScopedWidgetsTest.php via ->getData(),
    // since chart.js canvas output isn't reliably plain-text-assertable in a real browser)
    $page->assertSee('Distribución Territorial');
});

it('shows a second articulador only their own team\'s data, proving cross-articulador isolation', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    $areaCoordinatorA = User::factory()->create();
    $areaCoordinatorA->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinatorA->campaigns()->attach($campaign->id);

    $coordinatorA = User::factory()->create(['area_coordinator_user_id' => $areaCoordinatorA->id]);
    $coordinatorA->assignRole(UserRole::COORDINATOR->value);
    $coordinatorA->campaigns()->attach($campaign->id);

    $leaderA1 = User::factory()->create([
        'coordinator_user_id' => $coordinatorA->id,
        'name' => 'Leader Team A',
        'email' => 'leader-team-a@example.com',
        'phone' => '300 111 1111',
    ]);
    $leaderA1->assignRole(UserRole::LEADER->value);
    $leaderA1->campaigns()->attach($campaign->id);

    Voter::factory()->count(4)->create(['campaign_id' => $campaign->id, 'registered_by' => $leaderA1->id]);

    $areaCoordinatorB = User::factory()->withoutTwoFactor()->create(['email' => 'articulador-b@example.com', 'password' => 'password']);
    $areaCoordinatorB->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinatorB->campaigns()->attach($campaign->id);

    $coordinatorB = User::factory()->create(['area_coordinator_user_id' => $areaCoordinatorB->id]);
    $coordinatorB->assignRole(UserRole::COORDINATOR->value);
    $coordinatorB->campaigns()->attach($campaign->id);

    $leaderB1 = User::factory()->create([
        'coordinator_user_id' => $coordinatorB->id,
        'name' => 'Leader Team B',
        'email' => 'leader-team-b@example.com',
        'phone' => '300 222 2222',
    ]);
    $leaderB1->assignRole(UserRole::LEADER->value);
    $leaderB1->campaigns()->attach($campaign->id);

    Voter::factory()->count(11)->create(['campaign_id' => $campaign->id, 'registered_by' => $leaderB1->id]);

    loginRealBrowserUser($areaCoordinatorB);

    $page = visit(route('filament.area_coordinator.pages.dashboard'));

    // CampaignStatsOverview: team B total (11) shown, name-based checks below prove
    // per-team scoping. No digit-substring assertion here — see comment in the first
    // test above (Recharts axis ticks are now real, text-assertable DOM content).

    $page->assertSee('Leader Team B');
    $page->assertDontSee('Leader Team A');
});
