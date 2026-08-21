<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders SurveyResponsesSparklineWidget as a real Recharts sparkline with real campaign-scoped active-survey response data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'survey-responses-sparkline@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    // Survey belongs to this test's campaign — proves the widget's Survey::forCampaign()
    // scoping picks THIS campaign's survey, not some other campaign's.
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true]);
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);
    SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'voter_id' => $voter->id,
        'responded_at' => now(),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    $page->assertSee('Respuestas de Encuestas — últimos 7 días');
    // Scoped to this widget's own <section> (Playwright strict mode rejects the bare
    // [data-chart-kind="sparkline"] selector once a second sparkline widget — e.g.
    // CampaignVotersSparklineWidget — is also on the same dashboard).
    $page->assertVisible('section.fi-section:has-text("Respuestas de Encuestas — últimos 7 días") [data-chart-kind="sparkline"]');
    $page->assertNoJavaScriptErrors();
});
