<?php

declare(strict_types=1);

use App\Enums\CallResult;
use App\Models\CallAssignment;
use App\Models\Survey;
use App\Models\User;
use App\Models\VerificationCall;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a verification call', function () {
    $voter = Voter::factory()->create();
    $caller = User::factory()->create();
    $assignment = CallAssignment::factory()->create();

    $call = VerificationCall::create([
        'voter_id' => $voter->id,
        'assignment_id' => $assignment->id,
        'caller_id' => $caller->id,
        'attempt_number' => 1,
        'call_date' => now(),
        'call_duration' => 120,
        'call_result' => CallResult::ANSWERED->value,
        'notes' => 'Test call',
    ]);

    expect($call)->toBeInstanceOf(VerificationCall::class)
        ->and($call->voter_id)->toBe($voter->id)
        ->and($call->caller_id)->toBe($caller->id)
        ->and($call->call_duration)->toBe(120)
        ->and($call->call_result)->toBe(CallResult::ANSWERED);
});

it('has correct relationships', function () {
    $call = VerificationCall::factory()->create();

    expect($call->voter)->toBeInstanceOf(Voter::class)
        ->and($call->caller)->toBeInstanceOf(User::class)
        ->and($call->assignment)->toBeInstanceOf(CallAssignment::class);
});

it('can have optional survey relationship', function () {
    $survey = Survey::factory()->create();
    $call = VerificationCall::factory()->withSurvey()->create([
        'survey_id' => $survey->id,
    ]);

    expect($call->survey)->toBeInstanceOf(Survey::class)
        ->and($call->survey_id)->toBe($survey->id);
});

it('casts call_result to CallResult enum', function () {
    $call = VerificationCall::factory()->create([
        'call_result' => CallResult::CONFIRMED->value,
    ]);

    expect($call->call_result)->toBeInstanceOf(CallResult::class)
        ->and($call->call_result)->toBe(CallResult::CONFIRMED);
});

it('can scope by voter', function () {
    $voter1 = Voter::factory()->create();
    $voter2 = Voter::factory()->create();

    VerificationCall::factory()->count(3)->create(['voter_id' => $voter1->id]);
    VerificationCall::factory()->count(2)->create(['voter_id' => $voter2->id]);

    $calls = VerificationCall::forVoter($voter1->id)->get();

    expect($calls)->toHaveCount(3);
});

it('can scope by caller', function () {
    $caller1 = User::factory()->create();
    $caller2 = User::factory()->create();

    VerificationCall::factory()->count(4)->create(['caller_id' => $caller1->id]);
    VerificationCall::factory()->count(2)->create(['caller_id' => $caller2->id]);

    $calls = VerificationCall::forCaller($caller1->id)->get();

    expect($calls)->toHaveCount(4);
});

it('can scope by call result', function () {
    VerificationCall::factory()->answered()->count(3)->create();
    VerificationCall::factory()->noAnswer()->count(2)->create();

    $answered = VerificationCall::byResult(CallResult::ANSWERED)->get();

    expect($answered)->toHaveCount(3);
});

it('can scope successful calls', function () {
    VerificationCall::factory()->answered()->count(2)->create();
    VerificationCall::factory()->confirmed()->count(3)->create();
    VerificationCall::factory()->callbackRequested()->count(1)->create();
    VerificationCall::factory()->noAnswer()->count(2)->create();

    $successful = VerificationCall::successful()->get();

    expect($successful)->toHaveCount(6); // answered + confirmed + callback
});

it('can scope calls requiring follow-up', function () {
    VerificationCall::factory()->noAnswer()->count(2)->create();
    VerificationCall::factory()->busy()->count(1)->create();
    VerificationCall::factory()->callbackRequested()->count(1)->create();
    VerificationCall::factory()->confirmed()->count(2)->create();

    $followUp = VerificationCall::needsFollowUp()->get();

    expect($followUp)->toHaveCount(4); // no_answer + busy + callback
});

it('can scope invalid number calls', function () {
    VerificationCall::factory()->wrongNumber()->count(2)->create();
    VerificationCall::factory()->invalidNumber()->count(1)->create();
    VerificationCall::factory()->answered()->count(3)->create();

    $invalid = VerificationCall::invalidNumber()->get();

    expect($invalid)->toHaveCount(3); // wrong + invalid
});

it('can scope recent calls', function () {
    VerificationCall::factory()->create(['call_date' => now()->subDays(3)]);
    VerificationCall::factory()->create(['call_date' => now()->subDays(10)]);
    VerificationCall::factory()->create(['call_date' => now()]);

    $recent = VerificationCall::recent(7)->get();

    expect($recent)->toHaveCount(2); // within 7 days
});

it('can scope calls with survey', function () {
    VerificationCall::factory()->withSurvey()->count(3)->create();
    VerificationCall::factory()->count(2)->create(['survey_id' => null]);

    $withSurvey = VerificationCall::withSurvey()->get();

    expect($withSurvey)->toHaveCount(3);
});

