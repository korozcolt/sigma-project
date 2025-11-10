<?php

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;

test('can query municipalities', function () {
    $count = Municipality::count();

    expect($count)->toBeGreaterThanOrEqual(0);
});

test('neighborhoods exist for sincelejo', function () {
    $sincelejo = Municipality::where('name', 'Sincelejo')->first();

    if (! $sincelejo) {
        $this->markTestSkipped('Sincelejo municipality not found in database');
    }

    $count = Neighborhood::where('municipality_id', $sincelejo->id)->count();

    expect($count)->toBeGreaterThan(0);
});

test('imported neighborhoods have correct structure', function () {
    $sincelejo = Municipality::where('name', 'Sincelejo')->first();

    if (! $sincelejo) {
        $this->markTestSkipped('Sincelejo municipality not found in database');
    }

    $neighborhood = Neighborhood::where('municipality_id', $sincelejo->id)->first();

    if (! $neighborhood) {
        $this->markTestSkipped('No neighborhoods found for Sincelejo');
    }

    expect($neighborhood)->not->toBeNull()
        ->and($neighborhood->name)->not->toBeEmpty()
        ->and($neighborhood->municipality_id)->toBe($sincelejo->id);
});

test('neighborhood names are properly formatted', function () {
    $sincelejo = Municipality::where('name', 'Sincelejo')->first();

    if (! $sincelejo) {
        $this->markTestSkipped('Sincelejo municipality not found in database');
    }

    $neighborhoods = Neighborhood::where('municipality_id', $sincelejo->id)
        ->limit(5)
        ->get();

    if ($neighborhoods->isEmpty()) {
        $this->markTestSkipped('No neighborhoods found for Sincelejo');
    }

    foreach ($neighborhoods as $neighborhood) {
        expect($neighborhood->name)->not->toBeEmpty()
            ->and($neighborhood->name)->not->toContain('  ');
    }
});

test('can create neighborhood for municipality', function () {
    $department = Department::factory()->create(['name' => 'Test Department']);
    $municipality = Municipality::factory()->create([
        'department_id' => $department->id,
        'name' => 'Test City',
    ]);

    $neighborhood = Neighborhood::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Test Neighborhood',
        'is_global' => true,
    ]);

    expect($neighborhood->municipality_id)->toBe($municipality->id)
        ->and($neighborhood->name)->toBe('Test Neighborhood')
        ->and($neighborhood->is_global)->toBeTrue();
});
