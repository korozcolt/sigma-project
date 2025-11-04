<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('can navigate between questions', function () {
    $survey = Survey::factory()->create(['is_active' => true]);
    $questions = SurveyQuestion::factory()->count(3)->create(['survey_id' => $survey->id]);

    Volt::test('surveys.apply-survey', ['surveyId' => $survey->id])
        ->assertSet('currentQuestionIndex', 0)
        ->call('nextQuestion')
        ->assertSet('currentQuestionIndex', 1)
        ->call('previousQuestion')
        ->assertSet('currentQuestionIndex', 0);
});

it('prevents navigation to next if required question is unanswered', function () {
    $survey = Survey::factory()->create(['is_active' => true]);
    $question = SurveyQuestion::factory()->required()->create(['survey_id' => $survey->id]);
    $question2 = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    Volt::test('surveys.apply-survey', ['surveyId' => $survey->id])
        ->assertSet('currentQuestionIndex', 0)
        ->assertSet('canGoNext', false)
        ->set('responses.0', 'Test answer')
        ->assertSet('canGoNext', true);
});

it('can submit yes/no question response', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);
    $question = SurveyQuestion::factory()->yesNo()->required()->create([
        'survey_id' => $survey->id,
    ]);

    $this->actingAs($user);

    Volt::test('surveys.apply-survey', [
        'surveyId' => $survey->id,
        'voterId' => $voter->id,
    ])
        ->assertSet('completed', false)
        ->set('responses.0', 'Sí')
        ->call('submit')
        ->assertSet('completed', true);

    expect(SurveyResponse::count())->toBe(1);
    expect(SurveyResponse::first()->response_value)->toBe('Sí');
});

it('can submit scale question response', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);
    $question = SurveyQuestion::factory()->scale(1, 5)->required()->create([
        'survey_id' => $survey->id,
    ]);

    $this->actingAs($user);

    Volt::test('surveys.apply-survey', [
        'surveyId' => $survey->id,
        'voterId' => $voter->id,
    ])
        ->set('responses.0', '4')
        ->call('submit')
        ->assertSet('completed', true);

    expect(SurveyResponse::count())->toBe(1);
    expect(SurveyResponse::first()->response_value)->toBe('4');
});

it('can submit text question response', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);
    $question = SurveyQuestion::factory()->text()->required()->create([
        'survey_id' => $survey->id,
    ]);

    $this->actingAs($user);

    Volt::test('surveys.apply-survey', [
        'surveyId' => $survey->id,
        'voterId' => $voter->id,
    ])
        ->set('responses.0', 'This is my detailed answer to the question')
        ->call('submit')
        ->assertSet('completed', true);

    expect(SurveyResponse::count())->toBe(1);
    expect(SurveyResponse::first()->response_value)->toBe('This is my detailed answer to the question');
});

it('can submit multiple choice question response', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);
    $question = SurveyQuestion::factory()->multipleChoice()->required()->create([
        'survey_id' => $survey->id,
    ]);

    $this->actingAs($user);

    Volt::test('surveys.apply-survey', [
        'surveyId' => $survey->id,
        'voterId' => $voter->id,
    ])
        ->set('responses.0', ['Option 1', 'Option 3'])
        ->call('submit')
        ->assertSet('completed', true);

    expect(SurveyResponse::count())->toBe(1);

    $response = SurveyResponse::first();
    $decoded = json_decode($response->response_value, true);
    expect($decoded)->toBeArray();
    expect($decoded)->toContain('Option 1', 'Option 3');
});

it('validates that all required questions are answered', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);

    // Create 2 required questions
    SurveyQuestion::factory()->required()->create(['survey_id' => $survey->id]);
    SurveyQuestion::factory()->required()->create(['survey_id' => $survey->id]);

    $this->actingAs($user);

    Volt::test('surveys.apply-survey', [
        'surveyId' => $survey->id,
        'voterId' => $voter->id,
    ])
        ->set('responses.0', 'Answer 1')
        // Don't answer second question
        ->set('currentQuestionIndex', 1)
        ->call('submit')
        ->assertSet('completed', false);

    expect(SurveyResponse::count())->toBe(0);
});

it('calculates progress correctly', function () {
    $survey = Survey::factory()->create(['is_active' => true]);
    SurveyQuestion::factory()->count(4)->create(['survey_id' => $survey->id]);

    Volt::test('surveys.apply-survey', ['surveyId' => $survey->id])
        ->assertSet('progress', 25.0) // First question (index 0) = 1/4 = 25%
        ->call('nextQuestion')
        ->assertSet('progress', 50.0) // Second question (index 1) = 2/4 = 50%
        ->call('nextQuestion')
        ->assertSet('progress', 75.0); // Third question (index 2) = 3/4 = 75%
});

it('can submit survey with mix of required and optional questions', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);

    // 1 required, 2 optional
    SurveyQuestion::factory()->required()->create(['survey_id' => $survey->id]);
    SurveyQuestion::factory()->optional()->create(['survey_id' => $survey->id]);
    SurveyQuestion::factory()->optional()->create(['survey_id' => $survey->id]);

    $this->actingAs($user);

    Volt::test('surveys.apply-survey', [
        'surveyId' => $survey->id,
        'voterId' => $voter->id,
    ])
        ->set('responses.0', 'Required answer')
        // Skip optional questions
        ->set('currentQuestionIndex', 2)
        ->call('submit')
        ->assertSet('completed', true);

    // Should have 1 response (only the required one)
    expect(SurveyResponse::count())->toBe(1);
});
