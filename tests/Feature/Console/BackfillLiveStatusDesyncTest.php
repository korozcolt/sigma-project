<?php

declare(strict_types=1);

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('syncs status to VERIFIED_CENSUS for a voter stuck with polling_place_source LIVE and a non-terminal status', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $this->artisan('census:backfill-live-status-desync')->assertSuccessful();

    expect($voter->fresh()->status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($voter->fresh()->census_validated_at)->not->toBeNull();
});

test('ignores voters whose polling_place_source is not LIVE', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $this->artisan('census:backfill-live-status-desync')->assertSuccessful();

    expect($voter->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW);
});

test('respects the non-downgradable status guard for already-stronger statuses', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'status' => VoterStatus::CONFIRMED,
    ]);

    $this->artisan('census:backfill-live-status-desync')->assertSuccessful();

    expect($voter->fresh()->status)->toBe(VoterStatus::CONFIRMED);
});

test('dry-run mode writes nothing', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $this->artisan('census:backfill-live-status-desync', ['--dry-run' => true])->assertSuccessful();

    expect($voter->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW);
});

test('never invokes the polling place resolver, in either normal or dry-run mode', function () {
    $resolver = Mockery::mock(PollingPlaceResolver::class);
    $resolver->shouldNotReceive('resolveAutomated');
    $resolver->shouldNotReceive('resolveFromPermanentLookup');
    $resolver->shouldNotReceive('resolveFromNationalSnapshot');
    $resolver->shouldNotReceive('resolveFromCampaignCensus');
    $resolver->shouldNotReceive('startLiveLookup');
    $resolver->shouldNotReceive('isLiveReachable');
    app()->instance(PollingPlaceResolver::class, $resolver);

    Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $this->artisan('census:backfill-live-status-desync')->assertSuccessful();
    $this->artisan('census:backfill-live-status-desync', ['--dry-run' => true])->assertSuccessful();
});
