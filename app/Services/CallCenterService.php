<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CallResult;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\VerificationCall;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Collection;

class CallCenterService
{
    /**
     * Obtiene el próximo votante en cola para llamar
     */
    public function getNextVoterToCall(?int $callerId = null): ?Voter
    {
        return Voter::query()
            ->whereDoesntHave('verificationCalls', function ($query) {
                // No tiene llamadas exitosas
                $query->whereIn('call_result', [
                    CallResult::ANSWERED->value,
                    CallResult::CONFIRMED->value,
                ]);
            })
            ->orWhereHas('verificationCalls', function ($query) {
                // O la última llamada fue no contestó/ocupado (puede reintentar)
                $query->whereIn('call_result', [
                    CallResult::NO_ANSWER->value,
                    CallResult::BUSY->value,
                    CallResult::CALLBACK_REQUESTED->value,
                ])
                    ->where('attempt_number', '<', 3); // Máximo 3 intentos
            })
            ->whereNotNull('phone')
            ->orderByRaw('COALESCE((SELECT MAX(call_date) FROM verification_calls WHERE verification_calls.voter_id = voters.id), voters.created_at) ASC')
            ->first();
    }

    /**
     * Obtiene votantes pendientes de llamar (cola)
     */
    public function getPendingVoters(int $limit = 50): Collection
    {
        return Voter::query()
            ->whereDoesntHave('verificationCalls', function ($query) {
                $query->whereIn('call_result', [
                    CallResult::ANSWERED->value,
                    CallResult::CONFIRMED->value,
                ]);
            })
            ->orWhereHas('verificationCalls', function ($query) {
                $query->whereIn('call_result', [
                    CallResult::NO_ANSWER->value,
                    CallResult::BUSY->value,
                    CallResult::CALLBACK_REQUESTED->value,
                ])
                    ->where('attempt_number', '<', 3);
            })
            ->whereNotNull('phone')
            ->with(['municipality', 'neighborhood', 'registeredBy', 'verificationCalls' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->limit($limit)
            ->get();
    }

    /**
     * Inicia una nueva llamada
     */
    public function startCall(int $voterId, int $callerId, ?int $surveyId = null): VerificationCall
    {
        $voter = Voter::findOrFail($voterId);

        // Calcular número de intento
        $attemptNumber = $voter->verificationCalls()->count() + 1;

        // Crear registro de llamada
        return VerificationCall::create([
            'voter_id' => $voterId,
            'caller_id' => $callerId,
            'survey_id' => $surveyId,
            'attempt_number' => $attemptNumber,
            'call_date' => now(),
            'call_duration' => 0,
            'call_result' => CallResult::NO_ANSWER, // Default, se actualizará al finalizar
            'survey_completed' => false,
        ]);
    }

    /**
     * Guarda una respuesta de encuesta durante la llamada
     */
    public function saveQuestionResponse(
        int $verificationCallId,
        int $questionId,
        string $responseValue,
        int $answeredBy
    ): SurveyResponse {
        $call = VerificationCall::findOrFail($verificationCallId);

        return SurveyResponse::updateOrCreate(
            [
                'survey_id' => $call->survey_id,
                'survey_question_id' => $questionId,
                'verification_call_id' => $call->id,
            ],
            [
                'voter_id' => $call->voter_id,
                'response_value' => $responseValue,
                'answered_by' => $answeredBy,
                'responded_at' => now(),
            ]
        );
    }

    /**
     * Finaliza la llamada y guarda el resultado
     */
    public function endCall(
        int $verificationCallId,
        CallResult $result,
        int $duration,
        ?string $notes = null,
        bool $surveyCompleted = false
    ): VerificationCall {
        $call = VerificationCall::findOrFail($verificationCallId);

        $call->update([
            'call_result' => $result,
            'call_duration' => $duration,
            'notes' => $notes,
            'survey_completed' => $surveyCompleted,
        ]);

        // Si fue exitosa, programar próximo intento si es necesario
        if ($result === CallResult::CALLBACK_REQUESTED) {
            $call->scheduleNextAttempt(24); // 24 horas después
        }

        return $call->fresh();
    }

    /**
     * Obtiene el historial de llamadas de un reviewer
     */
    public function getReviewerCallHistory(int $reviewerId, int $limit = 20): Collection
    {
        return VerificationCall::query()
            ->where('caller_id', $reviewerId)
            ->with(['voter', 'survey'])
            ->latest('call_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtiene estadísticas del reviewer
     */
    public function getReviewerStats(int $reviewerId): array
    {
        $calls = VerificationCall::where('caller_id', $reviewerId);

        $totalCalls = $calls->count();
        $successfulCalls = (clone $calls)->whereIn('call_result', [
            CallResult::ANSWERED,
            CallResult::CONFIRMED,
        ])->count();

        $surveysCompleted = (clone $calls)->where('survey_completed', true)->count();

        $avgDuration = (clone $calls)->avg('call_duration') ?? 0;

        $callsToday = (clone $calls)
            ->whereDate('call_date', today())
            ->count();

        return [
            'total_calls' => $totalCalls,
            'successful_calls' => $successfulCalls,
            'success_rate' => $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 1) : 0,
            'surveys_completed' => $surveysCompleted,
            'avg_duration_seconds' => round((float) $avgDuration),
            'avg_duration_formatted' => gmdate('i:s', (int) $avgDuration),
            'calls_today' => $callsToday,
        ];
    }

    /**
     * Valida si se puede completar la encuesta
     */
    public function canCompleteSurvey(int $surveyId, int $voterId): bool
    {
        $survey = Survey::findOrFail($surveyId);

        // Obtener preguntas requeridas
        $requiredQuestions = $survey->questions()->where('is_required', true)->pluck('id');

        // Verificar que todas las preguntas requeridas tengan respuesta
        $answeredQuestions = SurveyResponse::where('survey_id', $surveyId)
            ->where('voter_id', $voterId)
            ->whereIn('survey_question_id', $requiredQuestions)
            ->pluck('survey_question_id');

        return $requiredQuestions->count() === $answeredQuestions->count();
    }

    /**
     * Obtiene las respuestas de un votante para una encuesta
     */
    public function getSurveyResponses(int $surveyId, int $voterId): Collection
    {
        return SurveyResponse::query()
            ->where('survey_id', $surveyId)
            ->where('voter_id', $voterId)
            ->with('question')
            ->get();
    }

    /**
     * Calcula el tiempo total en llamadas de hoy
     */
    public function getTodayCallTime(int $reviewerId): int
    {
        return VerificationCall::where('caller_id', $reviewerId)
            ->whereDate('call_date', today())
            ->sum('call_duration');
    }
}
