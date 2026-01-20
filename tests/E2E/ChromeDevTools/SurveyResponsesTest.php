<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\VerificationCall;
use App\Enums\SurveyQuestionType;
use App\Enums\CallResult;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Chrome DevTools E2E Test for Survey Responses with Call History
 * Tests the business rule: Survey responses are linked to verification_call_id
 * and uniqueness is by (call + question), not by voter
 */
test('encuestas con histórico por llamada - flujo completo con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    // Create a survey with multiple questions
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'title' => 'Encuesta de Satisfacción',
        'description' => 'Encuesta para evaluar la experiencia del votante',
        'is_active' => true,
    ]);

    // Add different types of questions
    $yesNoQuestion = SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'question_text' => '¿Votará por nuestro candidato?',
        'question_type' => SurveyQuestionType::YES_NO,
        'is_required' => true,
        'order' => 1,
    ]);

    $scaleQuestion = SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'question_text' => '¿Qué tan probable es que vote? (1-10)',
        'question_type' => SurveyQuestionType::SCALE,
        'is_required' => true,
        'order' => 2,
        'scale_min' => 1,
        'scale_max' => 10,
    ]);

    $textQuestion = SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'question_text' => '¿Algún comentario adicional?',
        'question_type' => SurveyQuestionType::TEXT,
        'is_required' => false,
        'order' => 3,
    ]);

    // Create voter and verification call
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
        'phone' => '3001234567',
    ]);

    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer');

    // Create first verification call
    $firstCall = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::ANSWERED,
        'call_date' => now()->subMinutes(30),
        'attempt_number' => 1,
    ]);

    // Create second verification call (same voter, different call)
    $secondCall = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::ANSWERED,
        'call_date' => now()->subMinutes(15),
        'attempt_number' => 2,
    ]);

    // Authenticate as reviewer
    actingAs($reviewer);

    // Navigate to call center
    $snapshot = navigateToUrl(config('app.url') . '/admin/call-center');
    
    // Verify call center interface
    assertSeeTextInSnapshot($snapshot, 'Centro de Llamadas');
    assertSeeTextInSnapshot($snapshot, 'Historial de Llamadas');

    // Search for voter
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    // Verify voter appears with call history
    assertSeeTextInSnapshot($snapshot, $voter->first_name);
    assertSeeTextInSnapshot($snapshot, $voter->last_name);
    assertSeeTextInSnapshot($snapshot, '2 llamadas registradas');
    
    // Click on "Apply Survey" for first call
    clickElementInSnapshot($snapshot, "[data-call-id=\"{$firstCall->id}\"] [data-action=\"apply-survey\"]");
    
    // Wait for survey form
    $snapshot = waitForElementAndSnapshot('[data-testid="survey-form"]');
    
    // Verify survey form
    assertSeeTextInSnapshot($snapshot, 'Encuesta de Satisfacción');
    assertSeeTextInSnapshot($snapshot, '¿Votará por nuestro candidato?');
    assertSeeTextInSnapshot($snapshot, '¿Qué tan probable es que vote? (1-10)');
    assertSeeTextInSnapshot($snapshot, '¿Algún comentario adicional?');
    
    // Fill survey responses
    clickElementInSnapshot($snapshot, 'input[name="question_' . $yesNoQuestion->id . '"][value="yes"]');
    typeInFieldInSnapshot($snapshot, 'input[name="question_' . $scaleQuestion->id . '"]', '8');
    typeInFieldInSnapshot($snapshot, 'textarea[name="question_' . $textQuestion->id . '"]', 'Muy buena campaña');
    
    // Submit survey
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-survey"]');
    
    // Wait for success message
    $snapshot = waitForTextAndSnapshot('Encuesta aplicada exitosamente');
    assertSeeTextInSnapshot($snapshot, 'Respuestas guardadas para llamada #' . $firstCall->id);
    
    // Verify survey responses in database (linked to first call)
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $firstCall->id,
        'survey_question_id' => $yesNoQuestion->id,
        'response_value' => 'yes',
    ]);
    
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $firstCall->id,
        'survey_question_id' => $scaleQuestion->id,
        'response_value' => '8',
    ]);
    
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $firstCall->id,
        'survey_question_id' => $textQuestion->id,
        'response_value' => 'Muy buena campaña',
    ]);
    
    // Navigate back to call center and apply survey for second call
    $snapshot = navigateToUrl(config('app.url') . '/admin/call-center');
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    // Click on "Apply Survey" for second call (same questions)
    clickElementInSnapshot($snapshot, "[data-call-id=\"{$secondCall->id}\"] [data-action=\"apply-survey\"]");
    $snapshot = waitForElementAndSnapshot('[data-testid="survey-form"]');
    
    // Fill different responses for second call
    clickElementInSnapshot($snapshot, 'input[name="question_' . $yesNoQuestion->id . '"][value="no"]');
    typeInFieldInSnapshot($snapshot, 'input[name="question_' . $scaleQuestion->id . '"]', '3');
    typeInFieldInSnapshot($snapshot, 'textarea[name="question_' . $textQuestion->id . '"]', 'Necesita más información');
    
    // Submit second survey
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-survey"]');
    $snapshot = waitForTextAndSnapshot('Encuesta aplicada exitosamente');
    
    // Verify second call responses (should be different from first call)
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $secondCall->id,
        'survey_question_id' => $yesNoQuestion->id,
        'response_value' => 'no',
    ]);
    
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $secondCall->id,
        'survey_question_id' => $scaleQuestion->id,
        'response_value' => '3',
    ]);
    
    // Verify total responses (same question can have multiple responses for different calls)
    $totalYesNoResponses = \App\Models\SurveyResponse::where('survey_question_id', $yesNoQuestion->id)->count();
    expect($totalYesNoResponses)->toBe(2); // One for each call
});

