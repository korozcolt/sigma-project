<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\Pages\CreateAreaCoordinator;
use App\Filament\Resources\Coordinators\Pages\CreateCoordinator;
use App\Filament\Resources\Leaders\Pages\CreateLeader;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Services\CampaignContext;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);
    $this->admin->forceFill([
        'municipality_id' => null,
        'neighborhood_id' => null,
    ])->save();

    actingAs($this->admin);

    $this->campaign = Campaign::factory()->create(['created_by' => $this->admin->id]);
    $this->municipality = Municipality::factory()->create();

    CampaignContext::setCampaignId(null);
});

afterEach(function () {
    CampaignContext::setCampaignId(null);
});

// ============ Leader ============

test('creating a leader with only document_number and no email succeeds', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    Livewire::test(CreateLeader::class)
        ->fillForm([
            'coordinator_user_id' => $coordinator->id,
            'name' => 'Lider Solo Cedula',
            'email' => '',
            'document_number' => '900300400',
            'phone' => '3001234567',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $leader = User::where('document_number', '900300400')->first();

    expect($leader)->not->toBeNull();
    expect($leader->email)->toBeNull();
});

test('creating a leader with only email and no document_number succeeds', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    Livewire::test(CreateLeader::class)
        ->fillForm([
            'coordinator_user_id' => $coordinator->id,
            'name' => 'Lider Solo Correo',
            'email' => 'lider-solo-correo@example.com',
            'document_number' => '',
            'phone' => '3001234567',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $leader = User::where('email', 'lider-solo-correo@example.com')->first();

    expect($leader)->not->toBeNull();
});

test('creating a leader without email nor document_number fails validation on both fields', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    Livewire::test(CreateLeader::class)
        ->fillForm([
            'coordinator_user_id' => $coordinator->id,
            'name' => 'Lider Sin Nada',
            'email' => '',
            'document_number' => '',
            'phone' => '3001234567',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email', 'document_number']);
});

// ============ Coordinator ============

test('creating a coordinator with only document_number and no email succeeds', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateCoordinator::class)
        ->fillForm([
            'name' => 'Coordinador Solo Cedula',
            'email' => '',
            'document_number' => '900400500',
            'phone' => '3001234567',
            'password' => 'password123',
            'municipality_id' => $this->municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::where('document_number', '900400500')->first();

    expect($coordinator)->not->toBeNull();
    expect($coordinator->email)->toBeNull();
});

test('creating a coordinator with only email and no document_number succeeds', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateCoordinator::class)
        ->fillForm([
            'name' => 'Coordinador Solo Correo',
            'email' => 'coordinador-solo-correo@example.com',
            'document_number' => '',
            'phone' => '3001234567',
            'password' => 'password123',
            'municipality_id' => $this->municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::where('email', 'coordinador-solo-correo@example.com')->first();

    expect($coordinator)->not->toBeNull();
});

test('creating a coordinator without email nor document_number fails validation on both fields', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateCoordinator::class)
        ->fillForm([
            'name' => 'Coordinador Sin Nada',
            'email' => '',
            'document_number' => '',
            'phone' => '3001234567',
            'password' => 'password123',
            'municipality_id' => $this->municipality->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['email', 'document_number']);
});

// ============ AreaCoordinator ============

test('creating an area coordinator with only document_number and no email succeeds', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm([
            'name' => 'Articulador Solo Cedula',
            'email' => '',
            'document_number' => '900500600',
            'phone' => '3009876543',
            'password' => 'password123',
            'municipality_id' => $this->municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $areaCoordinator = User::where('document_number', '900500600')->first();

    expect($areaCoordinator)->not->toBeNull();
    expect($areaCoordinator->email)->toBeNull();
});

test('creating an area coordinator with only email and no document_number succeeds', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm([
            'name' => 'Articulador Solo Correo',
            'email' => 'articulador-solo-correo@example.com',
            'document_number' => '',
            'phone' => '3009876543',
            'password' => 'password123',
            'municipality_id' => $this->municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $areaCoordinator = User::where('email', 'articulador-solo-correo@example.com')->first();

    expect($areaCoordinator)->not->toBeNull();
});

test('creating an area coordinator without email nor document_number fails validation on both fields', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm([
            'name' => 'Articulador Sin Nada',
            'email' => '',
            'document_number' => '',
            'phone' => '3009876543',
            'password' => 'password123',
            'municipality_id' => $this->municipality->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['email', 'document_number']);
});

// ============ User (admin) ============

test('creating a user with only document_number and no email succeeds', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Usuario Solo Cedula',
            'email' => '',
            'document_number' => '900600700',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'campaignAssignments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('document_number', '900600700')->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBeNull();
});

test('creating a user with only email and no document_number succeeds', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Usuario Solo Correo',
            'email' => 'usuario-solo-correo@example.com',
            'document_number' => '',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'campaignAssignments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'usuario-solo-correo@example.com')->first();

    expect($user)->not->toBeNull();
});

test('creating a user without email nor document_number fails validation on both fields', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Usuario Sin Nada',
            'email' => '',
            'document_number' => '',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'campaignAssignments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['email', 'document_number']);
});
