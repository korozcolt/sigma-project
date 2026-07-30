<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use App\Models\Voter;
use App\Services\VoterValidationService;

use function Pest\Laravel\actingAs;

it('validates pending voters and updates statuses accordingly', function () {
    $campaign = Campaign::factory()->create();

    // Voter that exists in census — re-seeded via the permanent Registraduría lookup
    // cache (census_records is no longer consulted at all, see VoterValidationService).
    $voterA = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '11111111',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    RegistraduriaLookup::factory()->create([
        'document_number' => '11111111',
    ]);

    // Voter not in census
    $voterB = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '22222222',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $service = new VoterValidationService;

    actingAs(User::factory()->create());

    $result = $service->validatePendingVoters($campaign->id);

    expect($result['validated'])->toBe(2);
    expect($result['found'])->toBe(1);
    expect($result['rejected'])->toBe(1);

    expect($voterA->fresh()->status->value)->toBe(VoterStatus::VERIFIED_CENSUS->value);
    expect($voterB->fresh()->status->value)->toBe(VoterStatus::CENSUS_NOT_FOUND->value);
});
