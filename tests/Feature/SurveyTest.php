<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a survey', function () {
    $survey = Survey::factory()->create();

    expect($survey)->toBeInstanceOf(Survey::class);
    expect($survey->id)->toBeInt();
});

it('requires campaign_id and title', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    Survey::factory()->create([
        'campaign_id' => null,
        'title' => null,
    ]);
});

it('has default values', function () {
    $survey = Survey::factory()->create();

    expect($survey->is_active)->toBeTrue();
    expect($survey->version)->toBe(1);
    expect($survey->parent_survey_id)->toBeNull();
});

it('casts fields correctly', function () {
    $survey = Survey::factory()->create([
        'is_active' => true,
        'version' => 2,
    ]);

    expect($survey->is_active)->toBeBool();
    expect($survey->version)->toBeInt();
});

it('has campaign relationship', function () {
    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);

    expect($survey->campaign)->toBeInstanceOf(Campaign::class);
    expect($survey->campaign->id)->toBe($campaign->id);
});

it('has parent survey relationship', function () {
    $parent = Survey::factory()->create();
    $child = Survey::factory()->create([
        'parent_survey_id' => $parent->id,
        'version' => 2,
    ]);

    expect($child->parentSurvey)->toBeInstanceOf(Survey::class);
    expect($child->parentSurvey->id)->toBe($parent->id);
});

it('has versions relationship', function () {
    $parent = Survey::factory()->create();
    $child1 = Survey::factory()->create([
        'parent_survey_id' => $parent->id,
        'version' => 2,
    ]);
    $child2 = Survey::factory()->create([
        'parent_survey_id' => $parent->id,
        'version' => 3,
    ]);

    expect($parent->versions)->toHaveCount(2);
    expect($parent->versions->pluck('id')->toArray())->toContain($child1->id);
    expect($parent->versions->pluck('id')->toArray())->toContain($child2->id);
});

it('has questions relationship', function () {
    $survey = Survey::factory()->create();
    $question1 = SurveyQuestion::factory()->create(['survey_id' => $survey->id, 'order' => 1]);
    $question2 = SurveyQuestion::factory()->create(['survey_id' => $survey->id, 'order' => 2]);

    expect($survey->questions)->toHaveCount(2);
    expect($survey->questions->first()->id)->toBe($question1->id);
});

it('has responses relationship', function () {
    $survey = Survey::factory()->create();
    $responses = SurveyResponse::factory()->count(3)->create(['survey_id' => $survey->id]);

    expect($survey->responses)->toHaveCount(3);
});

it('scope active returns only active surveys', function () {
    Survey::factory()->create(['is_active' => true]);
    Survey::factory()->create(['is_active' => true]);
    Survey::factory()->create(['is_active' => false]);

    $activeSurveys = Survey::active()->get();

    expect($activeSurveys)->toHaveCount(2);
    expect($activeSurveys->every(fn ($survey) => $survey->is_active === true))->toBeTrue();
});

it('scope forCampaign returns only surveys for specific campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    Survey::factory()->count(2)->create(['campaign_id' => $campaign1->id]);
    Survey::factory()->create(['campaign_id' => $campaign2->id]);

    $campaignSurveys = Survey::forCampaign($campaign1->id)->get();

    expect($campaignSurveys)->toHaveCount(2);
    expect($campaignSurveys->every(fn ($survey) => $survey->campaign_id === $campaign1->id))->toBeTrue();
});

it('can update a survey', function () {
    $survey = Survey::factory()->create(['title' => 'Original Title']);

    $survey->update(['title' => 'Updated Title']);

    expect($survey->fresh()->title)->toBe('Updated Title');
});

it('can soft delete a survey', function () {
    $survey = Survey::factory()->create();

    $survey->delete();

    expect($survey->trashed())->toBeTrue();
    expect(Survey::count())->toBe(0);
    expect(Survey::withTrashed()->count())->toBe(1);
});

it('can restore a soft deleted survey', function () {
    $survey = Survey::factory()->create();
    $survey->delete();

    $survey->restore();

    expect($survey->trashed())->toBeFalse();
    expect(Survey::count())->toBe(1);
});

it('deleting campaign cascades delete surveys', function () {
    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);

    $campaign->forceDelete();

    expect(Survey::withTrashed()->count())->toBe(0);
});

it('factory inactive state works correctly', function () {
    $survey = Survey::factory()->inactive()->create();

    expect($survey->is_active)->toBeFalse();
});

it('factory newVersion state works correctly', function () {
    $parent = Survey::factory()->create();
    $child = Survey::factory()->newVersion($parent->id, 3)->create();

    expect($child->parent_survey_id)->toBe($parent->id);
    expect($child->version)->toBe(3);
});