test('historical de respuestas por votante con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'title' => 'Encuesta de Perfil',
        'is_active' => true,
    ]);

    $question = SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'question_text' => '¿Nivel de educación?',
        'question_type' => SurveyQuestionType::MULTIPLE_CHOICE,
        'is_required' => true,
        'order' => 1,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
        'phone' => '3001234567',
    ]);

    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer');

    // Create multiple calls for the same voter
    $calls = [];
    for ($i = 1; $i <= 3; $i++) {
        $calls[] = VerificationCall::factory()->create([
            'voter_id' => $voter->id,
            'caller_id' => $reviewer->id,
            'call_result' => CallResult::ANSWERED,
            'call_date' => now()->subMinutes($i * 10),
            'attempt_number' => $i,
        ]);
    }

    // Apply survey responses for each call
    $responses = ['Primaria', 'Secundaria', 'Universitaria'];
    foreach ($calls as $index => $call) {
        actingAs($reviewer);
        
        $snapshot = navigateToUrl(config('app.url') . '/admin/call-center');
        typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
        $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
        
        clickElementInSnapshot($snapshot, "[data-call-id=\"{$call->id}\"] [data-action=\"apply-survey\"]");
        $snapshot = waitForElementAndSnapshot('[data-testid="survey-form"]');
        
        // Select different education level each time
        clickElementInSnapshot($snapshot, "input[value=\"{$responses[$index]}\"]");
        clickElementInSnapshot($snapshot, 'button[data-testid="submit-survey"]');
        
        $snapshot = waitForTextAndSnapshot('Encuesta aplicada exitosamente');
    }

    // Verify historical responses exist
    $allResponses = \App\Models\SurveyResponse::where('survey_question_id', $question->id)->get();
    expect($allResponses)->toHaveCount(3);
    
    // Verify each response is linked to different call
    $callIds = $allResponses->pluck('verification_call_id')->unique();
    expect($callIds)->toHaveCount(3);
    
    // Verify responses are different
    $responseValues = $allResponses->pluck('response_value')->unique();
    expect($responseValues)->toHaveCount(3);
    
    // Verify all calls have responses
    foreach ($calls as $call) {
        assertDatabaseHas('survey_responses', [
            'verification_call_id' => $call->id,
            'survey_question_id' => $question->id,
        ]);
    }
});

test('prevención de duplicados en misma llamada con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $survey = Survey::factory()->create([
        'campaign_id' => $campaign->id,
        'title' => 'Encuesta Única',
        'is_active' => true,
    ]);

    $question = SurveyQuestion::factory()->create([
        'survey_id' => $survey->id,
        'question_text' => '¿Confirmó asistencia?',
        'question_type' => SurveyQuestionType::YES_NO,
        'is_required' => true,
        'order' => 1,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
        'phone' => '3001234567',
    ]);

    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer');

    $verificationCall = VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::ANSWERED,
        'call_date' => now(),
        'attempt_number' => 1,
    ]);

    actingAs($reviewer);

    // First application of survey
    $snapshot = navigateToUrl(config('app.url') . '/admin/call-center');
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    clickElementInSnapshot($snapshot, "[data-call-id=\"{$verificationCall->id}\"] [data-action=\"apply-survey\"]");
    $snapshot = waitForElementAndSnapshot('[data-testid="survey-form"]');
    
    clickElementInSnapshot($snapshot, 'input[name="question_' . $question->id . '"][value="yes"]');
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-survey"]');
    $snapshot = waitForTextAndSnapshot('Encuesta aplicada exitosamente');
    
    // Verify first response exists
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $verificationCall->id,
        'survey_question_id' => $question->id,
        'response_value' => 'yes',
    ]);

    // Try to apply same survey again (should update, not duplicate)
    $snapshot = navigateToUrl(config('app.url') . '/admin/call-center');
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    clickElementInSnapshot($snapshot, "[data-call-id=\"{$verificationCall->id}\"] [data-action=\"apply-survey\"]");
    $snapshot = waitForElementAndSnapshot('[data-testid="survey-form"]');
    
    // Change response to 'no'
    clickElementInSnapshot($snapshot, 'input[name="question_' . $question->id . '"][value="no"]');
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-survey"]');
    $snapshot = waitForTextAndSnapshot('Encuesta actualizada exitosamente');
    
    // Verify response was updated (not duplicated)
    assertDatabaseMissing('survey_responses', [
        'verification_call_id' => $verificationCall->id,
        'survey_question_id' => $question->id,
        'response_value' => 'yes',
    ]);
    
    assertDatabaseHas('survey_responses', [
        'verification_call_id' => $verificationCall->id,
        'survey_question_id' => $question->id,
        'response_value' => 'no',
    ]);
    
    // Still only one response exists for this call+question combination
    $totalResponses = \App\Models\SurveyResponse::where([
        'verification_call_id' => $verificationCall->id,
        'survey_question_id' => $question->id,
    ])->count();
    
    expect($totalResponses)->toBe(1);
});

/**
 * Helper Functions for Chrome DevTools MCP
 */

function navigateToUrl(string $url): array
{
    return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::navigate($url);
}

function assertSeeTextInSnapshot(array $snapshot, string $text): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::assertSeeText($text);
}

function clickElementInSnapshot(array &$snapshot, string $selector): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::click($selector);
    $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
}

function typeInFieldInSnapshot(array &$snapshot, string $selector, string $value): void
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::type($selector, $value);
    $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
}

function waitForElementAndSnapshot(string $selector, int $timeout = 10000): array
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::waitForElement($selector, $timeout);
    return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
}

function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
{
    \Tests\E2E\ChromeDevTools\ChromeDevToolsService::waitForText($text, $timeout);
    return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
}