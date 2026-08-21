<?php

namespace App\Filament\Widgets;

use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use Filament\Widgets\ChartWidget;

class SurveyScaleHistogramChart extends ChartWidget
{
    protected string $view = 'filament.widgets.react-chart';

    public ?int $questionId = null;

    protected static ?int $sort = 4;

    public function getHeading(): ?string
    {
        $question = $this->questionId ? SurveyQuestion::find($this->questionId) : null;

        return $question
            ? "Distribución de Respuestas — {$question->question_text}"
            : 'Distribución de Respuestas';
    }

    protected function getData(): array
    {
        $emptyPayload = [
            'labels' => [],
            'datasets' => [['label' => 'Respuestas', 'data' => []]],
            'emptyReason' => 'no_survey_responses',
        ];

        if (! $this->questionId) {
            return $emptyPayload;
        }

        $metrics = SurveyMetrics::where('survey_question_id', $this->questionId)
            ->where('metric_type', 'question_average')
            ->latest('calculated_at')
            ->first();

        if (! $metrics || ! $metrics->distribution || $metrics->total_responses === 0) {
            return $emptyPayload;
        }

        $distribution = $metrics->distribution;

        return [
            'labels' => array_keys($distribution),
            'datasets' => [[
                'label' => 'Respuestas',
                // array_values() re-indexes from 0: $distribution's keys are the scale values
                // themselves (1, 2, 3...), and array_map() preserves those keys — without this,
                // a scale starting above 0 would json_encode() as a JS object, not an array.
                'data' => array_values(array_map(fn (array $bucket): int => $bucket['count'] ?? 0, $distribution)),
            ]],
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'histogram';
    }
}
