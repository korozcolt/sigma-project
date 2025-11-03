<?php

use App\Enums\VoterStatus;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;

use function Pest\Laravel\assertDatabaseHas;

it('can create a validation history', function () {
    $voter = Voter::factory()->create();
    $user = User::factory()->create();

    $history = ValidationHistory::factory()->create([
        'voter_id' => $voter->id,
        'previous_status' => VoterStatus::PENDING_REVIEW,
        'new_status' => VoterStatus::VERIFIED_CENSUS,
        'validated_by' => $user->id,
        'validation_type' => 'census',
    ]);

    expect($history)->toBeInstanceOf(ValidationHistory::class);
    expect($history->previous_status)->toBe(VoterStatus::PENDING_REVIEW);
    expect($history->new_status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($history->validation_type)->toBe('census');

    assertDatabaseHas('validation_histories', [
        'voter_id' => $voter->id,
        'validated_by' => $user->id,
        'validation_type' => 'census',
    ]);
});

it('requires voter_id, previous_status, new_status, validated_by and validation_type', function () {
    expect(fn () => ValidationHistory::create([]))->toThrow(Exception::class);
});

it('casts status fields to VoterStatus enum', function () {
    $history = ValidationHistory::factory()->create();

    expect($history->previous_status)->toBeInstanceOf(VoterStatus::class);
    expect($history->new_status)->toBeInstanceOf(VoterStatus::class);
});

it('has voter relationship', function () {
    $history = ValidationHistory::factory()->create();

    expect($history->voter())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve voter', function () {
    $voter = Voter::factory()->create(['first_name' => 'Juan']);
    $history = ValidationHistory::factory()->create(['voter_id' => $voter->id]);

    $history->load('voter');

    expect($history->voter->id)->toBe($voter->id);
    expect($history->voter->first_name)->toBe('Juan');
});

it('has validator relationship', function () {
    $history = ValidationHistory::factory()->create();

    expect($history->validator())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve validator user', function () {
    $user = User::factory()->create(['name' => 'Validator User']);
    $history = ValidationHistory::factory()->create(['validated_by' => $user->id]);

    $history->load('validator');

    expect($history->validator->id)->toBe($user->id);
    expect($history->validator->name)->toBe('Validator User');
});

it('scope forVoter returns only histories for specific voter', function () {
    $voter1 = Voter::factory()->create();
    $voter2 = Voter::factory()->create();

    ValidationHistory::factory()->create(['voter_id' => $voter1->id]);
    ValidationHistory::factory()->create(['voter_id' => $voter1->id]);
    ValidationHistory::factory()->create(['voter_id' => $voter2->id]);

    $voter1Histories = ValidationHistory::forVoter($voter1->id)->get();

    expect($voter1Histories)->toHaveCount(2);
    expect($voter1Histories->every(fn ($h) => $h->voter_id === $voter1->id))->toBeTrue();
});

it('scope byType filters by validation type', function () {
    ValidationHistory::factory()->censusValidation()->create();
    ValidationHistory::factory()->censusValidation()->create();
    ValidationHistory::factory()->callValidation()->create();

    $censusHistories = ValidationHistory::byType('census')->get();

    expect($censusHistories)->toHaveCount(2);
    expect($censusHistories->every(fn ($h) => $h->validation_type === 'census'))->toBeTrue();
});

it('scope recent orders by most recent first', function () {
    $history1 = ValidationHistory::factory()->create(['created_at' => now()->subDays(2)]);
    $history2 = ValidationHistory::factory()->create(['created_at' => now()->subDays(1)]);
    $history3 = ValidationHistory::factory()->create(['created_at' => now()]);

    $histories = ValidationHistory::recent()->get();

    expect($histories->first()->id)->toBe($history3->id);
    expect($histories->last()->id)->toBe($history1->id);
});

it('can update validation history', function () {
    $history = ValidationHistory::factory()->create([
        'notes' => 'Original notes',
    ]);

    $history->update([
        'notes' => 'Updated notes',
    ]);

    expect($history->fresh()->notes)->toBe('Updated notes');
});

it('can delete validation history', function () {
    $history = ValidationHistory::factory()->create();
    $id = $history->id;

    $history->delete();

    expect(ValidationHistory::find($id))->toBeNull();
});

it('deleting voter cascades delete validation histories', function () {
    $voter = Voter::factory()->create();
    $history = ValidationHistory::factory()->create(['voter_id' => $voter->id]);

    $voter->forceDelete();

    expect(ValidationHistory::find($history->id))->toBeNull();
});

it('deleting validator user cascades delete validation histories', function () {
    $user = User::factory()->create();
    $history = ValidationHistory::factory()->create(['validated_by' => $user->id]);

    $user->forceDelete();

    expect(ValidationHistory::find($history->id))->toBeNull();
});

it('voter can have multiple validation histories', function () {
    $voter = Voter::factory()->create();

    ValidationHistory::factory()->count(3)->create(['voter_id' => $voter->id]);

    $voter->load('validationHistories');

    expect($voter->validationHistories)->toHaveCount(3);
});

it('factory censusValidation state works correctly', function () {
    $history = ValidationHistory::factory()->censusValidation()->create();

    expect($history->previous_status)->toBe(VoterStatus::PENDING_REVIEW);
    expect($history->new_status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($history->validation_type)->toBe('census');
});

it('factory callValidation state works correctly', function () {
    $history = ValidationHistory::factory()->callValidation()->create();

    expect($history->previous_status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($history->new_status)->toBe(VoterStatus::VERIFIED_CALL);
    expect($history->validation_type)->toBe('call');
});

it('factory manualValidation state works correctly', function () {
    $history = ValidationHistory::factory()->manualValidation()->create();

    expect($history->validation_type)->toBe('manual');
});

it('factory rejection state works correctly', function () {
    $history = ValidationHistory::factory()->rejection()->create();

    expect($history->previous_status)->toBe(VoterStatus::PENDING_REVIEW);
    expect($history->new_status)->toBe(VoterStatus::REJECTED_CENSUS);
    expect($history->validation_type)->toBe('census');
    expect($history->notes)->toContain('No se encontró en el censo electoral');
});
