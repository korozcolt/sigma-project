<?php

use App\Enums\VoterStatus;
use App\Filament\Pages\ManageElectionEvents;
use App\Jobs\FinalizeElectionEvent;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Models\VoteRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

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

test('deactivateEvent dispatches FinalizeElectionEvent onto the real queue', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    Role::firstOrCreate(['name' => 'admin_campaign', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin_campaign');

    $event = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
        'date' => now(),
    ]);

    $this->actingAs($admin);
    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(ManageElectionEvents::class)
        ->call('deactivateEvent', $event->id);

    Queue::assertPushed(FinalizeElectionEvent::class, function (FinalizeElectionEvent $job) use ($event, $admin) {
        return $job->electionEventId === $event->id && $job->validatedByUserId === $admin->id;
    });
});

test('FinalizeElectionEvent failed hook logs the error', function () {
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->create();
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id]);

    Log::shouldReceive('error')
        ->once()
        ->with('election_event.finalize.failed', Mockery::on(function (array $context) use ($event, $admin) {
            return $context['election_event_id'] === $event->id
                && $context['validated_by_user_id'] === $admin->id
                && $context['error'] === 'boom';
        }));

    $job = new FinalizeElectionEvent($event->id, $admin->id);
    $job->failed(new \Exception('boom'));
});
