<?php

declare(strict_types=1);

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
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

test('backfills polling_place_id for a LIVE-sourced voter from the permanent lookup table', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000101',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000101',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBe($this->pollingPlace->id);
});

test('backfills polling_place_id for a SNAPSHOT-sourced voter from the national census snapshot', function () {
    NationalCensusRecord::factory()->create([
        'document_number' => '1000000102',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000102',
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBe($this->pollingPlace->id);
});

test('backfills polling_place_id for a DB_RECONSTRUCTION-sourced voter from the campaign census', function () {
    CensusRecord::factory()->create([
        'document_number' => '1000000103',
        'municipality_code' => $this->municipality->code,
        'polling_station' => $this->pollingPlace->name,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000103',
        'polling_place_source' => PollingPlaceSource::DB_RECONSTRUCTION,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBe($this->pollingPlace->id);
});

test('ignores voters whose polling_place_id is already set', function () {
    $otherPlace = PollingPlace::factory()->create([
        'department_id' => $this->department->id,
        'municipality_id' => $this->municipality->id,
    ]);

    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000104',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000104',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => $otherPlace->id,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBe($otherPlace->id);
});

test('ignores voters whose polling_place_source is null', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => null,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBeNull();
});

test('skips a MANUAL-sourced voter with no polling_place_id, since there is no local read to re-derive it', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::MANUAL,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBeNull();
});

test('skips a LIVE-sourced voter with no matching permanent lookup row, leaving polling_place_id null without error', function () {
    $voter = Voter::factory()->create([
        'document_number' => '1000000106',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBeNull();
});

test('dry-run mode writes nothing', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000107',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000107',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-polling-place-id', ['--dry-run' => true])->assertSuccessful();

    expect($voter->fresh()->polling_place_id)->toBeNull();
});

test('never invokes the live/2captcha-touching resolver methods, in either normal or dry-run mode', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000108',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    Voter::factory()->create([
        'document_number' => '1000000108',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    // Partial mock backed by a REAL resolver (empty live-adapter list) so the pure
    // local-read methods (resolveFromPermanentLookup/resolveFromNationalSnapshot/
    // resolveFromCampaignCensus) run for real, while explicitly asserting the
    // live/2captcha-touching methods are never invoked.
    $resolver = Mockery::mock(PollingPlaceResolver::class, [[]])->makePartial();
    $resolver->shouldNotReceive('resolveAutomated');
    $resolver->shouldNotReceive('startLiveLookup');
    $resolver->shouldNotReceive('isLiveReachable');
    app()->instance(PollingPlaceResolver::class, $resolver);

    $this->artisan('census:backfill-polling-place-id')->assertSuccessful();
    $this->artisan('census:backfill-polling-place-id', ['--dry-run' => true])->assertSuccessful();
});
