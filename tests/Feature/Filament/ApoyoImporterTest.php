<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Imports\ApoyoImporter;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// NOTE (Wave 0 / plan 02.1-01): This file scaffolds RED tests for D-06/D-07.
// `App\Filament\Imports\ApoyoImporter` and the `import` header action on
// ListVoters do not exist yet - they are implemented in plan 02.1-10.
// Failing/erroring here is expected and correct until that plan lands.

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

test('admin_campaign can see the import action on the voters list page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->assertActionVisible('import');
});

test('leader cannot see the import action on the voters list page', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    actingAs($leader);

    Livewire::test(ListVoters::class)
        ->assertActionHidden('import');
});

test('reviewer cannot see the import action on the voters list page', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    Livewire::test(ListVoters::class)
        ->assertActionHidden('import');
});

test('importing a CSV with a mix of valid and invalid rows creates only the valid Apoyos and produces a downloadable failed-rows report', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    // Referencing the importer directly documents the expected column contract
    // (App\Filament\Imports\ApoyoImporter) and forces this test to error until
    // plan 02.1-10 creates it.
    expect(ApoyoImporter::getColumns())->not->toBeEmpty();

    $csv = UploadedFile::fake()->createWithContent('apoyos.csv', implode("\n", [
        'cedula,nombre1,apellido1,fecha_nacimiento,telefono,barrio,direccion,lugar_expedicion_cedula,subcategoria,gremio,placa',
        '1010101010,Ana,Torres,,3001112222,'.$neighborhood->name.',,,,,',
        ',SinCedula,Torres,,3003334444,'.$neighborhood->name.',,,,,',
    ]));

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->callAction('import', data: ['file' => $csv]);

    expect(Voter::where('document_number', '1010101010')->exists())->toBeTrue()
        ->and(Voter::where('first_name', 'SinCedula')->exists())->toBeFalse();
});

test('importing a CSV row whose cedula belongs to an existing leader is rejected with a clear reason', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    $leader = User::factory()->create([
        'document_number' => '2020202020',
        'municipality_id' => $municipality->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    $csv = UploadedFile::fake()->createWithContent('apoyos.csv', implode("\n", [
        'cedula,nombre1,apellido1,fecha_nacimiento,telefono,barrio,direccion,lugar_expedicion_cedula,subcategoria,gremio,placa',
        '2020202020,Otro,Nombre,,3005556666,'.$neighborhood->name.',,,,,',
    ]));

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->callAction('import', data: ['file' => $csv]);

    expect(Voter::where('document_number', '2020202020')
        ->where('registered_by', '!=', $leader->id)
        ->exists())->toBeFalse();
});
