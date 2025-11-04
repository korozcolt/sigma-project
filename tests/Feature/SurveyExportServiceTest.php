<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use App\Services\SurveyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('exports survey responses to CSV', function () {
    Storage::fake('local');

    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'title' => 'Test Survey',
    ]);
    $questions = SurveyQuestion::factory()->count(3)->create(['survey_id' => $survey->id]);
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $user = User::factory()->create();

    // Create responses
    foreach ($questions as $question) {
        SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'survey_question_id' => $question->id,
            'voter_id' => $voter->id,
            'answered_by' => $user->id,
        ]);
    }

    $service = new SurveyExportService;
    $filePath = $service->exportToCSV($survey);

    expect(Storage::exists($filePath))->toBeTrue();

    $content = file_get_contents(Storage::path($filePath));
    expect($content)->toContain($voter->full_name);
    expect($content)->toContain($voter->document_number);
    expect($content)->toContain($user->name);

    Storage::delete($filePath);
});

it('exports survey summary with metrics to CSV', function () {
    Storage::fake('local');

    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'title' => 'Test Survey',
        'description' => 'Test Description',
    ]);
    $question = SurveyQuestion::factory()->yesNo()->create(['survey_id' => $survey->id]);

    // Create metrics
    SurveyMetrics::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => null,
        'metric_type' => 'overall',
        'total_responses' => 10,
        'response_rate' => 85.5,
    ]);

    SurveyMetrics::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'metric_type' => 'question_distribution',
        'total_responses' => 10,
        'distribution' => [
            'Sí' => ['count' => 7, 'percentage' => 70.0],
            'No' => ['count' => 3, 'percentage' => 30.0],
        ],
    ]);

    $service = new SurveyExportService;
    $filePath = $service->exportSummaryToCSV($survey);

    expect(Storage::exists($filePath))->toBeTrue();

    $content = file_get_contents(Storage::path($filePath));
    expect($content)->toContain('Test Survey');
    expect($content)->toContain('Test Description');
    expect($content)->toContain('MÉTRICAS GENERALES');
    expect($content)->toContain('85.5');
    expect($content)->toContain('DESGLOSE POR PREGUNTA');

    Storage::delete($filePath);
});

it('formats response values correctly in CSV', function () {
    Storage::fake('local');

    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $question = SurveyQuestion::factory()->multipleChoice()->create(['survey_id' => $survey->id]);
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    // Create response with JSON array
    SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'voter_id' => $voter->id,
        'response_value' => json_encode(['Option 1', 'Option 2', 'Option 3']),
    ]);

    $service = new SurveyExportService;
    $filePath = $service->exportToCSV($survey);

    $content = file_get_contents(Storage::path($filePath));
    expect($content)->toContain('Option 1, Option 2, Option 3');

    Storage::delete($filePath);
});

it('handles empty survey in export', function () {
    Storage::fake('local');

    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    SurveyQuestion::factory()->count(2)->create(['survey_id' => $survey->id]);

    $service = new SurveyExportService;
    $filePath = $service->exportToCSV($survey);

    expect(Storage::exists($filePath))->toBeTrue();

    Storage::delete($filePath);
});

it('cleans up old export files', function () {
    Storage::fake('local');

    // Create old file (simulate)
    Storage::put('exports/old-file.csv', 'content');
    Storage::put('exports/recent-file.csv', 'content');

    // Manipulate timestamps
    touch(Storage::path('exports/old-file.csv'), now()->subDays(2)->timestamp);
    touch(Storage::path('exports/recent-file.csv'), now()->timestamp);

    $service = new SurveyExportService;
    $deleted = $service->cleanupOldExports();

    expect($deleted)->toBeGreaterThan(0);
    expect(Storage::exists('exports/old-file.csv'))->toBeFalse();
    expect(Storage::exists('exports/recent-file.csv'))->toBeTrue();
});

it('exports multiple voters responses correctly', function () {
    Storage::fake('local');

    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    // Create 3 voters with responses
    $voters = Voter::factory()->count(3)->create(['campaign_id' => $campaign->id]);

    foreach ($voters as $voter) {
        SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'survey_question_id' => $question->id,
            'voter_id' => $voter->id,
        ]);
    }

    $service = new SurveyExportService;
    $filePath = $service->exportToCSV($survey);

    $content = file_get_contents(Storage::path($filePath));

    // Check all voters are in the export
    foreach ($voters as $voter) {
        expect($content)->toContain($voter->document_number);
    }

    Storage::delete($filePath);
});

it('includes BOM for UTF-8 encoding', function () {
    Storage::fake('local');

    $campaign = Campaign::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);

    $service = new SurveyExportService;
    $filePath = $service->exportToCSV($survey);

    $handle = fopen(Storage::path($filePath), 'r');
    $bom = fread($handle, 3);
    fclose($handle);

    expect($bom)->toBe(chr(0xEF).chr(0xBB).chr(0xBF));

    Storage::delete($filePath);
});
