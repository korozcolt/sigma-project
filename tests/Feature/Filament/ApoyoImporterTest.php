<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Imports\ApoyoImporter;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

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
    $campaign = Campaign::factory()->create();
    $admin->campaigns()->attach($campaign, ['assigned_at' => now()]);
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    expect(ApoyoImporter::getColumns())->not->toBeEmpty();

    $csv = UploadedFile::fake()->createWithContent('apoyos.csv', implode("\n", [
        'cedula,nombre1,apellido1,fecha_nacimiento,telefono,barrio,direccion,lugar_expedicion_cedula,subcategoria,gremio,placa',
        '1010101010,Ana,Torres,,3001112222,'.$neighborhood->name.',,,,,',
        ',SinCedula,Torres,,3003334444,'.$neighborhood->name.',,,,,',
    ]));

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->callAction('import', data: [
            'file' => $csv,
            'municipality_id' => $municipality->id,
        ]);

    expect(Voter::where('document_number', '1010101010')->exists())->toBeTrue()
        ->and(Voter::where('first_name', 'SinCedula')->exists())->toBeFalse();

    $import = Import::query()->latest('id')->first();

    expect($import)->not->toBeNull()
        ->and($import->total_rows)->toBe(2)
        ->and($import->getFailedRowsCount())->toBe(1)
        ->and($import->failedRows()->count())->toBe(1);
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
        ->callAction('import', data: [
            'file' => $csv,
            'municipality_id' => $municipality->id,
        ]);

    expect(Voter::where('document_number', '2020202020')->exists())->toBeFalse();

    $import = Import::query()->latest('id')->first();
    $failedRow = $import->failedRows()->first();

    expect($import->getFailedRowsCount())->toBe(1)
        ->and($failedRow)->not->toBeNull()
        ->and($failedRow->validation_error)->toContain('líder/coordinador');
});

test('importing a CSV row missing a required column is rejected and appears in the failed-rows report', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    $csv = UploadedFile::fake()->createWithContent('apoyos.csv', implode("\n", [
        'cedula,nombre1,apellido1,fecha_nacimiento,telefono,barrio,direccion,lugar_expedicion_cedula,subcategoria,gremio,placa',
        '3030303030,Sin,Telefono,,,'.$neighborhood->name.',,,,,',
    ]));

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->callAction('import', data: [
            'file' => $csv,
            'municipality_id' => $municipality->id,
        ]);

    expect(Voter::where('document_number', '3030303030')->exists())->toBeFalse();

    $import = Import::query()->latest('id')->first();

    expect($import->getFailedRowsCount())->toBe(1);
});

test('a row with a valid, never-before-seen cedula creates a new Voter with duplicate_sequence 0', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $campaign = Campaign::factory()->create();
    $admin->campaigns()->attach($campaign, ['assigned_at' => now()]);

    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    $csv = UploadedFile::fake()->createWithContent('apoyos.csv', implode("\n", [
        'cedula,nombre1,apellido1,fecha_nacimiento,telefono,barrio,direccion,lugar_expedicion_cedula,subcategoria,gremio,placa',
        '4040404040,Nueva,Persona,,3007778888,'.$neighborhood->name.',,,,,',
    ]));

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->callAction('import', data: [
            'file' => $csv,
            'municipality_id' => $municipality->id,
        ]);

    $voter = Voter::where('document_number', '4040404040')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->duplicate_sequence)->toBe(0)
        ->and($voter->municipality_id)->toBe($municipality->id)
        ->and($voter->registered_by)->toBe($admin->id);
});

test('a row whose cedula already exists creates a duplicate Voter with incremented sequence and DUPLICATE status', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $campaign = Campaign::factory()->create();
    $admin->campaigns()->attach($campaign, ['assigned_at' => now()]);

    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    Voter::factory()->create([
        'document_number' => '5050505050',
        'municipality_id' => $municipality->id,
    ]);

    $csv = UploadedFile::fake()->createWithContent('apoyos.csv', implode("\n", [
        'cedula,nombre1,apellido1,fecha_nacimiento,telefono,barrio,direccion,lugar_expedicion_cedula,subcategoria,gremio,placa',
        '5050505050,Duplicado,Apoyo,,3009990000,'.$neighborhood->name.',,,,,',
    ]));

    actingAs($admin);

    Livewire::test(ListVoters::class)
        ->callAction('import', data: [
            'file' => $csv,
            'municipality_id' => $municipality->id,
        ]);

    $duplicateVoter = Voter::where('document_number', '5050505050')
        ->where('duplicate_sequence', '>', 0)
        ->first();

    expect($duplicateVoter)->not->toBeNull()
        ->and($duplicateVoter->status->value)->toBe('duplicate');
});
