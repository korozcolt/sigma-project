<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders SurveyScaleGaugeChart and SurveyScaleHistogramChart for a SCALE question on the real survey edit page', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'survey-scale-gauge-histogram@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $scaleQuestion = SurveyQuestion::factory()->for($survey)->scale()->create(['order' => 0]);

    SurveyMetrics::factory()->questionAverage()->create([
        'survey_question_id' => $scaleQuestion->id,
        'total_responses' => 50,
        'average_value' => 3.50,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.resources.surveys.edit', ['record' => $survey]));

    // The histogram widget (3rd of 3 stacked widgets for this question) sits below the fold on
    // this page's default viewport height — Filament's lazy-loaded (x-intersect) widgets only
    // fetch/render once their placeholder intersects the viewport. A single scroll+wait tick is
    // not always reliable, so repeat it a few times (established precedent from
    // VoterStatusDonutChartTest/CoordinatorTeamStackedBarChartTest).
    for ($i = 0; $i < 5; $i++) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible("[data-question-id=\"{$scaleQuestion->id}\"][data-chart-kind=\"gauge\"]");
    $page->assertVisible("[data-question-id=\"{$scaleQuestion->id}\"][data-chart-kind=\"histogram\"]");
    $page->assertVisible("[data-question-id=\"{$scaleQuestion->id}\"][data-chart-kind=\"bar\"]");
    $page->assertNoJavaScriptErrors();
});
