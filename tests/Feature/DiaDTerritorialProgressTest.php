<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\DiaDTerritorialProgressTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $department = Department::factory()->create();
    $this->municipalityOne = Municipality::factory()->create(['department_id' => $department->id]);
    $this->municipalityTwo = Municipality::factory()->create(['department_id' => $department->id]);
    $this->emptyMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('widget shows one row per municipality with correct voted/did-not-vote/total counts', function () {
    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipalityOne->id,
        'status' => VoterStatus::VOTED,
    ]);
    Voter::factory()->count(1)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipalityOne->id,
        'status' => VoterStatus::DID_NOT_VOTE,
    ]);
    Voter::factory()->count(1)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipalityOne->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    Voter::factory()->count(1)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipalityTwo->id,
        'status' => VoterStatus::VOTED,
    ]);

    Livewire::test(DiaDTerritorialProgressTable::class)
        ->assertOk()
        ->assertSee($this->municipalityOne->name)
        ->assertSee($this->municipalityTwo->name)
        ->assertSeeInOrder([$this->municipalityOne->name, $this->municipalityTwo->name]);

    $municipalityOneFresh = Municipality::withCount([
        'voters as total' => fn ($q) => $q->where('campaign_id', $this->campaign->id),
        'voters as voted_count' => fn ($q) => $q->where('campaign_id', $this->campaign->id)->where('status', VoterStatus::VOTED->value),
        'voters as did_not_vote_count' => fn ($q) => $q->where('campaign_id', $this->campaign->id)->where('status', VoterStatus::DID_NOT_VOTE->value),
    ])->find($this->municipalityOne->id);

    expect($municipalityOneFresh->total)->toBe(4)
        ->and($municipalityOneFresh->voted_count)->toBe(2)
        ->and($municipalityOneFresh->did_not_vote_count)->toBe(1);
});

test('a municipality with zero voters in the active campaign does not appear as a row', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipalityOne->id,
        'status' => VoterStatus::VOTED,
    ]);

    Livewire::test(DiaDTerritorialProgressTable::class)
        ->assertOk()
        ->assertSee($this->municipalityOne->name)
        ->assertDontSee($this->emptyMunicipality->name);
});
