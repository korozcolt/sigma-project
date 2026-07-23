<?php

declare(strict_types=1);

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\CensusRecord;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\VoterValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->validator = User::factory()->create();
    $this->service = new VoterValidationService;
});

test('validating a voter found in the census sets verified status and writes an auditable validation history', function () {
    $campaign = Campaign::factory()->create();

    CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '11111111',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '11111111',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs($this->validator);

    $this->service->validateAndUpdate($voter);

    $voter->refresh();

    expect($voter->status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($voter->census_validated_at)->not->toBeNull();

    $history = ValidationHistory::where('voter_id', $voter->id)->latest()->first();

    expect($history)->not->toBeNull();
    expect($history->validation_type)->toBe('census');
    expect($history->new_status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($history->validated_by)->toBe($this->validator->id);
});

test('validating a voter not found in the census sets rejected status and writes an auditable validation history', function () {
    $campaign = Campaign::factory()->create();

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '22222222',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs($this->validator);

    $this->service->validateAndUpdate($voter);

    $voter->refresh();

    expect($voter->status)->toBe(VoterStatus::REJECTED_CENSUS);

    $history = ValidationHistory::where('voter_id', $voter->id)->latest()->first();

    expect($history)->not->toBeNull();
    expect($history->validation_type)->toBe('census');
    expect($history->new_status)->toBe(VoterStatus::REJECTED_CENSUS);
});

test('the validation history records the previous status before validation ran', function () {
    $campaign = Campaign::factory()->create();

    CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '33333333',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '33333333',
        'status' => VoterStatus::CORRECTION_REQUIRED,
    ]);

    actingAs($this->validator);

    $this->service->validateAndUpdate($voter);

    $history = ValidationHistory::where('voter_id', $voter->id)->latest()->first();

    expect($history->previous_status)->toBe(VoterStatus::CORRECTION_REQUIRED);
    expect($history->new_status)->toBe(VoterStatus::VERIFIED_CENSUS);
});
