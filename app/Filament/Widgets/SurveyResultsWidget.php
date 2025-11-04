<?php

namespace App\Filament\Widgets;

use App\Enums\QuestionType;
use App\Models\Survey;
use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use Filament\Widgets\ChartWidget;

class SurveyResultsWidget extends ChartWidget
{
    public ?int $surveyId = null;

    public ?int $questionId = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        if ($this->questionId) {
            $question = SurveyQuestion::find($this->questionId);

            return $question ? "Resultados: {$question->question_text}" : 'Resultados de Pregunta';
        }

        if ($this->surveyId) {
            $survey = Survey::find($this->surveyId);

            return $survey ? "Resultados: {$survey->title}" : 'Resultados de Encuesta';
        }

        return 'Resultados de Encuesta';
    }

    protected function getData(): array
    {
        // If showing specific question results
        if ($this->questionId) {
            return $this->getQuestionData();
        }

        // If showing overall survey results
        if ($this->surveyId) {
            return $this->getOverallSurveyData();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sin datos',
                    'data' => [0],
                ],
            ],
            'labels' => ['Sin encuesta seleccionada'],
        ];
    }

    protected function getType(): string
    {
        // Determine chart type based on question type
        if ($this->questionId) {
            $question = SurveyQuestion::find($this->questionId);
            if (! $question) {
                return 'bar';
            }

            return match ($question->question_type) {
                QuestionType::YES_NO => 'pie',
                QuestionType::SCALE => 'bar',
                QuestionType::SINGLE_CHOICE, QuestionType::MULTIPLE_CHOICE => 'bar',
                default => 'bar',
            };
        }

        return 'bar';
    }

    protected function getQuestionData(): array
    {
        $metrics = SurveyMetrics::where('survey_question_id', $this->questionId)
            ->latest('calculated_at')
            ->first();

        if (! $metrics || ! $metrics->distribution) {
            return [
                'datasets' => [
                    [
                        'label' => 'Respuestas',
                        'data' => [0],
                    ],
                ],
                'labels' => ['Sin datos'],
            ];
        }

        $labels = [];
        $data = [];
        $backgroundColor = [];

        $colors = [
            'rgba(59, 130, 246, 0.7)',   // blue
            'rgba(239, 68, 68, 0.7)',    // red
            'rgba(34, 197, 94, 0.7)',    // green
            'rgba(251, 146, 60, 0.7)',   // orange
            'rgba(168, 85, 247, 0.7)',   // purple
            'rgba(236, 72, 153, 0.7)',   // pink
            'rgba(14, 165, 233, 0.7)',   // cyan
            'rgba(234, 179, 8, 0.7)',    // yellow
        ];

        $colorIndex = 0;
        foreach ($metrics->distribution as $option => $stats) {
            $labels[] = $option;
            $data[] = $stats['count'] ?? 0;
            $backgroundColor[] = $colors[$colorIndex % count($colors)];
            $colorIndex++;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Respuestas',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderColor' => array_map(
                        fn ($color) => str_replace('0.7', '1', $color),
                        $backgroundColor
                    ),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOverallSurveyData(): array
    {
        $survey = Survey::with('questions')->find($this->surveyId);

        if (! $survey) {
            return [
                'datasets' => [
                    [
                        'label' => 'Respuestas',
                        'data' => [0],
                    ],
                ],
                'labels' => ['Sin datos'],
            ];
        }

        $labels = [];
        $data = [];

        foreach ($survey->questions as $question) {
            $metrics = SurveyMetrics::where('survey_question_id', $question->id)
                ->latest('calculated_at')
                ->first();

            $labels[] = substr($question->question_text, 0, 30).'...';
            $data[] = $metrics ? $metrics->total_responses : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Respuestas por pregunta',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'scales' => $this->getType() !== 'pie' ? [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ] : [],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
