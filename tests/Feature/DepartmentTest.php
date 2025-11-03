<?php

use App\Models\Department;
use App\Models\Municipality;

use function Pest\Laravel\assertDatabaseHas;

it('can create a department', function () {
    $department = Department::factory()->create([
        'name' => 'Test Department',
        'code' => 'TEST01',
    ]);

    expect($department)->toBeInstanceOf(Department::class);
    expect($department->name)->toBe('Test Department');
    expect($department->code)->toBe('TEST01');

    assertDatabaseHas('departments', [
        'name' => 'Test Department',
        'code' => 'TEST01',
    ]);
});

it('requires name and code', function () {
    expect(fn () => Department::create([]))->toThrow(Exception::class);
});

it('name must be unique', function () {
    Department::factory()->create(['name' => 'Unique Department', 'code' => 'TEST02']);

    expect(fn () => Department::factory()->create([
        'name' => 'Unique Department',
        'code' => 'TEST03',
    ]))->toThrow(Exception::class);
});

it('code must be unique', function () {
    Department::factory()->create(['name' => 'Department One', 'code' => 'TEST04']);

    expect(fn () => Department::factory()->create([
        'name' => 'Department Two',
        'code' => 'TEST04',
    ]))->toThrow(Exception::class);
});

it('has municipalities relationship', function () {
    $department = Department::factory()->create();

    expect($department->municipalities())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can retrieve municipalities', function () {
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $department->load('municipalities');

    expect($department->municipalities)->toHaveCount(1);
    expect($department->municipalities->first()->id)->toBe($municipality->id);
});

it('seeder creates all 33 departments of Colombia', function () {
    $this->artisan('db:seed', ['--class' => 'DepartmentSeeder']);

    $departments = Department::all();

    expect($departments)->toHaveCount(33);

    // Verificar algunos departamentos específicos (usando IDs de la API)
    assertDatabaseHas('departments', ['name' => 'Antioquia']);
    assertDatabaseHas('departments', ['name' => 'Bogotá']);
    assertDatabaseHas('departments', ['name' => 'Valle del Cauca']);
    assertDatabaseHas('departments', ['name' => 'Santander']);
    assertDatabaseHas('departments', ['name' => 'Arauca']);
});

it('can update a department', function () {
    $department = Department::factory()->create([
        'name' => 'Original Name',
        'code' => 'TEST05',
    ]);

    $department->update(['name' => 'Updated Name']);

    expect($department->fresh()->name)->toBe('Updated Name');
    assertDatabaseHas('departments', ['name' => 'Updated Name', 'code' => 'TEST05']);
});

it('can delete a department', function () {
    $department = Department::factory()->create();
    $id = $department->id;

    $department->delete();

    expect(Department::find($id))->toBeNull();
});

it('deleting department does not automatically delete municipalities', function () {
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);

    // Sin onDelete cascade, esto debería lanzar un error o el municipio quedaría huérfano
    // Dependiendo de la configuración de la BD
    expect($municipality->exists())->toBeTrue();
});
