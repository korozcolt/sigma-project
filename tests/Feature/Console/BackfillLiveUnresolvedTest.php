<?php

declare(strict_types=1);

use App\Enums\PollingPlaceSource;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reverts a LIVE voter with no polling_place_id to an unresolved state', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
        'polling_place_resolved_at' => now(),
        'reconciliation_attempts' => 3,
        'reconciliation_exhausted_at' => now(),
    ]);

    $this->artisan('census:backfill-live-unresolved')->assertSuccessful();

    $fresh = $voter->fresh();

    expect($fresh->polling_place_source)->toBeNull()
        ->and($fresh->polling_place_resolved_at)->toBeNull()
        ->and($fresh->reconciliation_attempts)->toBe(0)
        ->and($fresh->reconciliation_exhausted_at)->toBeNull();
});

test('does not touch a LIVE voter whose polling_place_id is already resolved', function () {
    $pollingPlace = PollingPlace::factory()->create();

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => $pollingPlace->id,
        'polling_place_resolved_at' => now(),
    ]);

    $this->artisan('census:backfill-live-unresolved')->assertSuccessful();

    $fresh = $voter->fresh();

    expect($fresh->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($fresh->polling_place_id)->toBe($pollingPlace->id);
});

test('does not touch a non-LIVE voter with no polling_place_id', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-live-unresolved')->assertSuccessful();

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::SNAPSHOT);
});

test('dry-run lists affected voters without writing any changes', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    $this->artisan('census:backfill-live-unresolved --dry-run')->assertSuccessful();

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::LIVE);
});

// apoyo-marcado-en-vivo-con-puesto-sin-resolver: the dry-run reports whether the fixed
// fuzzy matcher would now resolve each voter's cached municipio, giving an operator full
// visibility into real mismatch patterns before running the real revert.
test('dry-run reports that a cached municipio would now resolve via the fixed fuzzy matcher', function () {
    $municipality = Municipality::factory()->create(['name' => 'Tolú Viejo']);

    $voter = Voter::factory()->create([
        'document_number' => '7000000001',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    RegistraduriaLookup::factory()->create([
        'document_number' => '7000000001',
        'municipio' => 'TOLUVIEJO',
    ]);

    $this->artisan('census:backfill-live-unresolved --dry-run')
        ->expectsOutputToContain("resolvería a \"{$municipality->name}\" (#{$municipality->id})")
        ->assertSuccessful();
});

test('dry-run reports that a genuinely unmatched municipio still needs manual review', function () {
    $voter = Voter::factory()->create([
        'document_number' => '7000000002',
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_id' => null,
    ]);

    RegistraduriaLookup::factory()->create([
        'document_number' => '7000000002',
        'municipio' => 'MUNICIPIO INEXISTENTE',
    ]);

    $this->artisan('census:backfill-live-unresolved --dry-run')
        ->expectsOutputToContain('"MUNICIPIO INEXISTENTE" sigue sin match — requiere revisión manual')
        ->assertSuccessful();
});
