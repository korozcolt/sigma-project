<?php

use App\Enums\VoterStatus;
use App\Jobs\FinalizeElectionEvent;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Models\VoteRecord;

test('FinalizeElectionEvent marks unvoted eligible voters as did_not_vote', function () {
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->create();

    $voterConfirmed = Voter::factory()->create(['campaign_id' => $campaign->id, 'status' => VoterStatus::CONFIRMED]);
    $voterVerifiedCall = Voter::factory()->create(['campaign_id' => $campaign->id, 'status' => VoterStatus::VERIFIED_CALL]);
    $voterPending = Voter::factory()->create(['campaign_id' => $campaign->id, 'status' => VoterStatus::PENDING_REVIEW]);

    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true, 'date' => now()]);

    $voterWithRecord = Voter::factory()->create(['campaign_id' => $campaign->id, 'status' => VoterStatus::CONFIRMED]);
    $voterWithRecord->voteRecords()->create([
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
        'recorded_by' => $admin->id,
        'voted_at' => now(),
    ]);

    FinalizeElectionEvent::dispatchSync($event->id, $admin->id);

    expect($voterConfirmed->fresh()->status)->toBe(VoterStatus::DID_NOT_VOTE)
        ->and($voterVerifiedCall->fresh()->status)->toBe(VoterStatus::DID_NOT_VOTE)
        ->and($voterWithRecord->fresh()->status)->toBe(VoterStatus::CONFIRMED)
        ->and($voterPending->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW);
});

test('FinalizeElectionEvent writes a validation history entry per closed voter', function () {
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->create();

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id, 'status' => VoterStatus::CONFIRMED]);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true, 'date' => now()]);

    FinalizeElectionEvent::dispatchSync($event->id, $admin->id);

    $history = ValidationHistory::where('voter_id', $voter->id)
        ->where('validation_type', 'election')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(VoterStatus::CONFIRMED)
        ->and($history->new_status)->toBe(VoterStatus::DID_NOT_VOTE)
        ->and($history->validated_by)->toBe($admin->id);
});

test('vote_records DB constraint rejects a duplicate voter+event pair', function () {
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true]);

    VoteRecord::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
        'recorded_by' => $admin->id,
        'voted_at' => now(),
    ]);

    expect(fn () => VoteRecord::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
        'recorded_by' => $admin->id,
        'voted_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
