<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\Pages\CreateCoordinator;
use App\Models\NationalIdentityRecord;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($this->admin);
});

test('blurring a cedula in the national identity directory autofills and locks the name field', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1053006255',
        'nombre1' => 'Lanna',
        'nombre2' => 'Javiana',
        'apellido1' => 'Contreras',
        'apellido2' => 'Ortega',
    ]);

    Livewire::test(CreateCoordinator::class)
        ->set('data.document_number', '1053006255')
        ->assertSet('data.name', 'Lanna Javiana Contreras Ortega')
        ->assertSet('data.name_locked', true);
});

test('blurring a cedula not in the directory leaves the name field untouched and unlocked', function () {
    Livewire::test(CreateCoordinator::class)
        ->set('data.document_number', '9999999999')
        ->assertSet('data.name', '')
        ->assertSet('data.name_locked', false);
});

test('the unlock action re-enables editing the name field', function () {
    NationalIdentityRecord::factory()->create(['cedula' => '1053006255']);

    Livewire::test(CreateCoordinator::class)
        ->set('data.document_number', '1053006255')
        ->assertSet('data.name_locked', true)
        ->callFormComponentAction('name', 'unlock_name')
        ->assertSet('data.name_locked', false);
});
