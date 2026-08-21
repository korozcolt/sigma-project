<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\VerificationCall;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders CallContactabilityFunnelChart as a real Recharts funnel with real call-attempt data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'call-contactability-funnel@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
    ]);
    VerificationCall::factory()->for($voter)->answered()->create(['attempt_number' => 1]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.resources.verification-calls.index'));

    $page->assertVisible('[data-chart-kind="funnel"]');
    $page->assertSee('Intento 1');
    $page->assertNoJavaScriptErrors();
});
