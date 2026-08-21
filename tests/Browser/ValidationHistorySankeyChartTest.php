<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders ValidationHistorySankeyChart as a real Recharts sankey with real transition data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'validation-history-sankey@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $voterA = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);
    $voterB = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);
    $voterC = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);

    // Seed a few distinct (previous_status, new_status) pairs so the Sankey renders a
    // non-degenerate diagram (Pitfall 5's recommended fixture-seeding guidance).
    ValidationHistory::factory()->for($voterA, 'voter')->censusValidation()->create();
    ValidationHistory::factory()->for($voterB, 'voter')->create(['previous_status' => null, 'new_status' => \App\Enums\VoterStatus::PENDING_REVIEW]);
    ValidationHistory::factory()->for($voterC, 'voter')->rejection()->create();

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    foreach (range(1, 10) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="sankey"]');
    $page->assertVisible('[data-chart-kind="sankey"] .recharts-sankey-node >> nth=0');
    $page->assertVisible('[data-chart-kind="sankey"] .recharts-sankey-link >> nth=0');
    $page->assertNoJavaScriptErrors();
});
