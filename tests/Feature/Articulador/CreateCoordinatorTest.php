<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\NationalIdentityRecord;
use App\Models\Neighborhood;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->municipality = Municipality::factory()->create();
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinator->campaigns()->attach($this->campaign->id);

    actingAs($this->areaCoordinator);
});

function validCoordinatorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lanna Contreras',
        'email' => 'lanna.contreras@example.com',
        'password' => 'password123',
        'document_number' => '1053006255',
        'phone' => '3001234567',
    ], $overrides);
}

test('submitting valid data as an articulador creates a coordinador linked to the articulador', function () {
    Volt::test('articulador.create-coordinator')
        ->set('municipality_id', $this->municipality->id)
        ->set('name', 'Lanna Contreras')
        ->set('email', 'lanna.contreras@example.com')
        ->set('password', 'password123')
        ->set('document_number', '1053006255')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasNoErrors();

    $coordinator = User::where('email', 'lanna.contreras@example.com')->first();

    expect($coordinator)->not->toBeNull();
    expect($coordinator->area_coordinator_user_id)->toBe($this->areaCoordinator->id);
    expect($coordinator->hasRole(UserRole::COORDINATOR->value))->toBeTrue();
});

test('the created coordinador is attached to the same campaigns as the acting articulador', function () {
    Volt::test('articulador.create-coordinator')
        ->set('municipality_id', $this->municipality->id)
        ->set('name', 'Lanna Contreras')
        ->set('email', 'lanna.contreras@example.com')
        ->set('password', 'password123')
        ->set('document_number', '1053006255')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasNoErrors();

    $coordinator = User::where('email', 'lanna.contreras@example.com')->first();

    expect($coordinator->campaigns()->pluck('campaigns.id')->sort()->values()->toArray())
        ->toBe($this->areaCoordinator->campaigns()->pluck('campaigns.id')->sort()->values()->toArray());
});

test('blurring a cedula in the national identity directory autofills and locks the name field', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1053006255',
        'nombre1' => 'Lanna',
        'nombre2' => 'Javiana',
        'apellido1' => 'Contreras',
        'apellido2' => 'Ortega',
    ]);

    Volt::test('articulador.create-coordinator')
        ->set('document_number', '1053006255')
        ->assertSet('name', 'Lanna Javiana Contreras Ortega')
        ->assertSet('nameLocked', true);
});

test('blurring a cedula not in the directory leaves the name field empty and unlocked', function () {
    Volt::test('articulador.create-coordinator')
        ->set('document_number', '9999999999')
        ->assertSet('name', '')
        ->assertSet('nameLocked', false);
});

test('unlockName re-enables editing after an autofilled match', function () {
    NationalIdentityRecord::factory()->create(['cedula' => '1053006255']);

    Volt::test('articulador.create-coordinator')
        ->set('document_number', '1053006255')
        ->assertSet('nameLocked', true)
        ->call('unlockName')
        ->assertSet('nameLocked', false);
});

test('the rendered form does not contain OTP verification elements', function () {
    Volt::test('articulador.create-coordinator')
        ->assertDontSee('Enviar código')
        ->assertDontSee('Verificación de Teléfono');
});

test('the rendered form does not contain an articulador field', function () {
    Volt::test('articulador.create-coordinator')
        ->assertDontSee('Articulador')
        ->assertDontSeeHtml('name="area_coordinator_user_id"');
});

test('submitting with a duplicate email fails validation', function () {
    User::factory()->create(['email' => 'lanna.contreras@example.com']);

    Volt::test('articulador.create-coordinator')
        ->set('municipality_id', $this->municipality->id)
        ->set('name', 'Lanna Contreras')
        ->set('email', 'lanna.contreras@example.com')
        ->set('password', 'password123')
        ->set('document_number', '1053006255')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasErrors(['email']);
});

test('submitting with a duplicate document number fails validation', function () {
    User::factory()->create(['document_number' => '1053006255']);

    Volt::test('articulador.create-coordinator')
        ->set('municipality_id', $this->municipality->id)
        ->set('name', 'Lanna Contreras')
        ->set('email', 'lanna.contreras@example.com')
        ->set('password', 'password123')
        ->set('document_number', '1053006255')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasErrors(['document_number']);
});

test('the newly created coordinador has role coordinator and not role area_coordinator', function () {
    Volt::test('articulador.create-coordinator')
        ->set('municipality_id', $this->municipality->id)
        ->set('name', 'Lanna Contreras')
        ->set('email', 'lanna.contreras@example.com')
        ->set('password', 'password123')
        ->set('document_number', '1053006255')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasNoErrors();

    $coordinator = User::where('email', 'lanna.contreras@example.com')->first();

    expect($coordinator->hasRole(UserRole::COORDINATOR->value))->toBeTrue();
    expect($coordinator->hasRole(UserRole::AREA_COORDINATOR->value))->toBeFalse();
    expect($coordinator->hasRole(UserRole::LEADER->value))->toBeFalse();
});

test('an admin_campaign actor creates a coordinador with a null area_coordinator_user_id', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $admin->campaigns()->attach($this->campaign->id);

    actingAs($admin);

    Volt::test('articulador.create-coordinator')
        ->set('municipality_id', $this->municipality->id)
        ->set('name', 'Lanna Contreras')
        ->set('email', 'lanna.contreras@example.com')
        ->set('password', 'password123')
        ->set('document_number', '1053006255')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasNoErrors();

    $coordinator = User::where('email', 'lanna.contreras@example.com')->first();

    expect($coordinator->area_coordinator_user_id)->toBeNull();
    expect($coordinator->campaigns()->pluck('campaigns.id')->sort()->values()->toArray())
        ->toBe($admin->campaigns()->pluck('campaigns.id')->sort()->values()->toArray());
});
