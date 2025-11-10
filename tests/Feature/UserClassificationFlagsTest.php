<?php

use App\Models\User;

uses()->group('user-classification-flags');

test('user model has classification flags fillable', function () {
    $user = new User;

    $fillable = $user->getFillable();

    expect($fillable)
        ->toContain('is_vote_recorder')
        ->toContain('is_witness')
        ->toContain('witness_assigned_station')
        ->toContain('witness_payment_amount')
        ->toContain('is_special_coordinator');
});

test('classification flags have correct casts', function () {
    $user = new User;

    $casts = $user->getCasts();

    expect($casts)
        ->toHaveKey('is_vote_recorder', 'boolean')
        ->toHaveKey('is_witness', 'boolean')
        ->toHaveKey('is_special_coordinator', 'boolean')
        ->toHaveKey('witness_payment_amount', 'decimal:2');
});

test('classification flags have correct default values', function () {
    $user = User::factory()->create();

    expect($user->is_vote_recorder)->toBeFalse()
        ->and($user->is_witness)->toBeFalse()
        ->and($user->is_special_coordinator)->toBeFalse()
        ->and($user->witness_assigned_station)->toBeNull()
        ->and($user->witness_payment_amount)->toBeNull();
});

test('user can be created as vote recorder', function () {
    $user = User::factory()->create([
        'is_vote_recorder' => true,
    ]);

    expect($user->is_vote_recorder)->toBeTrue()
        ->and($user->is_witness)->toBeFalse()
        ->and($user->is_special_coordinator)->toBeFalse();
});

test('user can be created as witness with station and payment', function () {
    $user = User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 001-A',
        'witness_payment_amount' => 100000.50,
    ]);

    expect($user->is_witness)->toBeTrue()
        ->and($user->witness_assigned_station)->toBe('Mesa 001-A')
        ->and($user->witness_payment_amount)->toBe('100000.50');
});

test('user can be created as special coordinator', function () {
    $user = User::factory()->create([
        'is_special_coordinator' => true,
    ]);

    expect($user->is_special_coordinator)->toBeTrue()
        ->and($user->is_vote_recorder)->toBeFalse()
        ->and($user->is_witness)->toBeFalse();
});

test('user can have multiple classification flags simultaneously', function () {
    $user = User::factory()->create([
        'is_vote_recorder' => true,
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 123-B',
        'witness_payment_amount' => 150000.00,
        'is_special_coordinator' => true,
    ]);

    expect($user->is_vote_recorder)->toBeTrue()
        ->and($user->is_witness)->toBeTrue()
        ->and($user->witness_assigned_station)->toBe('Mesa 123-B')
        ->and($user->witness_payment_amount)->toBe('150000.00')
        ->and($user->is_special_coordinator)->toBeTrue();
});

test('voteRecorder factory state works', function () {
    $user = User::factory()->voteRecorder()->create();

    expect($user->is_vote_recorder)->toBeTrue()
        ->and($user->is_witness)->toBeFalse()
        ->and($user->is_special_coordinator)->toBeFalse();
});

test('witness factory state works', function () {
    $user = User::factory()->witness()->create();

    expect($user->is_witness)->toBeTrue()
        ->and($user->witness_assigned_station)->not->toBeNull()
        ->and($user->witness_payment_amount)->not->toBeNull();
});

test('witness factory state accepts custom station and payment', function () {
    $user = User::factory()->witness('Mesa 999-Z', 200000.75)->create();

    expect($user->is_witness)->toBeTrue()
        ->and($user->witness_assigned_station)->toBe('Mesa 999-Z')
        ->and($user->witness_payment_amount)->toBe('200000.75');
});

test('specialCoordinator factory state works', function () {
    $user = User::factory()->specialCoordinator()->create();

    expect($user->is_special_coordinator)->toBeTrue()
        ->and($user->is_vote_recorder)->toBeFalse()
        ->and($user->is_witness)->toBeFalse();
});

test('voteRecorders scope returns only vote recorders', function () {
    User::factory()->count(3)->create(['is_vote_recorder' => true]);
    User::factory()->count(2)->create(['is_vote_recorder' => false]);

    $voteRecorders = User::voteRecorders()->get();

    expect($voteRecorders)->toHaveCount(3)
        ->and($voteRecorders->every(fn ($user) => $user->is_vote_recorder))->toBeTrue();
});

