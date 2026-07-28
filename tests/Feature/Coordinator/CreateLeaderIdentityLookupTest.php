<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\NationalIdentityRecord;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->municipality = Municipality::factory()->create();
    $this->coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->coordinator->campaigns()->attach($this->campaign->id);

    actingAs($this->coordinator);
});

test('blurring a cedula in the national identity directory autofills and locks the name field', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1053006255',
        'nombre1' => 'Lanna',
        'nombre2' => 'Javiana',
        'apellido1' => 'Contreras',
        'apellido2' => 'Ortega',
    ]);

    Volt::test('coordinator.create-leader')
        ->set('document_number', '1053006255')
        ->assertSet('name', 'Lanna Javiana Contreras Ortega')
        ->assertSet('nameLocked', true);
});

test('blurring a cedula not in the directory leaves the name field empty and unlocked', function () {
    Volt::test('coordinator.create-leader')
        ->set('document_number', '9999999999')
        ->assertSet('name', '')
        ->assertSet('nameLocked', false);
});

test('unlockName re-enables editing after an autofilled match', function () {
    NationalIdentityRecord::factory()->create(['cedula' => '1053006255']);

    Volt::test('coordinator.create-leader')
        ->set('document_number', '1053006255')
        ->assertSet('nameLocked', true)
        ->call('unlockName')
        ->assertSet('nameLocked', false);
});
