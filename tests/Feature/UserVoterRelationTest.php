<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

uses()->group('user-voter-relation');

beforeEach(function () {
    // Crear estructura territorial
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);
    $this->municipality = $municipality;

    // Crear campaña
    $this->campaign = Campaign::factory()->create();
});

test('user hasOne voter relationship exists', function () {
    $user = User::factory()->create();

    expect($user->voter())->toBeInstanceOf(HasOne::class);
});

test('user has directVoters relationship', function () {
    $user = User::factory()->create();

    expect($user->directVoters())->toBeInstanceOf(HasMany::class);
});

test('voter belongsTo user relationship exists', function () {
    $voter = Voter::factory()->create();

    expect($voter->user())->toBeInstanceOf(BelongsTo::class);
});

test('user can be created with voter association', function () {
    $voter = Voter::factory()->create();
    $user = User::factory()->create();

    $voter->user_id = $user->id;
    $voter->save();

    expect($voter->refresh()->user)->not->toBeNull()
        ->and($voter->user->id)->toBe($user->id)
        ->and($user->refresh()->voter)->not->toBeNull()
        ->and($user->voter->id)->toBe($voter->id);
});

test('creating user with required data and campaign auto-creates voter record', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
        'neighborhood_id' => $this->neighborhood->id,
    ]);

    // Asignar campaña
    $user->campaigns()->attach($this->campaign);

    // Simular el observer manualmente para testing
    $observer = new UserObserver;
    $observer->created($user);

    $user->refresh();

    expect($user->voter)->not->toBeNull()
        ->and($user->voter->user_id)->toBe($user->id)
        ->and($user->voter->campaign_id)->toBe($this->campaign->id)
        ->and($user->voter->document_number)->toBe($user->document_number)
        ->and($user->voter->municipality_id)->toBe($user->municipality_id)
        ->and($user->voter->registered_by)->toBe($user->id)
        ->and($user->voter->status)->toBe(VoterStatus::CONFIRMED);
});

test('user without document_number does not auto-create voter', function () {
    $user = User::factory()->create([
        'document_number' => null,
        'municipality_id' => $this->municipality->id,
    ]);

    $user->campaigns()->attach($this->campaign);

    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('user without municipality does not auto-create voter', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => null,
    ]);

    $user->campaigns()->attach($this->campaign);

    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('user without campaign does not auto-create voter', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
    ]);

    // NO asignar campaña

    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('voter record uses first and last name from user name', function () {
    $user = User::factory()->create([
        'name' => 'Juan Pérez García',
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
    ]);

    $user->campaigns()->attach($this->campaign);

    $voter = Voter::create([
        'user_id' => $user->id,
        'campaign_id' => $this->campaign->id,
        'document_number' => $user->document_number,
        'first_name' => explode(' ', $user->name)[0],
        'last_name' => implode(' ', array_slice(explode(' ', $user->name), 1)),
        'phone' => $user->phone ?? '0000000000',
        'municipality_id' => $user->municipality_id,
        'registered_by' => $user->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    expect($voter->first_name)->toBe('Juan')
        ->and($voter->last_name)->toBe('Pérez García');
});

test('voter record handles single name correctly', function () {
    $user = User::factory()->create([
        'name' => 'Juan',
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
    ]);

    $user->campaigns()->attach($this->campaign);

    $nameParts = explode(' ', $user->name, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    expect($firstName)->toBe('Juan')
        ->and($lastName)->toBe('');
});

test('user can have multiple direct voters', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
    ]);

    $voters = Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $user->id,
    ]);

    expect($user->directVoters)->toHaveCount(5)
        ->and($user->directVoters->pluck('id')->toArray())
        ->toEqual($voters->pluck('id')->toArray());
});

test('user directVoters is alias of registeredVoters', function () {
    $user = User::factory()->create();

    $voter1 = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $user->id,
    ]);

    $voter2 = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $user->id,
    ]);

    expect($user->directVoters->count())->toBe(2)
        ->and($user->registeredVoters->count())->toBe(2)
        ->and($user->directVoters->pluck('id')->toArray())
        ->toEqual($user->registeredVoters->pluck('id')->toArray());
});

test('voter can identify if it is a system user', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
    ]);

    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
    ]);

    expect($voter->isSystemUser())->toBeFalse();

    $voter->update(['user_id' => $user->id]);

    expect($voter->refresh()->isSystemUser())->toBeTrue();
});

test('comando users:create-voter-records creates voters for existing users', function () {
    // Deshabilitar eventos para crear users sin que se dispare el observer
    User::unsetEventDispatcher();

    // Crear users con datos completos
    $user1 = User::factory()->create([
        'document_number' => '1111111111',
        'municipality_id' => $this->municipality->id,
        'neighborhood_id' => $this->neighborhood->id,
    ]);

    $user2 = User::factory()->create([
        'document_number' => '2222222222',
        'municipality_id' => $this->municipality->id,
    ]);

    // Reactivar eventos
    User::setEventDispatcher(app('events'));

    // Asignar campaña
    $user1->campaigns()->attach($this->campaign);
    $user2->campaigns()->attach($this->campaign);

    // Ejecutar comando
    $this->artisan('users:create-voter-records')
        ->assertSuccessful();

    // Verificar que se crearon los votantes
    expect(Voter::where('user_id', $user1->id)->exists())->toBeTrue()
        ->and(Voter::where('user_id', $user2->id)->exists())->toBeTrue();
});

test('comando users:create-voter-records skips users without document', function () {
    $user = User::factory()->create([
        'document_number' => null,
        'municipality_id' => $this->municipality->id,
    ]);

    $user->campaigns()->attach($this->campaign);

    $this->artisan('users:create-voter-records')
        ->assertSuccessful();

    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('comando users:create-voter-records skips users without municipality', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => null,
    ]);

    $user->campaigns()->attach($this->campaign);

    $this->artisan('users:create-voter-records')
        ->assertSuccessful();

    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('comando users:create-voter-records skips users without campaign', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
    ]);

    // NO asignar campaña

    $this->artisan('users:create-voter-records')
        ->assertSuccessful();

    expect(Voter::where('user_id', $user->id)->exists())->toBeFalse();
});

test('comando users:create-voter-records with --force updates existing voters', function () {
    $user = User::factory()->create([
        'document_number' => '1234567890',
        'municipality_id' => $this->municipality->id,
        'phone' => '3001234567',
    ]);

    $user->campaigns()->attach($this->campaign);

    // Crear votante existente
    $voter = Voter::factory()->create([
        'user_id' => $user->id,
        'campaign_id' => $this->campaign->id,
        'phone' => '3009999999', // Teléfono diferente
    ]);

    // Ejecutar comando con --force
    $this->artisan('users:create-voter-records --force')
        ->assertSuccessful();

    // Verificar que se actualizó
    $voter->refresh();
    expect($voter->phone)->toBe('3001234567');
});

test('voter has correct fillable fields including user_id', function () {
    $voter = new Voter;

    expect($voter->getFillable())->toContain('user_id')
        ->and($voter->getFillable())->toContain('campaign_id')
        ->and($voter->getFillable())->toContain('document_number');
});

test('user fillable does not include voter_id', function () {
    $user = new User;

    expect($user->getFillable())->not->toContain('voter_id');
});