test('witnesses scope returns only witnesses', function () {
    User::factory()->count(4)->create(['is_witness' => true]);
    User::factory()->count(2)->create(['is_witness' => false]);

    $witnesses = User::witnesses()->get();

    expect($witnesses)->toHaveCount(4)
        ->and($witnesses->every(fn ($user) => $user->is_witness))->toBeTrue();
});

test('specialCoordinators scope returns only special coordinators', function () {
    User::factory()->count(2)->create(['is_special_coordinator' => true]);
    User::factory()->count(5)->create(['is_special_coordinator' => false]);

    $specialCoordinators = User::specialCoordinators()->get();

    expect($specialCoordinators)->toHaveCount(2)
        ->and($specialCoordinators->every(fn ($user) => $user->is_special_coordinator))->toBeTrue();
});

test('assignedWitnesses scope returns only witnesses with assigned station', function () {
    User::factory()->count(3)->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 001-A',
    ]);
    User::factory()->count(2)->create([
        'is_witness' => true,
        'witness_assigned_station' => null,
    ]);
    User::factory()->count(2)->create([
        'is_witness' => false,
    ]);

    $assignedWitnesses = User::assignedWitnesses()->get();

    expect($assignedWitnesses)->toHaveCount(3)
        ->and($assignedWitnesses->every(fn ($user) => $user->is_witness && $user->witness_assigned_station))->toBeTrue();
});

test('user can update classification flags', function () {
    $user = User::factory()->create([
        'is_vote_recorder' => false,
        'is_witness' => false,
        'is_special_coordinator' => false,
    ]);

    $user->update([
        'is_vote_recorder' => true,
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 456-C',
        'witness_payment_amount' => 125000.00,
    ]);

    expect($user->fresh()->is_vote_recorder)->toBeTrue()
        ->and($user->fresh()->is_witness)->toBeTrue()
        ->and($user->fresh()->witness_assigned_station)->toBe('Mesa 456-C')
        ->and($user->fresh()->witness_payment_amount)->toBe('125000.00');
});

test('witness payment amount is stored as decimal with 2 precision', function () {
    $user = User::factory()->create([
        'is_witness' => true,
        'witness_payment_amount' => 99999.999, // Should be rounded
    ]);

    expect($user->fresh()->witness_payment_amount)->toBe('100000.00');
});

test('witness can be unassigned by setting station to null', function () {
    $user = User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 123-A',
        'witness_payment_amount' => 100000.00,
    ]);

    $user->update([
        'witness_assigned_station' => null,
        'witness_payment_amount' => null,
    ]);

    expect($user->fresh()->witness_assigned_station)->toBeNull()
        ->and($user->fresh()->witness_payment_amount)->toBeNull()
        ->and($user->fresh()->is_witness)->toBeTrue(); // Flag remains true
});

test('multiple classification flags can be queried together', function () {
    // User that is both vote recorder and witness
    User::factory()->count(2)->create([
        'is_vote_recorder' => true,
        'is_witness' => true,
    ]);

    // User that is only vote recorder
    User::factory()->count(3)->create([
        'is_vote_recorder' => true,
        'is_witness' => false,
    ]);

    // User that is only witness
    User::factory()->create([
        'is_vote_recorder' => false,
        'is_witness' => true,
    ]);

    $voteRecorderWitnesses = User::query()
        ->where('is_vote_recorder', true)
        ->where('is_witness', true)
        ->get();

    expect($voteRecorderWitnesses)->toHaveCount(2);
});

test('classification flags can be combined with factory states', function () {
    $user = User::factory()
        ->voteRecorder()
        ->witness('Mesa 777-X', 175000.50)
        ->specialCoordinator()
        ->create();

    expect($user->is_vote_recorder)->toBeTrue()
        ->and($user->is_witness)->toBeTrue()
        ->and($user->witness_assigned_station)->toBe('Mesa 777-X')
        ->and($user->witness_payment_amount)->toBe('175000.50')
        ->and($user->is_special_coordinator)->toBeTrue();
});
