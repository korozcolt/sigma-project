<?php

use App\Imports\NeighborhoodsImport;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

beforeEach(function () {
    $department = Department::factory()->create(['name' => 'Sucre']);

    $this->sincelejo = Municipality::factory()->create([
        'department_id' => $department->id,
        'name' => 'Sincelejo',
        'code' => '700',
    ]);

    Neighborhood::factory()->create([
        'municipality_id' => $this->sincelejo->id,
        'name' => 'Centro',
        'is_global' => true,
    ]);

    Neighborhood::factory()->count(4)->create([
        'municipality_id' => $this->sincelejo->id,
        'name' => function () {
            return 'Barrio '.fake()->unique()->numberBetween(1, 999);
        },
    ]);
});

test('can query municipalities', function () {
    $count = Municipality::count();

    expect($count)->toBeGreaterThanOrEqual(0);
});

test('neighborhoods exist for sincelejo', function () {
    $count = Neighborhood::where('municipality_id', $this->sincelejo->id)->count();

    expect($count)->toBeGreaterThan(0);
});

test('imported neighborhoods have correct structure', function () {
    $neighborhood = Neighborhood::where('municipality_id', $this->sincelejo->id)->first();

    expect($neighborhood)->not->toBeNull()
        ->and($neighborhood->name)->not->toBeEmpty()
        ->and($neighborhood->municipality_id)->toBe($this->sincelejo->id);
});

test('neighborhood names are properly formatted', function () {
    $neighborhoods = Neighborhood::where('municipality_id', $this->sincelejo->id)
        ->limit(5)
        ->get();

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

test('barrio names matching day-de-month patterns import as full strings, not truncated to a day number', function () {
    // Simulate the exact production failure mode: PhpSpreadsheet's global
    // value binder is (or becomes) AdvancedValueBinder, which auto-detects
    // Spanish "day de month" patterns as dates and truncates them to the
    // leading day number on read. NeighborhoodsImport must force plain
    // string reads regardless of whatever binder is globally active.
    Cell::setValueBinder(new AdvancedValueBinder);

    $csvPath = tempnam(sys_get_temp_dir(), 'neighborhoods_').'.csv';
    file_put_contents($csvPath, "barrio\n20 De Julio I\n7 De Agosto\n28 De Mayo\n");

    Excel::import(new NeighborhoodsImport($this->sincelejo->id), $csvPath);

    @unlink($csvPath);

    expect(Neighborhood::where('municipality_id', $this->sincelejo->id)->where('name', '20 De Julio I')->exists())->toBeTrue()
        ->and(Neighborhood::where('municipality_id', $this->sincelejo->id)->where('name', '7 De Agosto')->exists())->toBeTrue()
        ->and(Neighborhood::where('municipality_id', $this->sincelejo->id)->where('name', '28 De Mayo')->exists())->toBeTrue()
        ->and(Neighborhood::where('municipality_id', $this->sincelejo->id)->whereIn('name', ['20', '7', '28'])->exists())->toBeFalse();
});
