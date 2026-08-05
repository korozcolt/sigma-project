<?php

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\CensusRecord;
use App\Models\NationalIdentityRecord;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\VoterValidationService;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new VoterValidationService;
});

it('validates voter found in census', function () {
    $campaign = Campaign::factory()->create();

    RegistraduriaLookup::factory()->create([
        'document_number' => '1234567890',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $result = $this->service->validateAgainstCensus($voter);

    expect($result['found'])->toBeTrue();
    expect($result['match'])->toBeNull();
    expect($result['confidence'])->toBe('high');
    expect($voter->fresh()->polling_place_source)->not->toBeNull();
});

it('validates voter not found in census', function () {
    $campaign = Campaign::factory()->create();

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '9999999999',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $result = $this->service->validateAgainstCensus($voter);

    expect($result['found'])->toBeFalse();
    expect($result['match'])->toBeNull();
    expect($result['confidence'])->toBe('none');
});

it('updates voter status to verified when found in census', function () {
    $campaign = Campaign::factory()->create();

    CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs(User::factory()->create());

    $updatedVoter = $this->service->updateVoterStatus($voter, true);

    expect($updatedVoter->status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($updatedVoter->census_validated_at)->not->toBeNull();
});

it('updates voter status to census_not_found (not the dead-end rejected_census) when not found in census', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs(User::factory()->create());

    $updatedVoter = $this->service->updateVoterStatus($voter, false);

    expect($updatedVoter->status)->toBe(VoterStatus::CENSUS_NOT_FOUND);
    expect($updatedVoter->notes)->toContain('No se encontró en el censo electoral');
});

it('does not downgrade a DUPLICATE voter even when found in census with polling_place_source LIVE', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::DUPLICATE,
        'polling_place_source' => PollingPlaceSource::LIVE,
    ]);

    actingAs(User::factory()->create());

    $updatedVoter = $this->service->updateVoterStatus($voter, true);

    expect($updatedVoter->status)->toBe(VoterStatus::DUPLICATE);
    expect(ValidationHistory::where('voter_id', $voter->id)->count())->toBe(0);
});

it('does not downgrade a CORRECTION_REQUIRED voter even when found in census with polling_place_source LIVE', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::CORRECTION_REQUIRED,
        'polling_place_source' => PollingPlaceSource::LIVE,
    ]);

    actingAs(User::factory()->create());

    $updatedVoter = $this->service->updateVoterStatus($voter, true);

    expect($updatedVoter->status)->toBe(VoterStatus::CORRECTION_REQUIRED);
    expect(ValidationHistory::where('voter_id', $voter->id)->count())->toBe(0);
});

it('validates and updates voter in one operation', function () {
    $campaign = Campaign::factory()->create();

    RegistraduriaLookup::factory()->create([
        'document_number' => '1234567890',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs(User::factory()->create());

    $result = $this->service->validateAndUpdate($voter);

    expect($result['found'])->toBeTrue();
    expect($result['voter']->status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($result['match'])->toBeNull();
});

it('validates all pending voters for a campaign', function () {
    $campaign = Campaign::factory()->create();

    // Re-seed the two "found" fixtures via the permanent Registraduría lookup cache
    // (census_records is no longer consulted at all — see VoterValidationService).
    RegistraduriaLookup::factory()->create([
        'document_number' => '1111111111',
    ]);

    RegistraduriaLookup::factory()->create([
        'document_number' => '2222222222',
    ]);

    // Create pending voters - 2 will be found, 1 will land on census_not_found
    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1111111111',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '2222222222',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '9999999999',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs(User::factory()->create());

    $result = $this->service->validatePendingVoters($campaign->id);

    expect($result['validated'])->toBe(3);
    expect($result['found'])->toBe(2);
    expect($result['rejected'])->toBe(1);

    expect(Voter::where('status', VoterStatus::VERIFIED_CENSUS)->count())->toBe(2);
    expect(Voter::where('status', VoterStatus::CENSUS_NOT_FOUND)->count())->toBe(1);
});

it('checks if document exists in census', function () {
    $campaign = Campaign::factory()->create();

    RegistraduriaLookup::factory()->create([
        'document_number' => '1234567890',
    ]);

    NationalIdentityRecord::factory()->create([
        'cedula' => '5555555555',
    ]);

    expect($this->service->documentExistsInCensus($campaign->id, '1234567890'))->toBeTrue();
    expect($this->service->documentExistsInCensus($campaign->id, '5555555555'))->toBeTrue();
    expect($this->service->documentExistsInCensus($campaign->id, '9999999999'))->toBeFalse();
});

it('does not count a lone census_records row as existing in the census (orphaned table no longer consulted)', function () {
    $campaign = Campaign::factory()->create();

    CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '8888888888',
    ]);

    expect($this->service->documentExistsInCensus($campaign->id, '8888888888'))->toBeFalse();
});

it('gets census info for a voter', function () {
    $campaign = Campaign::factory()->create();

    $censusRecord = CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
        'polling_station' => 'Mesa 123',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
    ]);

    $info = $this->service->getCensusInfo($voter);

    expect($info)->toBeInstanceOf(CensusRecord::class);
    expect($info->id)->toBe($censusRecord->id);
    expect($info->polling_station)->toBe('Mesa 123');
});

it('returns null when getting census info for voter not in census', function () {
    $voter = Voter::factory()->create([
        'document_number' => '9999999999',
    ]);

    $info = $this->service->getCensusInfo($voter);

    expect($info)->toBeNull();
});

it('only validates voters in the same campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    CensusRecord::factory()->create([
        'campaign_id' => $campaign1->id,
        'document_number' => '1234567890',
    ]);

    // Voter in campaign 2 with same document number
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign2->id,
        'document_number' => '1234567890',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $result = $this->service->validateAgainstCensus($voter);

    expect($result['found'])->toBeFalse();
});
