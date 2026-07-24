<?php

use App\Models\Department;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/census/national-sample.csv');

    $this->department = Department::factory()->create([
        'name' => 'SUCRE',
        'code' => '28',
    ]);

    $this->municipality = Municipality::factory()->create([
        'department_id' => $this->department->id,
        'name' => 'SINCELEJO',
        'code' => '001',
    ]);

    $this->pollingPlace = PollingPlace::factory()->create([
        'department_id' => $this->department->id,
        'municipality_id' => $this->municipality->id,
        'dane_department_code' => 28,
        'dane_municipality_code' => 1,
        'zone_code' => 1,
        'place_code' => 1,
        'address' => 'CALLE FALSA 123',
    ]);
});

test('imports every CSV row keyed uniquely on document_number', function () {
    $this->artisan('census:import-national', ['--path' => $this->fixture])->assertSuccessful();

    expect(NationalCensusRecord::count())->toBe(3)
        ->and(NationalCensusRecord::where('document_number', '1000000001')->exists())->toBeTrue();
});

test('enriches a matched row with polling place, municipality, and department names via the FK', function () {
    $this->artisan('census:import-national', ['--path' => $this->fixture])->assertSuccessful();

    $record = NationalCensusRecord::where('document_number', '1000000001')->first();

    expect($record->pollingPlace->address)->toBe('CALLE FALSA 123')
        ->and($record->pollingPlace->municipality->name)->toBe('SINCELEJO')
        ->and($record->pollingPlace->department->name)->toBe('SUCRE');
});

test('imports accented Latin-1 names without UTF-8 corruption', function () {
    $this->artisan('census:import-national', ['--path' => $this->fixture])->assertSuccessful();

    $record = NationalCensusRecord::where('document_number', '1000000002')->first();

    expect($record->polling_station_name)->toBe('LA PEÑATA');
});

test('national_census_records has no campaign_id column', function () {
    expect(Schema::hasColumn('national_census_records', 'campaign_id'))->toBeFalse();
});

test('reports the unmatched divipol percentage without aborting', function () {
    $this->artisan('census:import-national', ['--path' => $this->fixture])
        ->expectsOutputToContain('%')
        ->assertSuccessful();

    $record = NationalCensusRecord::where('document_number', '1000000003')->first();

    expect($record)->not->toBeNull()
        ->and($record->polling_place_id)->toBeNull()
        ->and($record->polling_station_name)->toBe('PUESTO FANTASMA');
});

test('last row wins for a duplicate document_number within a run', function () {
    $this->artisan('census:import-national', ['--path' => $this->fixture])->assertSuccessful();

    $record = NationalCensusRecord::where('document_number', '1000000001')->first();

    expect($record->table_number)->toBe('99');
});

test('re-running the import is idempotent with no duplicate document_number rows', function () {
    $this->artisan('census:import-national', ['--path' => $this->fixture])->assertSuccessful();
    $this->artisan('census:import-national', ['--path' => $this->fixture])->assertSuccessful();

    expect(NationalCensusRecord::count())->toBe(3);
});
