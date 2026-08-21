<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoteRecord;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders DiaDLiveVotingChart as a real Recharts line chart with real campaign data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'diad-live-voting-chart@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true]);
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);

    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
        'recorded_by' => $admin->id,
        'voted_at' => now(),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dia-d'));

    foreach (range(1, 5) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="line"]');
    $page->assertSee('Votación en Vivo');
    $page->assertNoJavaScriptErrors();
});

it('shows the no_active_event empty state when no election event is active', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'diad-live-voting-chart-empty@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dia-d'));

    foreach (range(1, 5) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertSee('No hay ningún evento electoral activo en este momento.');
    $page->assertNoJavaScriptErrors();
});
