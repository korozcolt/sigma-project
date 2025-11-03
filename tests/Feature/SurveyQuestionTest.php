<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a survey question', function () {
    $question = SurveyQuestion::factory()->create();

    expect($question)->toBeInstanceOf(SurveyQuestion::class);
    expect($question->id)->toBeInt();
});

it('requires survey_id, question_text and question_type', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    SurveyQuestion::factory()->create([
        'survey_id' => null,
        'question_text' => null,
        'question_type' => null,
    ]);
});

it('has default values', function () {
    $question = SurveyQuestion::factory()->create();

    expect($question->order)->toBeInt();
    expect($question->is_required)->toBeBool();
    expect($question->configuration)->toBeNull();
});

it('casts fields correctly', function () {
    $question = SurveyQuestion::factory()->create([
        'question_type' => QuestionType::SCALE,
        'is_required' => true,
        'configuration' => ['min' => 1, 'max' => 10],
        'order' => 5,
    ]);

    expect($question->question_type)->toBeInstanceOf(QuestionType::class);
    expect($question->is_required)->toBeBool();
    expect($question->configuration)->toBeArray();
    expect($question->order)->toBeInt();
});

it('has survey relationship', function () {
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    expect($question->survey)->toBeInstanceOf(Survey::class);
    expect($question->survey->id)->toBe($survey->id);
});

it('has responses relationship', function () {
    $question = SurveyQuestion::factory()->create();
    $responses = SurveyResponse::factory()->count(3)->create(['survey_question_id' => $question->id]);

    expect($question->responses)->toHaveCount(3);
});

it('scope required returns only required questions', function () {
    SurveyQuestion::factory()->count(2)->create(['is_required' => true]);
    SurveyQuestion::factory()->create(['is_required' => false]);

    $requiredQuestions = SurveyQuestion::required()->get();

    expect($requiredQuestions)->toHaveCount(2);
    expect($requiredQuestions->every(fn ($q) => $q->is_required === true))->toBeTrue();
});

it('scope optional returns only optional questions', function () {
    SurveyQuestion::factory()->count(2)->create(['is_required' => false]);
    SurveyQuestion::factory()->create(['is_required' => true]);

    $optionalQuestions = SurveyQuestion::optional()->get();

    expect($optionalQuestions)->toHaveCount(2);
    expect($optionalQuestions->every(fn ($q) => $q->is_required === false))->toBeTrue();
});

it('scope byType filters questions by type', function () {
    SurveyQuestion::factory()->count(2)->create(['question_type' => QuestionType::YES_NO]);
    SurveyQuestion::factory()->create(['question_type' => QuestionType::SCALE]);

    $yesNoQuestions = SurveyQuestion::byType(QuestionType::YES_NO)->get();

    expect($yesNoQuestions)->toHaveCount(2);
    expect($yesNoQuestions->every(fn ($q) => $q->question_type === QuestionType::YES_NO))->toBeTrue();
});

it('can update a survey question', function () {
    $question = SurveyQuestion::factory()->create(['question_text' => 'Original Question?']);

    $question->update(['question_text' => 'Updated Question?']);

    expect($question->fresh()->question_text)->toBe('Updated Question?');
});

it('can soft delete a survey question', function () {
    $question = SurveyQuestion::factory()->create();

    $question->delete();

    expect($question->trashed())->toBeTrue();
    expect(SurveyQuestion::count())->toBe(0);
    expect(SurveyQuestion::withTrashed()->count())->toBe(1);
});

it('deleting survey cascades delete questions', function () {
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    $survey->forceDelete();

    expect(SurveyQuestion::withTrashed()->count())->toBe(0);
});

it('factory yesNo state works correctly', function () {
    $question = SurveyQuestion::factory()->yesNo()->create();

    expect($question->question_type)->toBe(QuestionType::YES_NO);
    expect($question->configuration)->toBeNull();
});

it('factory scale state works correctly', function () {
    $question = SurveyQuestion::factory()->scale(1, 10, 'Bajo', 'Alto')->create();

    expect($question->question_type)->toBe(QuestionType::SCALE);
    expect($question->configuration)->toHaveKey('min');
    expect($question->configuration)->toHaveKey('max');
    expect($question->configuration['min'])->toBe(1);
    expect($question->configuration['max'])->toBe(10);
});

it('factory text state works correctly', function () {
    $question = SurveyQuestion::factory()->text(1000)->create();

    expect($question->question_type)->toBe(QuestionType::TEXT);
    expect($question->configuration['max_length'])->toBe(1000);
});

it('factory multipleChoice state works correctly', function () {
    $options = ['Opción A', 'Opción B', 'Opción C'];
    $question = SurveyQuestion::factory()->multipleChoice($options)->create();

    expect($question->question_type)->toBe(QuestionType::MULTIPLE_CHOICE);
    expect($question->configuration['options'])->toBe($options);
});

it('factory singleChoice state works correctly', function () {
    $options = ['Sí', 'No', 'Tal vez'];
    $question = SurveyQuestion::factory()->singleChoice($options)->create();

    expect($question->question_type)->toBe(QuestionType::SINGLE_CHOICE);
    expect($question->configuration['options'])->toBe($options);
});

it('factory required state works correctly', function () {
    $question = SurveyQuestion::factory()->required()->create();

    expect($question->is_required)->toBeTrue();
});

it('factory optional state works correctly', function () {
    $question = SurveyQuestion::factory()->optional()->create();

    expect($question->is_required)->toBeFalse();
});
