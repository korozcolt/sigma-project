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

it('renders SurveyResultsWidget with a pie chart for YES_NO questions and a bar chart for SCALE questions on the real survey edit page', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'survey-results-widget@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $yesNoQuestion = SurveyQuestion::factory()->for($survey)->yesNo()->create(['order' => 0]);
    $scaleQuestion = SurveyQuestion::factory()->for($survey)->scale()->create(['order' => 1]);

    SurveyMetrics::factory()->questionDistribution()->create([
        'survey_question_id' => $yesNoQuestion->id,
        'distribution' => [
            'Sí' => ['count' => 8, 'percentage' => 80.0],
            'No' => ['count' => 2, 'percentage' => 20.0],
        ],
    ]);
    SurveyMetrics::factory()->questionDistribution()->create([
        'survey_question_id' => $scaleQuestion->id,
        'distribution' => [
            '1' => ['count' => 1, 'percentage' => 10.0],
            '2' => ['count' => 2, 'percentage' => 20.0],
            '3' => ['count' => 4, 'percentage' => 40.0],
            '4' => ['count' => 2, 'percentage' => 20.0],
            '5' => ['count' => 1, 'percentage' => 10.0],
        ],
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.resources.surveys.edit', ['record' => $survey]));

    $page->assertVisible("[data-question-id=\"{$yesNoQuestion->id}\"][data-chart-kind=\"pie\"]");
    $page->assertVisible("[data-question-id=\"{$scaleQuestion->id}\"][data-chart-kind=\"bar\"]");
    $page->assertNoJavaScriptErrors();
});
