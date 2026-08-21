<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders CoordinatorTeamStackedBarChart as a real Recharts stacked bar with a real coordinator name', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'coordinator-stacked-bar@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $coordinator = User::factory()->create(['name' => 'Coordinador De Prueba']);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($campaign->id);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $coordinator->id,
        'status' => VoterStatus::VERIFIED_CENSUS,
    ]);
    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $coordinator->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    // CoordinatorTeamStackedBarChart is a lazy-loaded (x-intersect), sort-last widget
    // on a dashboard with ~20 widgets — a single scrollTo+wait doesn't reliably trigger
    // its intersection observer once the page has grown that tall, unlike the
    // higher-sort widgets covered by earlier Browser tests. Repeated scroll+wait ticks
    // let each newly-appended lazy widget re-trigger the observer as the page keeps
    // growing beneath the viewport.
    foreach (range(1, 8) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="stacked-bar"]');
    $page->assertSee('Coordinador De Prueba');
    $page->assertNoJavaScriptErrors();
});
