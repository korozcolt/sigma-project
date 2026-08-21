<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders ValidationProgressChart as a real Recharts line chart with real campaign data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'validation-progress-chart@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    Voter::factory()->count(5)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'call_verified_at' => now(),
    ]);
    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'call_verified_at' => null,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    $page->assertVisible('[data-chart-kind="line"]');
    $page->assertSee('Progreso de Validación');
    $page->assertNoJavaScriptErrors();
});
