<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear roles necesarios
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

test('creates voter automatically when user is created with required data', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $municipality->id,
        'name' => 'Juan Pérez',
    ]);

    // Asignar campaña al usuario
    $user->campaigns()->attach($campaign->id);

    // Disparar el observer manualmente (en caso de que no se dispare automáticamente)
    $user->refresh();
    app(\App\Observers\UserObserver::class)->created($user);

    // Verificar que se creó el votante
    expect(Voter::where('user_id', $user->id)->exists())->toBeTrue();

    $voter = Voter::where('user_id', $user->id)->first();
    expect($voter)
        ->not->toBeNull()
        ->document_number->toBe('1234567890')
        ->first_name->toBe('Juan')
        ->last_name->toBe('Pérez')
        ->campaign_id->toBe($campaign->id)
        ->municipality_id->toBe($municipality->id)
        ->registered_by->toBe($user->id)
        ->status->toBe(VoterStatus::CONFIRMED);
});

test('does not create voter if user has no document number', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => null,
        'municipality_id' => $municipality->id,
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    // Verificar que NO se creó el votante
    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('does not create voter if user has no municipality', function () {
    $campaign = Campaign::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => null,
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    // Verificar que NO se creó el votante
    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('does not create voter if user has no campaign assigned', function () {
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $municipality->id,
    ]);

    // NO asignar campaña

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    // Verificar que NO se creó el votante
    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('voter is created with all user data', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '9876543210',
        'name' => 'María García López',
        'birth_date' => '1990-05-15',
        'phone' => '3001234567',
        'secondary_phone' => '3107654321',
        'municipality_id' => $municipality->id,
        'address' => 'Calle 123 #45-67',
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    $voter = Voter::where('user_id', $user->id)->first();

    expect($voter)
        ->not->toBeNull()
        ->document_number->toBe('9876543210')
        ->first_name->toBe('María')
        ->last_name->toBe('García López')
        ->birth_date->format('Y-m-d')->toBe('1990-05-15')
        ->phone->toBe('3001234567')
        ->secondary_phone->toBe('3107654321')
        ->address->toBe('Calle 123 #45-67')
        ->status->toBe(VoterStatus::CONFIRMED);
});

test('voter is created with default phone when user has no phone', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $municipality->id,
        'phone' => null,
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    $voter = Voter::where('user_id', $user->id)->first();

    expect($voter)
        ->not->toBeNull()
        ->phone->toBe('0000000000');
});

test('handles single name correctly', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'name' => 'Madonna',
        'municipality_id' => $municipality->id,
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    $voter = Voter::where('user_id', $user->id)->first();

    expect($voter)
        ->not->toBeNull()
        ->first_name->toBe('Madonna')
        ->last_name->toBe('');
});

test('user voter relationship works correctly', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $municipality->id,
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    $user->refresh();

    // Verificar relación voter()
    expect($user->voter)->not->toBeNull();
    expect($user->voter)->toBeInstanceOf(Voter::class);
    expect($user->voter->user_id)->toBe($user->id);
});

test('voter user relationship works correctly', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $municipality->id,
    ]);

    $user->campaigns()->attach($campaign->id);

    // Disparar observer
    app(\App\Observers\UserObserver::class)->created($user);

    $voter = Voter::where('user_id', $user->id)->first();

    // Verificar relación user()
    expect($voter->user)->not->toBeNull();
    expect($voter->user)->toBeInstanceOf(User::class);
    expect($voter->user->id)->toBe($user->id);
});

test('user can have multiple direct voters registered', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $coordinator = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $municipality->id,
    ]);

    $coordinator->campaigns()->attach($campaign->id);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    // Crear 5 votantes registrados por este coordinador
    $voters = Voter::factory()->count(5)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $coordinator->id,
    ]);

    // Verificar relación directVoters()
    expect($coordinator->directVoters)->toHaveCount(5);
    expect($coordinator->directVoters->first())->toBeInstanceOf(Voter::class);
    expect($coordinator->registeredVoters)->toHaveCount(5);
});
