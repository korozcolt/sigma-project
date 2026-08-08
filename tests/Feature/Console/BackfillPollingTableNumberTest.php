<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
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
        'name' => 'IE LA CAMPIÑA',
        'address' => 'CALLE FALSA 123',
    ]);
});

test('backfills polling_table_number from the most recent polling_place_resolutions history row', function () {
    $voter = Voter::factory()->create([
        'polling_place_id' => $this->pollingPlace->id,
        'polling_table_number' => null,
    ]);

    PollingPlaceResolution::factory()->create([
        'voter_id' => $voter->id,
        'polling_place_id' => $this->pollingPlace->id,
        'table_number' => '3',
        'created_at' => now()->subDays(2),
    ]);

    PollingPlaceResolution::factory()->create([
        'voter_id' => $voter->id,
        'polling_place_id' => $this->pollingPlace->id,
        'table_number' => '8',
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('census:backfill-polling-table-number')->assertSuccessful();

    expect($voter->fresh()->polling_table_number)->toBe(8);
});

test('applies the single-mesa default when no history exists but the linked PollingPlace has max_tables===1', function () {
    $this->pollingPlace->update(['max_tables' => 1]);

    $voter = Voter::factory()->create([
        'polling_place_id' => $this->pollingPlace->id,
        'polling_table_number' => null,
    ]);

    $this->artisan('census:backfill-polling-table-number')->assertSuccessful();

    expect($voter->fresh()->polling_table_number)->toBe(1);
});

test('skips a voter with no history and a PollingPlace with max_tables greater than 1', function () {
    $this->pollingPlace->update(['max_tables' => 5]);

    $voter = Voter::factory()->create([
        'polling_place_id' => $this->pollingPlace->id,
        'polling_table_number' => null,
    ]);

    $this->artisan('census:backfill-polling-table-number')->assertSuccessful();

    expect($voter->fresh()->polling_table_number)->toBeNull();
});

test('ignores voters whose polling_table_number is already set', function () {
    $voter = Voter::factory()->create([
        'polling_place_id' => $this->pollingPlace->id,
        'polling_table_number' => 3,
    ]);

    PollingPlaceResolution::factory()->create([
        'voter_id' => $voter->id,
        'polling_place_id' => $this->pollingPlace->id,
        'table_number' => '9',
    ]);

    $this->artisan('census:backfill-polling-table-number')->assertSuccessful();

    expect($voter->fresh()->polling_table_number)->toBe(3);
});

test('dry-run mode writes nothing', function () {
    $voter = Voter::factory()->create([
        'polling_place_id' => $this->pollingPlace->id,
        'polling_table_number' => null,
    ]);

    PollingPlaceResolution::factory()->create([
        'voter_id' => $voter->id,
        'polling_place_id' => $this->pollingPlace->id,
        'table_number' => '8',
    ]);

    $this->artisan('census:backfill-polling-table-number', ['--dry-run' => true])->assertSuccessful();

    expect($voter->fresh()->polling_table_number)->toBeNull();
});
