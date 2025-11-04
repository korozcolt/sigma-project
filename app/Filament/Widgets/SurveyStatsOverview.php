<?php

namespace App\Filament\Widgets;

use App\Models\Survey;
use App\Models\SurveyMetrics;
use App\Models\SurveyResponse;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SurveyStatsOverview extends StatsOverviewWidget
{
    public ?int $surveyId = null;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        if (! $this->surveyId) {
            return $this->getGlobalStats();
        }

        return $this->getSurveyStats();
    }

    protected function getGlobalStats(): array
    {
        $totalSurveys = Survey::count();
        $activeSurveys = Survey::where('is_active', true)->count();
        $totalResponses = SurveyResponse::count();

        return [
            Stat::make('Total Encuestas', $totalSurveys)
                ->description('Encuestas creadas en el sistema')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Encuestas Activas', $activeSurveys)
                ->description('Encuestas disponibles actualmente')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Total Respuestas', number_format($totalResponses))
                ->description('Respuestas recibidas')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('info'),
        ];
    }

    protected function getSurveyStats(): array
    {
        $survey = Survey::with('questions')->find($this->surveyId);

        if (! $survey) {
            return [];
        }

        // Get overall metrics
        $overallMetrics = SurveyMetrics::where('survey_id', $this->surveyId)
            ->where('metric_type', 'overall')
            ->first();

        $totalQuestions = $survey->questions->count();
        $totalResponses = SurveyResponse::where('survey_id', $this->surveyId)
            ->distinct('voter_id')
            ->count('voter_id');

        $responseRate = $overallMetrics?->response_rate ?? 0;

        // Calculate completion rate (voters who answered all required questions)
        $completionRate = $this->calculateCompletionRate($survey);

        return [
            Stat::make('Total Preguntas', $totalQuestions)
                ->description('Preguntas en esta encuesta')
                ->descriptionIcon('heroicon-o-question-mark-circle')
                ->color('primary'),

            Stat::make('Respuestas Únicas', $totalResponses)
                ->description('Votantes que han respondido')
                ->descriptionIcon('heroicon-o-users')
                ->color('success')
                ->chart($this->getResponsesChart($survey)),

            Stat::make('Tasa de Respuesta', number_format($responseRate, 1).'%')
                ->description($responseRate > 50 ? 'Buena participación' : 'Baja participación')
                ->descriptionIcon($responseRate > 50 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($responseRate > 50 ? 'success' : 'warning'),

            Stat::make('Tasa de Completitud', number_format($completionRate, 1).'%')
                ->description('Encuestas completadas al 100%')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color($completionRate > 80 ? 'success' : ($completionRate > 50 ? 'warning' : 'danger')),
        ];
    }

    protected function calculateCompletionRate(Survey $survey): float
    {
        $requiredQuestions = $survey->questions->where('is_required', true);

        if ($requiredQuestions->isEmpty()) {
            return 100;
        }

        $votersWithResponses = SurveyResponse::where('survey_id', $survey->id)
            ->distinct('voter_id')
            ->pluck('voter_id');

        if ($votersWithResponses->isEmpty()) {
            return 0;
        }

        $completedCount = 0;

        foreach ($votersWithResponses as $voterId) {
            $answeredRequiredQuestions = SurveyResponse::where('survey_id', $survey->id)
                ->where('voter_id', $voterId)
                ->whereIn('survey_question_id', $requiredQuestions->pluck('id'))
                ->count();

            if ($answeredRequiredQuestions === $requiredQuestions->count()) {
                $completedCount++;
            }
        }

        return ($completedCount / $votersWithResponses->count()) * 100;
    }

    protected function getResponsesChart(Survey $survey): array
    {
        // Get last 7 days of responses
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = SurveyResponse::where('survey_id', $survey->id)
                ->whereDate('responded_at', $date)
                ->distinct('voter_id')
                ->count('voter_id');

            $data[] = $count;
        }

        return $data;
    }
}
