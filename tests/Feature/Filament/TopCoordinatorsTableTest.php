<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\TopCoordinatorsTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses()->group('dashboard-widgets');

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::COORDINATOR->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('top coordinators table shows apoyos_equipo_count as the sum of non-duplicate voters across all leaders, and leaders_count (D-05)', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign);

    $leaderA = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leaderA->campaigns()->attach($this->campaign);

    $leaderB = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leaderB->campaigns()->attach($this->campaign);

    Voter::factory()->count(10)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leaderA->id,
    ]);

    foreach (range(1, 2) as $i) {
        Voter::factory()->create([
            'campaign_id' => $this->campaign->id,
            'municipality_id' => $this->municipality->id,
            'registered_by' => $leaderA->id,
            'document_number' => "910000000{$i}",
            'status' => VoterStatus::DUPLICATE,
            'duplicate_sequence' => 1,
        ]);
    }

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leaderB->id,
    ]);

    Livewire::test(TopCoordinatorsTable::class)
        ->assertCanSeeTableRecords([$coordinator])
        ->assertTableColumnStateSet('apoyos_equipo_count', 15, $coordinator->getKey())
        ->assertTableColumnStateSet('leaders_count', 2, $coordinator->getKey());
});

test('top coordinators table shows a coordinator with 0 leaders and 0 apoyos in the active campaign (coverage report, not top-N)', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign);

    Livewire::test(TopCoordinatorsTable::class)
        ->assertCanSeeTableRecords([$coordinator])
        ->assertTableColumnStateSet('apoyos_equipo_count', 0, $coordinator->getKey())
        ->assertTableColumnStateSet('leaders_count', 0, $coordinator->getKey());
});

test('top coordinators table does not show a coordinator belonging to a different campaign', function () {
    $otherCampaign = Campaign::factory()->create(['status' => 'active']);

    $otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherCoordinator->assignRole(UserRole::COORDINATOR->value);

    Session::put('campaign_context.campaign_id', $otherCampaign->id);
    Session::put('campaign_context.mode', 'single');

    $otherCoordinator->campaigns()->attach($otherCampaign);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(TopCoordinatorsTable::class)
        ->assertCanNotSeeTableRecords([$otherCoordinator]);
});

test('top coordinators table export header action exists', function () {
    Livewire::test(TopCoordinatorsTable::class)
        ->assertTableHeaderActionsExistInOrder(['export']);
});
