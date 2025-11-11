<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear roles necesarios
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

// ============ Tests para Flags de Clasificación ============

test('user can be marked as vote recorder', function () {
    $user = User::factory()->create([
        'is_vote_recorder' => true,
    ]);

    expect($user->is_vote_recorder)->toBeTrue();
});

test('user can be marked as witness', function () {
    $user = User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 001',
        'witness_payment_amount' => 50000.00,
    ]);

    expect($user->is_witness)->toBeTrue();
    expect($user->witness_assigned_station)->toBe('Mesa 001');
    expect($user->witness_payment_amount)->toBe('50000.00');
});

test('user can be marked as special coordinator', function () {
    $user = User::factory()->create([
        'is_special_coordinator' => true,
    ]);

    expect($user->is_special_coordinator)->toBeTrue();
});

test('user can have multiple classification flags simultaneously', function () {
    $user = User::factory()->create([
        'is_vote_recorder' => true,
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 002',
        'is_special_coordinator' => true,
    ]);

    expect($user->is_vote_recorder)->toBeTrue();
    expect($user->is_witness)->toBeTrue();
    expect($user->is_special_coordinator)->toBeTrue();
});

// ============ Tests para Scopes ============

test('scope voteRecorders returns only vote recorders', function () {
    // Crear usuarios mixtos
    User::factory()->create(['is_vote_recorder' => true]);
    User::factory()->create(['is_vote_recorder' => true]);
    User::factory()->create(['is_vote_recorder' => false]);
    User::factory()->create(['is_witness' => true]);

    $voteRecorders = User::voteRecorders()->get();

    expect($voteRecorders)->toHaveCount(2);
    expect($voteRecorders->every(fn ($user) => $user->is_vote_recorder))->toBeTrue();
});

test('scope witnesses returns only witnesses', function () {
    // Crear usuarios mixtos
    User::factory()->create(['is_witness' => true]);
    User::factory()->create(['is_witness' => true]);
    User::factory()->create(['is_witness' => false]);
    User::factory()->create(['is_vote_recorder' => true]);

    $witnesses = User::witnesses()->get();

    expect($witnesses)->toHaveCount(2);
    expect($witnesses->every(fn ($user) => $user->is_witness))->toBeTrue();
});

test('scope specialCoordinators returns only special coordinators', function () {
    // Crear usuarios mixtos
    User::factory()->create(['is_special_coordinator' => true]);
    User::factory()->create(['is_special_coordinator' => true]);
    User::factory()->create(['is_special_coordinator' => false]);
    User::factory()->create(['is_witness' => true]);

    $specialCoordinators = User::specialCoordinators()->get();

    expect($specialCoordinators)->toHaveCount(2);
    expect($specialCoordinators->every(fn ($user) => $user->is_special_coordinator))->toBeTrue();
});

test('scope assignedWitnesses returns only witnesses with assigned station', function () {
    // Crear testigos con y sin mesa asignada
    User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 001',
    ]);
    User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 002',
    ]);
    User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => null,
    ]);
    User::factory()->create([
        'is_witness' => false,
    ]);

    $assignedWitnesses = User::assignedWitnesses()->get();

    expect($assignedWitnesses)->toHaveCount(2);
    expect($assignedWitnesses->every(fn ($user) => $user->is_witness && $user->witness_assigned_station !== null))->toBeTrue();
});

// ============ Tests para Castings ============

test('witness payment amount is casted to decimal', function () {
    $user = User::factory()->create([
        'witness_payment_amount' => 75000,
    ]);

    expect($user->witness_payment_amount)->toBe('75000.00');
});

test('classification flags are casted to boolean', function () {
    $user = User::factory()->create([
        'is_vote_recorder' => 1,
        'is_witness' => 1,
        'is_special_coordinator' => 0,
    ]);

    expect($user->is_vote_recorder)->toBeTrue();
    expect($user->is_witness)->toBeTrue();
    expect($user->is_special_coordinator)->toBeFalse();
});

// ============ Tests de Integración ============

test('can query users with multiple classification flags', function () {
    // Crear usuarios con diferentes combinaciones
    User::factory()->create([
        'is_vote_recorder' => true,
        'is_witness' => true,
    ]);

    User::factory()->create([
        'is_vote_recorder' => true,
        'is_special_coordinator' => true,
    ]);

    User::factory()->create([
        'is_witness' => true,
    ]);

    // Query usuarios que sean vote recorders Y testigos
    $voteRecorderWitnesses = User::voteRecorders()
        ->witnesses()
        ->get();

    expect($voteRecorderWitnesses)->toHaveCount(1);
});

test('can query assigned witnesses with payment', function () {
    User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => 'Mesa 003',
        'witness_payment_amount' => 80000,
    ]);

    User::factory()->create([
        'is_witness' => true,
        'witness_assigned_station' => null,
        'witness_payment_amount' => 80000,
    ]);

    $assignedWithPayment = User::assignedWitnesses()
        ->whereNotNull('witness_payment_amount')
        ->get();

    expect($assignedWithPayment)->toHaveCount(1);
    expect($assignedWithPayment->first()->witness_payment_amount)->toBe('80000.00');
});
