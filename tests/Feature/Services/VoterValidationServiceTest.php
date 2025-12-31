<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\CensusRecord;
use App\Models\Voter;
use App\Services\VoterValidationService;

it('validates pending voters and updates statuses accordingly', function () {
    $campaign = Campaign::factory()->create();

    // Voter that exists in census
    $voterA = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '11111111',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '11111111',
    ]);

    // Voter not in census
    $voterB = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '22222222',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $service = new VoterValidationService();

    $result = $service->validatePendingVoters($campaign->id);

    expect($result['validated'])->toBe(2);
    expect($result['found'])->toBe(1);
    expect($result['rejected'])->toBe(1);

    expect($voterA->fresh()->status->value)->toBe(VoterStatus::VERIFIED_CENSUS->value);
    expect($voterB->fresh()->status->value)->toBe(VoterStatus::REJECTED_CENSUS->value);
});
