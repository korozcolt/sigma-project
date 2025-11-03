<?php

declare(strict_types=1);

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a survey response', function () {
    $response = SurveyResponse::factory()->create();

    expect($response)->toBeInstanceOf(SurveyResponse::class);
    expect($response->id)->toBeInt();
});

it('requires survey_id, survey_question_id, voter_id and answered_by', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    SurveyResponse::factory()->create([
        'survey_id' => null,
        'survey_question_id' => null,
        'voter_id' => null,
        'answered_by' => null,
    ]);
});

it('casts responded_at to datetime', function () {
    $response = SurveyResponse::factory()->create([
        'responded_at' => now(),
    ]);

    expect($response->responded_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('has survey relationship', function () {
    $survey = Survey::factory()->create();
    $response = SurveyResponse::factory()->create(['survey_id' => $survey->id]);

    expect($response->survey)->toBeInstanceOf(Survey::class);
    expect($response->survey->id)->toBe($survey->id);
});

it('has question relationship', function () {
    $question = SurveyQuestion::factory()->create();
    $response = SurveyResponse::factory()->create(['survey_question_id' => $question->id]);

    expect($response->question)->toBeInstanceOf(SurveyQuestion::class);
    expect($response->question->id)->toBe($question->id);
});

it('has voter relationship', function () {
    $voter = Voter::factory()->create();
    $response = SurveyResponse::factory()->create(['voter_id' => $voter->id]);

    expect($response->voter)->toBeInstanceOf(Voter::class);
    expect($response->voter->id)->toBe($voter->id);
});

it('has answerer relationship', function () {
    $user = User::factory()->create();
    $response = SurveyResponse::factory()->create(['answered_by' => $user->id]);

    expect($response->answerer)->toBeInstanceOf(User::class);
    expect($response->answerer->id)->toBe($user->id);
});

it('scope forSurvey returns only responses for specific survey', function () {
    $survey1 = Survey::factory()->create();
    $survey2 = Survey::factory()->create();

    SurveyResponse::factory()->count(2)->create(['survey_id' => $survey1->id]);
    SurveyResponse::factory()->create(['survey_id' => $survey2->id]);

    $surveyResponses = SurveyResponse::forSurvey($survey1->id)->get();

    expect($surveyResponses)->toHaveCount(2);
    expect($surveyResponses->every(fn ($r) => $r->survey_id === $survey1->id))->toBeTrue();
});

it('scope forVoter returns only responses for specific voter', function () {
    $voter1 = Voter::factory()->create();
    $voter2 = Voter::factory()->create();

    SurveyResponse::factory()->count(2)->create(['voter_id' => $voter1->id]);
    SurveyResponse::factory()->create(['voter_id' => $voter2->id]);

    $voterResponses = SurveyResponse::forVoter($voter1->id)->get();

    expect($voterResponses)->toHaveCount(2);
    expect($voterResponses->every(fn ($r) => $r->voter_id === $voter1->id))->toBeTrue();
});

it('scope forQuestion returns only responses for specific question', function () {
    $question1 = SurveyQuestion::factory()->create();
    $question2 = SurveyQuestion::factory()->create();

    SurveyResponse::factory()->count(2)->create(['survey_question_id' => $question1->id]);
    SurveyResponse::factory()->create(['survey_question_id' => $question2->id]);

    $questionResponses = SurveyResponse::forQuestion($question1->id)->get();

    expect($questionResponses)->toHaveCount(2);
    expect($questionResponses->every(fn ($r) => $r->survey_question_id === $question1->id))->toBeTrue();
});

it('can update a survey response', function () {
    $response = SurveyResponse::factory()->create(['response_value' => 'Original Answer']);

    $response->update(['response_value' => 'Updated Answer']);

    expect($response->fresh()->response_value)->toBe('Updated Answer');
});

it('can delete a survey response', function () {
    $response = SurveyResponse::factory()->create();

    $response->delete();

    expect(SurveyResponse::count())->toBe(0);
});

it('deleting survey cascades delete responses', function () {
    $survey = Survey::factory()->create();
    $response = SurveyResponse::factory()->create(['survey_id' => $survey->id]);

    $survey->forceDelete();

    expect(SurveyResponse::count())->toBe(0);
});

it('deleting question cascades delete responses', function () {
    $question = SurveyQuestion::factory()->create();
    $response = SurveyResponse::factory()->create(['survey_question_id' => $question->id]);

    $question->forceDelete();

    expect(SurveyResponse::count())->toBe(0);
});

it('deleting voter cascades delete responses', function () {
    $voter = Voter::factory()->create();
    $response = SurveyResponse::factory()->create(['voter_id' => $voter->id]);

    $voter->forceDelete();

    expect(SurveyResponse::count())->toBe(0);
});

it('deleting answerer user cascades delete responses', function () {
    $user = User::factory()->create();
    $response = SurveyResponse::factory()->create(['answered_by' => $user->id]);

    $user->delete();

    expect(SurveyResponse::count())->toBe(0);
});

it('factory yesNoResponse state works correctly for yes', function () {
    $response = SurveyResponse::factory()->yesNoResponse(true)->create();

    expect($response->response_value)->toBe('Sí');
});

it('factory yesNoResponse state works correctly for no', function () {
    $response = SurveyResponse::factory()->yesNoResponse(false)->create();

    expect($response->response_value)->toBe('No');
});

it('factory scaleResponse state works correctly', function () {
    $response = SurveyResponse::factory()->scaleResponse(8)->create();

    expect($response->response_value)->toBe('8');
});

it('factory textResponse state works correctly', function () {
    $response = SurveyResponse::factory()->textResponse('Mi respuesta personalizada')->create();

    expect($response->response_value)->toBe('Mi respuesta personalizada');
});
