<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders CampaignVotersSparklineWidget as a real Recharts sparkline with real 7-day voter data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'campaign-voters-sparkline@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'created_at' => now(),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    $page->assertSee('Apoyos — últimos 7 días');
    // Scoped to this widget's own <section> (Playwright strict mode rejects the bare
    // [data-chart-kind="sparkline"] selector once a second sparkline widget — e.g.
    // SurveyResponsesSparklineWidget — is also on the same dashboard).
    $page->assertVisible('section.fi-section:has-text("Apoyos — últimos 7 días") [data-chart-kind="sparkline"]');
    $page->assertNoJavaScriptErrors();
});
