<?php

use App\Filament\Widgets\SurveyScaleGaugeChart;
use App\Filament\Widgets\SurveyScaleHistogramChart;
use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use Livewire\Livewire;

/**
 * Invokes the protected ChartWidget::getData() method via reflection —
 * matches the established precedent in OwnershipScopedWidgetsTest.php.
 */
function invokeGetData(object $widgetInstance): array
{
    $method = new ReflectionMethod($widgetInstance, 'getData');
    $method->setAccessible(true);

    return $method->invoke($widgetInstance);
}

it('SurveyScaleGaugeChart returns empty payload with no_survey_responses when no questionId is set', function () {
    $instance = Livewire::test(SurveyScaleGaugeChart::class)->instance();

    $data = invokeGetData($instance);

    expect($data['labels'])->toBe([]);
    expect($data['datasets'][0]['data'])->toBe([]);
    expect($data['emptyReason'])->toBe('no_survey_responses');
});

it('SurveyScaleGaugeChart returns empty payload with resolved min/max when no matching SurveyMetrics row exists', function () {
    $question = SurveyQuestion::factory()->scale(1, 5)->create();

    $instance = Livewire::test(SurveyScaleGaugeChart::class, ['questionId' => $question->id])->instance();

    $data = invokeGetData($instance);

    expect($data['emptyReason'])->toBe('no_survey_responses');
    expect($data['min'])->toBe(1);
    expect($data['max'])->toBe(5);
});

it('SurveyScaleGaugeChart returns empty payload when total_responses is 0', function () {
    $question = SurveyQuestion::factory()->scale(1, 5)->create();
    SurveyMetrics::factory()->questionAverage()->create([
        'survey_question_id' => $question->id,
        'total_responses' => 0,
    ]);

    $instance = Livewire::test(SurveyScaleGaugeChart::class, ['questionId' => $question->id])->instance();

    $data = invokeGetData($instance);

    expect($data['emptyReason'])->toBe('no_survey_responses');
});

it('SurveyScaleGaugeChart returns the average value when a real SurveyMetrics row exists', function () {
    $question = SurveyQuestion::factory()->scale(1, 5)->create();
    SurveyMetrics::factory()->questionAverage()->create([
        'survey_question_id' => $question->id,
        'total_responses' => 50,
        'average_value' => 3.50,
    ]);

    $instance = Livewire::test(SurveyScaleGaugeChart::class, ['questionId' => $question->id])->instance();

    $data = invokeGetData($instance);

    expect($data['labels'])->toBe(['Promedio']);
    expect($data['datasets'][0]['data'])->toBe([3.5]);
    expect($data)->not->toHaveKey('emptyReason');
});

it('SurveyScaleHistogramChart returns empty payload with no_survey_responses when no matching distribution exists', function () {
    $instance = Livewire::test(SurveyScaleHistogramChart::class)->instance();

    $data = invokeGetData($instance);

    expect($data['labels'])->toBe([]);
    expect($data['emptyReason'])->toBe('no_survey_responses');
});

it('SurveyScaleHistogramChart preserves ascending scale order and is never re-sorted by frequency', function () {
    $question = SurveyQuestion::factory()->scale(1, 5)->create();
    SurveyMetrics::factory()->questionAverage()->create([
        'survey_question_id' => $question->id,
        'total_responses' => 50,
        'distribution' => [
            '1' => ['count' => 5, 'percentage' => 10.0],
            '2' => ['count' => 10, 'percentage' => 20.0],
            '3' => ['count' => 15, 'percentage' => 30.0],
            '4' => ['count' => 15, 'percentage' => 30.0],
            '5' => ['count' => 5, 'percentage' => 10.0],
        ],
    ]);

    $instance = Livewire::test(SurveyScaleHistogramChart::class, ['questionId' => $question->id])->instance();

    $data = invokeGetData($instance);

    // PHP auto-coerces numeric string array keys ('1', '2', ...) to int keys,
    // so array_keys() returns ints here — order is what this test guards, not type.
    expect($data['labels'])->toBe([1, 2, 3, 4, 5]);
    expect($data['datasets'][0]['data'])->toBe([5, 10, 15, 15, 5]);
});
