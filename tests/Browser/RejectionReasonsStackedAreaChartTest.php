<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders RejectionReasonsStackedAreaChart as a real Recharts stacked area with real rejection data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'rejection-reasons-stacked-area@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);

    // Recharts' Area only renders a visible <path> when a series has more than 1 data
    // point (node_modules/recharts/lib/cartesian/Area.js: `points?.length > 1`) - a
    // single week bucket produces zero rendered .recharts-area elements even with
    // correct data, so this seeds 2 distinct weeks.
    ValidationHistory::factory()->for($voter, 'voter')->create([
        'previous_status' => VoterStatus::PENDING_REVIEW,
        'new_status' => VoterStatus::REJECTED_CENSUS,
        'created_at' => now()->subWeeks(2),
    ]);
    ValidationHistory::factory()->for($voter, 'voter')->create([
        'previous_status' => VoterStatus::PENDING_REVIEW,
        'new_status' => VoterStatus::REJECTED_CENSUS,
        'created_at' => now(),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    foreach (range(1, 10) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="stacked-area"]');
    $page->assertVisible('[data-chart-kind="stacked-area"] .recharts-area >> nth=0');
    $page->assertNoJavaScriptErrors();
});
