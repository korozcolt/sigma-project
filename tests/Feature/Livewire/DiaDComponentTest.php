<?php

declare(strict_types=1);

use App\Enums\VoterStatus;
use App\Filament\Pages\DiaD;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
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
