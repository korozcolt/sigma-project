<?php

declare(strict_types=1);

use App\Models\Survey;
use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Voter;
use App\Services\SurveyMetricsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create survey metrics', function () {
    $metrics = SurveyMetrics::factory()->create();

    expect($metrics)->toBeInstanceOf(SurveyMetrics::class);
    expect($metrics->id)->toBeInt();
});

it('has survey relationship', function () {
    $survey = Survey::factory()->create();
    $metrics = SurveyMetrics::factory()->create(['survey_id' => $survey->id]);

    expect($metrics->survey)->toBeInstanceOf(Survey::class);
    expect($metrics->survey->id)->toBe($survey->id);
});

it('has question relationship', function () {
    $question = SurveyQuestion::factory()->create();
    $metrics = SurveyMetrics::factory()->create(['survey_question_id' => $question->id]);

    expect($metrics->question)->toBeInstanceOf(SurveyQuestion::class);
    expect($metrics->question->id)->toBe($question->id);
});

it('casts fields correctly', function () {
    $metrics = SurveyMetrics::factory()->create([
        'distribution' => ['Yes' => 10, 'No' => 5],
        'metadata' => ['key' => 'value'],
        'calculated_at' => now(),
    ]);

    expect($metrics->distribution)->toBeArray();
    expect($metrics->metadata)->toBeArray();
    expect($metrics->calculated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('scope forSurvey returns only metrics for specific survey', function () {
    $survey1 = Survey::factory()->create();
    $survey2 = Survey::factory()->create();

    SurveyMetrics::factory()->count(2)->create(['survey_id' => $survey1->id]);
    SurveyMetrics::factory()->create(['survey_id' => $survey2->id]);

    $metrics = SurveyMetrics::forSurvey($survey1->id)->get();

    expect($metrics)->toHaveCount(2);
    expect($metrics->every(fn ($m) => $m->survey_id === $survey1->id))->toBeTrue();
});

it('scope forQuestion returns only metrics for specific question', function () {
    $question1 = SurveyQuestion::factory()->create();
    $question2 = SurveyQuestion::factory()->create();

    SurveyMetrics::factory()->create(['survey_question_id' => $question1->id]);
    SurveyMetrics::factory()->create(['survey_question_id' => $question2->id]);

    $metrics = SurveyMetrics::forQuestion($question1->id)->get();

    expect($metrics)->toHaveCount(1);
    expect($metrics->first()->survey_question_id)->toBe($question1->id);
});

it('calculator can calculate overall survey metrics', function () {
    $survey = Survey::factory()->create();
    $questions = SurveyQuestion::factory()->count(3)->create(['survey_id' => $survey->id]);
    $voters = Voter::factory()->count(5)->create(['campaign_id' => $survey->campaign_id]);

    // Create some responses
    foreach ($voters->take(3) as $voter) {
        foreach ($questions as $question) {
            SurveyResponse::factory()->create([
                'survey_id' => $survey->id,
                'survey_question_id' => $question->id,
                'voter_id' => $voter->id,
            ]);
        }
    }

    $calculator = new SurveyMetricsCalculator;
    $metrics = $calculator->calculateOverallMetrics($survey);

    expect($metrics->metric_type)->toBe('overall');
    expect($metrics->total_responses)->toBe(3); // 3 unique voters
    expect($metrics->response_rate)->toBeGreaterThan(0);
});

it('calculator can calculate yes/no question metrics', function () {
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->yesNo()->create(['survey_id' => $survey->id]);

    SurveyResponse::factory()->count(7)->yesNoResponse(true)->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
    ]);
    SurveyResponse::factory()->count(3)->yesNoResponse(false)->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
    ]);

    $calculator = new SurveyMetricsCalculator;
    $metrics = $calculator->calculateQuestionMetrics($question);

    expect($metrics->metric_type)->toBe('question_distribution');
    expect($metrics->distribution)->toHaveKey('Sí');
    expect($metrics->distribution)->toHaveKey('No');
    expect($metrics->distribution['Sí']['count'])->toBe(7);
    expect($metrics->distribution['No']['count'])->toBe(3);
});

it('calculator can calculate scale question metrics', function () {
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->scale(1, 5)->create(['survey_id' => $survey->id]);

    SurveyResponse::factory()->scaleResponse(5)->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
    ]);
    SurveyResponse::factory()->scaleResponse(3)->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
    ]);
    SurveyResponse::factory()->scaleResponse(4)->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
    ]);

    $calculator = new SurveyMetricsCalculator;
    $metrics = $calculator->calculateQuestionMetrics($question);

    expect($metrics->metric_type)->toBe('question_average');
    expect((float) $metrics->average_value)->toBe(4.0); // (5+3+4)/3 = 4
    expect($metrics->distribution)->toBeArray();
});

it('calculator can calculate all metrics for a survey', function () {
    $survey = Survey::factory()->create();
    $questions = SurveyQuestion::factory()->count(2)->create(['survey_id' => $survey->id]);

    SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $questions->first()->id,
    ]);

    $calculator = new SurveyMetricsCalculator;
    $result = $calculator->calculateAll($survey);

    expect($result)->toHaveKey('overall');
    expect($result)->toHaveKey('questions');
    expect($result['overall'])->toBeInstanceOf(SurveyMetrics::class);
    expect($result['questions'])->toHaveCount(2);
});

it('can delete survey metrics', function () {
    $metrics = SurveyMetrics::factory()->create();

    $metrics->delete();

    expect(SurveyMetrics::count())->toBe(0);
});

it('deleting survey cascades delete metrics', function () {
    $survey = Survey::factory()->create();
    $metrics = SurveyMetrics::factory()->create(['survey_id' => $survey->id]);

    $survey->forceDelete();

    expect(SurveyMetrics::count())->toBe(0);
});
