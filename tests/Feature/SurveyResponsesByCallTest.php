<?php

use App\Enums\CallResult;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use App\Models\VerificationCall;

test('las respuestas de encuesta se guardan asociadas a una llamada', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $caller = User::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    // Crear llamada de verificación
    $call = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::ANSWERED,
    ]);

    // Crear respuesta asociada a la llamada
    $response = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call->id,
        'response_value' => 'Respuesta de prueba',
    ]);

    expect($response->verification_call_id)->toBe($call->id);
    expect($response->verificationCall->id)->toBe($call->id);
});

test('la unicidad se hace por llamada + pregunta', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $caller = User::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    // Crear primera llamada
    $call1 = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::ANSWERED,
    ]);

    // Crear respuesta para la primera llamada
    $response1 = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call1->id,
        'response_text' => 'Primera respuesta',
    ]);

    // Crear segunda llamada (siguiente intento)
    $call2 = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::ANSWERED,
    ]);

    // Se puede crear otra respuesta para la misma pregunta pero con llamada diferente
    $response2 = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call2->id,
        'response_text' => 'Segunda respuesta',
    ]);

    expect($response1->verification_call_id)->toBe($call1->id);
    expect($response2->verification_call_id)->toBe($call2->id);
    expect($response1->id)->not->toBe($response2->id);

    // Verificar que ambas respuestas existen
    expect(SurveyResponse::where('survey_question_id', $question->id)->count())->toBe(2);
});

test('dentro de la misma llamada, re-guardar actualiza (no duplica)', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $caller = User::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    $call = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::ANSWERED,
    ]);

    // Crear respuesta inicial
    $response = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call->id,
        'response_text' => 'Respuesta inicial',
    ]);

    $responseId = $response->id;

    // Actualizar la misma respuesta
    $response->update(['response_text' => 'Respuesta actualizada']);

    // Verificar que no se duplicó
    expect(SurveyResponse::where('survey_question_id', $question->id)
        ->where('verification_call_id', $call->id)
        ->count())->toBe(1);

    expect($response->fresh()->response_text)->toBe('Respuesta actualizada');
    expect($response->fresh()->id)->toBe($responseId);
});

test('múltiples preguntas pueden tener respuestas en la misma llamada', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $caller = User::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);

    // Crear múltiples preguntas
    $question1 = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);
    $question2 = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    $call = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::ANSWERED,
    ]);

    // Crear respuestas para ambas preguntas en la misma llamada
    $response1 = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question1->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call->id,
        'response_text' => 'Respuesta pregunta 1',
    ]);

    $response2 = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question2->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call->id,
        'response_text' => 'Respuesta pregunta 2',
    ]);

    expect($response1->verification_call_id)->toBe($call->id);
    expect($response2->verification_call_id)->toBe($call->id);
    expect($response1->survey_question_id)->toBe($question1->id);
    expect($response2->survey_question_id)->toBe($question2->id);

    // Verificar que hay 2 respuestas para la misma llamada
    expect(SurveyResponse::where('verification_call_id', $call->id)->count())->toBe(2);
});

test('el histórico de respuestas se mantiene por llamada', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $caller = User::factory()->create();
    $survey = Survey::factory()->create(['campaign_id' => $campaign->id]);
    $question = SurveyQuestion::factory()->create(['survey_id' => $survey->id]);

    // Primer intento de llamada - no contesta
    $call1 = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::NO_ANSWER,
        'attempt_number' => 1,
    ]);

    // Segundo intento - contesta y responde
    $call2 = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
        'call_result' => CallResult::ANSWERED,
        'attempt_number' => 2,
    ]);

    // Solo la segunda llamada debería tener respuesta
    $response = SurveyResponse::factory()->create([
        'survey_id' => $survey->id,
        'survey_question_id' => $question->id,
        'voter_id' => $voter->id,
        'answered_by' => $caller->id,
        'verification_call_id' => $call2->id,
        'response_text' => 'Respuesta en segundo intento',
    ]);

    // Verificar que solo hay una respuesta asociada a la llamada exitosa
    expect(SurveyResponse::where('voter_id', $voter->id)
        ->where('survey_question_id', $question->id)
        ->count())->toBe(1);

    expect(SurveyResponse::where('verification_call_id', $call2->id)->count())->toBe(1);
    expect(SurveyResponse::where('verification_call_id', $call1->id)->count())->toBe(0);
});