<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Survey;
use App\Models\SurveyMetrics;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;

class SurveyMetricsCalculator
{
    /**
     * Calculate and store overall survey metrics.
     */
    public function calculateOverallMetrics(Survey $survey): SurveyMetrics
    {
        $totalQuestions = $survey->questions()->count();
        $totalResponses = SurveyResponse::where('survey_id', $survey->id)
            ->distinct('voter_id')
            ->count('voter_id');

        $totalPossibleResponses = $totalQuestions > 0
            ? $survey->campaign->voters()->count() * $totalQuestions
            : 0;

        $responseRate = $totalPossibleResponses > 0
            ? ($totalResponses / $totalPossibleResponses) * 100
            : 0;

        return SurveyMetrics::updateOrCreate(
            [
                'survey_id' => $survey->id,
                'survey_question_id' => null,
                'metric_type' => 'overall',
            ],
            [
                'total_responses' => $totalResponses,
                'response_rate' => round($responseRate, 2),
                'metadata' => [
                    'total_questions' => $totalQuestions,
                    'total_voters' => $survey->campaign->voters()->count(),
                ],
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * Calculate metrics for a specific question.
     */
    public function calculateQuestionMetrics(SurveyQuestion $question): SurveyMetrics
    {
        $responses = SurveyResponse::where('survey_question_id', $question->id)->get();
        $totalResponses = $responses->count();

        $metrics = [
            'survey_id' => $question->survey_id,
            'survey_question_id' => $question->id,
            'total_responses' => $totalResponses,
            'calculated_at' => now(),
        ];

        // Calculate specific metrics based on question type
        switch ($question->question_type) {
            case QuestionType::YES_NO:
                $metrics['metric_type'] = 'question_distribution';
                $metrics['distribution'] = $this->calculateYesNoDistribution($responses);
                break;

            case QuestionType::SCALE:
                $metrics['metric_type'] = 'question_average';
                $metrics['average_value'] = $this->calculateScaleAverage($responses);
                $metrics['distribution'] = $this->calculateScaleDistribution($responses, $question);
                break;

            case QuestionType::SINGLE_CHOICE:
            case QuestionType::MULTIPLE_CHOICE:
                $metrics['metric_type'] = 'question_distribution';
                $metrics['distribution'] = $this->calculateChoiceDistribution($responses);
                break;

            case QuestionType::TEXT:
                $metrics['metric_type'] = 'question_text';
                $metrics['metadata'] = [
                    'sample_responses' => $responses->take(10)->pluck('response_value')->toArray(),
                ];
                break;
        }

        return SurveyMetrics::updateOrCreate(
            [
                'survey_id' => $question->survey_id,
                'survey_question_id' => $question->id,
                'metric_type' => $metrics['metric_type'],
            ],
            $metrics
        );
    }

    /**
     * Calculate all metrics for a survey (overall + all questions).
     */
    public function calculateAll(Survey $survey): array
    {
        $metrics = [];

        // Overall metrics
        $metrics['overall'] = $this->calculateOverallMetrics($survey);

        // Question metrics
        foreach ($survey->questions as $question) {
            $metrics['questions'][$question->id] = $this->calculateQuestionMetrics($question);
        }

        return $metrics;
    }

    /**
     * Calculate Yes/No distribution.
     */
    protected function calculateYesNoDistribution($responses): array
    {
        $distribution = [
            'Sí' => 0,
            'No' => 0,
        ];

        foreach ($responses as $response) {
            $value = $response->response_value;
            if (isset($distribution[$value])) {
                $distribution[$value]++;
            }
        }

        $total = array_sum($distribution);
        $percentages = [];
        foreach ($distribution as $key => $count) {
            $percentages[$key] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        }

        return $percentages;
    }

    /**
     * Calculate scale average.
     */
    protected function calculateScaleAverage($responses): float
    {
        if ($responses->isEmpty()) {
            return 0;
        }

        $sum = 0;
        $count = 0;

        foreach ($responses as $response) {
            if (is_numeric($response->response_value)) {
                $sum += (float) $response->response_value;
                $count++;
            }
        }

        return $count > 0 ? round($sum / $count, 2) : 0;
    }

    /**
     * Calculate scale distribution.
     */
    protected function calculateScaleDistribution($responses, SurveyQuestion $question): array
    {
        $config = $question->configuration ?? [];
        $min = $config['min'] ?? 1;
        $max = $config['max'] ?? 5;

        $distribution = [];
        for ($i = $min; $i <= $max; $i++) {
            $distribution[$i] = 0;
        }

        foreach ($responses as $response) {
            $value = (int) $response->response_value;
            if (isset($distribution[$value])) {
                $distribution[$value]++;
            }
        }

        $total = array_sum($distribution);
        $percentages = [];
        foreach ($distribution as $value => $count) {
            $percentages[$value] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        }

        return $percentages;
    }

    /**
     * Calculate choice distribution.
     */
    protected function calculateChoiceDistribution($responses): array
    {
        $distribution = [];

        foreach ($responses as $response) {
            $value = $response->response_value;

            // Handle multiple selections (JSON array)
            if (is_array(json_decode($value, true))) {
                $selections = json_decode($value, true);
                foreach ($selections as $selection) {
                    $distribution[$selection] = ($distribution[$selection] ?? 0) + 1;
                }
            } else {
                // Single selection
                $distribution[$value] = ($distribution[$value] ?? 0) + 1;
            }
        }

        $total = array_sum($distribution);
        $percentages = [];
        foreach ($distribution as $option => $count) {
            $percentages[$option] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];
        }

        // Sort by count descending
        uasort($percentages, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $percentages;
    }

    /**
     * Compare metrics between survey versions.
     */
    public function compareVersions(Survey $currentVersion, Survey $previousVersion): array
    {
        $currentMetrics = SurveyMetrics::where('survey_id', $currentVersion->id)->get();
        $previousMetrics = SurveyMetrics::where('survey_id', $previousVersion->id)->get();

        $comparison = [
            'current_total_responses' => $currentMetrics->where('metric_type', 'overall')->first()->total_responses ?? 0,
            'previous_total_responses' => $previousMetrics->where('metric_type', 'overall')->first()->total_responses ?? 0,
            'questions' => [],
        ];

        foreach ($currentVersion->questions as $question) {
            $currentQuestionMetric = $currentMetrics->where('survey_question_id', $question->id)->first();
            $comparison['questions'][$question->id] = [
                'question_text' => $question->question_text,
                'current_responses' => $currentQuestionMetric->total_responses ?? 0,
                'current_distribution' => $currentQuestionMetric->distribution ?? [],
            ];
        }

        return $comparison;
    }
}
