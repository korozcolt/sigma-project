<?php

namespace App\Filament\Widgets;

use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use Filament\Widgets\ChartWidget;

class SurveyScaleGaugeChart extends ChartWidget
{
    protected string $view = 'filament.widgets.react-chart';

    public ?int $questionId = null;

    protected static ?int $sort = 3;

    public function getHeading(): ?string
    {
        $question = $this->questionId ? SurveyQuestion::find($this->questionId) : null;

        return $question
            ? "Promedio de Respuesta — {$question->question_text}"
            : 'Promedio de Respuesta';
    }

    protected function getData(): array
    {
        [$min, $max] = $this->resolveScaleBounds();

        $emptyPayload = fn (): array => [
            'labels' => [],
            'datasets' => [['label' => 'Promedio', 'data' => []]],
            'min' => $min,
            'max' => $max,
            'emptyReason' => 'no_survey_responses',
        ];

        if (! $this->questionId) {
            return $emptyPayload();
        }

        $metrics = SurveyMetrics::where('survey_question_id', $this->questionId)
            ->where('metric_type', 'question_average')
            ->latest('calculated_at')
            ->first();

        if (! $metrics || $metrics->total_responses === 0) {
            return $emptyPayload();
        }

        return [
            'labels' => ['Promedio'],
            'datasets' => [['label' => 'Promedio', 'data' => [(float) $metrics->average_value]]],
            'min' => $min,
            'max' => $max,
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'gauge';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveScaleBounds(): array
    {
        $question = $this->questionId ? SurveyQuestion::find($this->questionId) : null;
        $config = $question?->configuration ?? [];

        return [(int) ($config['min'] ?? 1), (int) ($config['max'] ?? 5)];
    }
}