it('can scope survey completed calls', function () {
    VerificationCall::factory()->withSurvey(completed: true)->count(2)->create();
    VerificationCall::factory()->withSurvey(completed: false)->count(1)->create();
    VerificationCall::factory()->count(1)->create(['survey_id' => null]);

    $completed = VerificationCall::surveyCompleted()->get();

    expect($completed)->toHaveCount(2);
});

it('has isSuccessful helper method', function () {
    $successful = VerificationCall::factory()->confirmed()->create();
    $unsuccessful = VerificationCall::factory()->noAnswer()->create();

    expect($successful->isSuccessful())->toBeTrue()
        ->and($unsuccessful->isSuccessful())->toBeFalse();
});

it('has requiresFollowUp helper method', function () {
    $needsFollowUp = VerificationCall::factory()->noAnswer()->create();
    $noFollowUp = VerificationCall::factory()->confirmed()->create();

    expect($needsFollowUp->requiresFollowUp())->toBeTrue()
        ->and($noFollowUp->requiresFollowUp())->toBeFalse();
});

it('has isInvalidNumber helper method', function () {
    $invalid = VerificationCall::factory()->wrongNumber()->create();
    $valid = VerificationCall::factory()->answered()->create();

    expect($invalid->isInvalidNumber())->toBeTrue()
        ->and($valid->isInvalidNumber())->toBeFalse();
});

it('can schedule next attempt', function () {
    $call = VerificationCall::factory()->noAnswer()->create([
        'next_attempt_at' => null,
    ]);

    expect($call->next_attempt_at)->toBeNull();

    $call->scheduleNextAttempt(24);

    $fresh = $call->fresh();
    expect($fresh->next_attempt_at)->not->toBeNull();
});

it('can mark survey as completed', function () {
    $call = VerificationCall::factory()->withSurvey(completed: false)->create();

    expect($call->survey_completed)->toBeFalse();

    $call->markSurveyCompleted();

    expect($call->fresh()->survey_completed)->toBeTrue();
});

it('can get duration in minutes', function () {
    $call = VerificationCall::factory()->create(['call_duration' => 150]); // 2.5 minutes

    expect($call->getDurationInMinutes())->toBe(3); // ceiling
});

it('can get formatted duration', function () {
    $call1 = VerificationCall::factory()->create(['call_duration' => 125]); // 2:05
    $call2 = VerificationCall::factory()->create(['call_duration' => 65]); // 1:05
    $call3 = VerificationCall::factory()->create(['call_duration' => 600]); // 10:00

    expect($call1->getFormattedDuration())->toBe('2:05')
        ->and($call2->getFormattedDuration())->toBe('1:05')
        ->and($call3->getFormattedDuration())->toBe('10:00');
});

it('tracks multiple attempts for same voter', function () {
    $voter = Voter::factory()->create();
    $caller = User::factory()->create();

    $attempt1 = VerificationCall::factory()->firstAttempt()->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
    ]);

    $attempt2 = VerificationCall::factory()->followUp(2)->create([
        'voter_id' => $voter->id,
        'caller_id' => $caller->id,
    ]);

    expect(VerificationCall::forVoter($voter->id)->count())->toBe(2)
        ->and($attempt1->attempt_number)->toBe(1)
        ->and($attempt2->attempt_number)->toBe(2);
});

it('can create call with different result states', function () {
    $answered = VerificationCall::factory()->answered()->create();
    $noAnswer = VerificationCall::factory()->noAnswer()->create();
    $busy = VerificationCall::factory()->busy()->create();
    $wrong = VerificationCall::factory()->wrongNumber()->create();
    $rejected = VerificationCall::factory()->rejected()->create();
    $callback = VerificationCall::factory()->callbackRequested()->create();
    $notInterested = VerificationCall::factory()->notInterested()->create();
    $confirmed = VerificationCall::factory()->confirmed()->create();
    $invalid = VerificationCall::factory()->invalidNumber()->create();

    expect($answered->call_result)->toBe(CallResult::ANSWERED)
        ->and($noAnswer->call_result)->toBe(CallResult::NO_ANSWER)
        ->and($busy->call_result)->toBe(CallResult::BUSY)
        ->and($wrong->call_result)->toBe(CallResult::WRONG_NUMBER)
        ->and($rejected->call_result)->toBe(CallResult::REJECTED)
        ->and($callback->call_result)->toBe(CallResult::CALLBACK_REQUESTED)
        ->and($notInterested->call_result)->toBe(CallResult::NOT_INTERESTED)
        ->and($confirmed->call_result)->toBe(CallResult::CONFIRMED)
        ->and($invalid->call_result)->toBe(CallResult::INVALID_NUMBER);
});
