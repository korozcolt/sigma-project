<?php

use App\Models\PollingPlace;
use App\Models\Municipality;
use App\Rules\MaxTablesForPollingPlace;

test('valida que la mesa no supere el máximo del puesto', function () {
    $municipality = Municipality::factory()->create();

    $pollingPlace = PollingPlace::query()->create([
        'department_id' => $municipality->department_id,
        'municipality_id' => $municipality->id,
        'dane_department_code' => 70,
        'dane_municipality_code' => 1,
        'zone_code' => 1,
        'place_code' => 1,
        'name' => 'Puesto de prueba',
        'max_tables' => 10,
    ]);

    $rule = new MaxTablesForPollingPlace($pollingPlace->id);

    $error = null;
    $rule->validate('polling_table_number', 11, function (string $message) use (&$error) {
        $error = $message;
    });

    expect($error)->toBe('El número de mesa no puede ser mayor a 10.');
});

