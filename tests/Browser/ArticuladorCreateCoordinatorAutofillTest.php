<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\NationalIdentityRecord;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->campaign = Campaign::factory()->create(['status' => 'active']);

    $this->areaCoordinator = User::factory()->withoutTwoFactor()->create([
        'email' => 'articulador-autofill@example.com',
        'password' => 'password',
    ]);
    $this->areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinator->campaigns()->attach($this->campaign->id);
});

it('autofills and locks the name field when the cedula matches the national identity directory, and unlocks it on demand', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1053006255',
        'nombre1' => 'Lanna',
        'nombre2' => 'Javiana',
        'apellido1' => 'Contreras',
        'apellido2' => 'Ortega',
    ]);

    loginRealBrowserUser($this->areaCoordinator);

    $page = visit(route('articulador.coordinadores.create'));

    $page->type('document_number', '1053006255');
    $page->keys('document_number', 'Tab');
    $page->assertValue('name', 'Lanna Javiana Contreras Ortega');
    $page->assertDisabled('name');

    $page->click('¿Nombre incorrecto? Editar manualmente');
    $page->assertEnabled('name');
});

it('leaves the name field empty and unlocked when the cedula is not in the national identity directory', function () {
    loginRealBrowserUser($this->areaCoordinator);

    $page = visit(route('articulador.coordinadores.create'));

    $page->type('document_number', '9999999999');
    $page->keys('document_number', 'Tab');
    $page->assertValue('name', '');
    $page->assertEnabled('name');
    $page->assertDontSee('¿Nombre incorrecto? Editar manualmente');
});
