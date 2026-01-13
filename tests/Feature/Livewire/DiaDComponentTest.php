<?php

declare(strict_types=1);

use App\Enums\VoterStatus;
use App\Filament\Pages\DiaD;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoteRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
});

it('can search voter and mark voted via Livewire DiaD page', function () {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '44556677',
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '44556677')
        ->call('searchVoter')
        ->assertSet('voterId', $voter->id)
        ->call('markVoted');

    expect($voter->fresh()->status)->toBe(VoterStatus::VOTED);
});

it('can mark did not vote via Livewire DiaD page without creating VoteRecord', function () {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '99880077',
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '99880077')
        ->call('searchVoter')
        ->call('markDidNotVote');

    expect($voter->fresh()->status)->toBe(VoterStatus::DID_NOT_VOTE);

    $this->assertDatabaseMissing('vote_records', ['voter_id' => $voter->id]);
});

it('prevents duplicate vote records when marking voted if record exists', function () {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '22334455',
        'status' => VoterStatus::VOTED,
    ]);

    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'election_event_id' => $event->id,
        'campaign_id' => $this->campaign->id,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '22334455')
        ->call('searchVoter')
        ->call('markVoted');

    $this->assertDatabaseCount('vote_records', 1);
    expect($voter->fresh()->status)->toBe(VoterStatus::VOTED);
});

it('creates validation history when marking voted', function () {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '55667788',
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '55667788')
        ->call('searchVoter')
        ->call('markVoted');

    $this->assertDatabaseHas('validation_histories', [
        'voter_id' => $voter->id,
        'validation_type' => 'election',
        'previous_status' => VoterStatus::CONFIRMED->value,
        'new_status' => VoterStatus::VOTED->value,
    ]);
});

it('creates validation history when marking did not vote', function () {
    actingAs($this->user);

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '66778899',
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '66778899')
        ->call('searchVoter')
        ->call('markDidNotVote');

    $this->assertDatabaseHas('validation_histories', [
        'voter_id' => $voter->id,
        'validation_type' => 'election',
        'previous_status' => VoterStatus::CONFIRMED->value,
        'new_status' => VoterStatus::DID_NOT_VOTE->value,
    ]);
});

it('does not mark voted without active event', function() {
    actingAs($this->user);

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '11122233',
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '11122233')
        ->call('searchVoter')
        ->call('markVoted');

    $this->assertDatabaseMissing('vote_records', ['voter_id' => $voter->id]);
    expect($voter->fresh()->status)->toBe(VoterStatus::CONFIRMED);
});

it('updates action permissions after marking voted', function() {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '12121212',
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(DiaD::class)
        ->set('documentNumber', '12121212')
        ->call('searchVoter')
        ->assertSet('canMarkVoted', true)
        ->assertSet('canMarkDidNotVote', true)
        ->call('markVoted')
        ->assertSet('canMarkVoted', false)
        ->assertSet('canMarkDidNotVote', true);
});
