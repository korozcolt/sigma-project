<?php

declare(strict_types=1);

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\ValidationHistory;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('reverts rejected_census voters in the target campaign to pending_review', function () {
    $campaign = Campaign::factory()->create();

    $rejected = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Artisan::call('census:remediate-misrejected', ['--campaign' => $campaign->id]);

    expect($rejected->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW);
});

test('writes a ValidationHistory audit row for each reverted voter', function () {
    $campaign = Campaign::factory()->create();

    $rejected = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Artisan::call('census:remediate-misrejected', ['--campaign' => $campaign->id]);

    $history = ValidationHistory::where('voter_id', $rejected->id)->latest()->first();

    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(VoterStatus::REJECTED_CENSUS)
        ->and($history->new_status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($history->validation_type)->toBe('census')
        ->and($history->validated_by)->toBeNull()
        ->and($history->notes)->toContain('Corrección de datos');
});

test('does not touch voters in other campaigns', function () {
    $targetCampaign = Campaign::factory()->create();
    $otherCampaign = Campaign::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $targetCampaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    $otherCampaignVoter = Voter::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Artisan::call('census:remediate-misrejected', ['--campaign' => $targetCampaign->id]);

    expect($otherCampaignVoter->fresh()->status)->toBe(VoterStatus::REJECTED_CENSUS);
});

test('does not touch voters with statuses other than rejected_census', function () {
    $campaign = Campaign::factory()->create();

    $pendingVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $verifiedVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::VERIFIED_CENSUS,
    ]);

    Artisan::call('census:remediate-misrejected', ['--campaign' => $campaign->id]);

    expect($pendingVoter->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($verifiedVoter->fresh()->status)->toBe(VoterStatus::VERIFIED_CENSUS);
});

test('is idempotent — a second run touches 0 voters', function () {
    $campaign = Campaign::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Artisan::call('census:remediate-misrejected', ['--campaign' => $campaign->id]);

    expect(Voter::where('campaign_id', $campaign->id)->where('status', VoterStatus::REJECTED_CENSUS)->count())->toBe(0);

    $historyCountAfterFirstRun = ValidationHistory::count();

    Artisan::call('census:remediate-misrejected', ['--campaign' => $campaign->id]);

    expect(ValidationHistory::count())->toBe($historyCountAfterFirstRun);
});

test('dry-run reports the count with no writes', function () {
    $campaign = Campaign::factory()->create();

    $rejected = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Artisan::call('census:remediate-misrejected', ['--campaign' => $campaign->id, '--dry-run' => true]);

    expect($rejected->fresh()->status)->toBe(VoterStatus::REJECTED_CENSUS)
        ->and(ValidationHistory::where('voter_id', $rejected->id)->exists())->toBeFalse();
});
