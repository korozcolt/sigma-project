<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\TopPollingPlacesTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses()->group('dashboard-widgets');

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('top polling places table excludes DUPLICATE status voters from apoyos_count (D-01)', function () {
    $placeA = PollingPlace::factory()->create(['municipality_id' => $this->municipality->id]);
    $placeB = PollingPlace::factory()->create(['municipality_id' => $this->municipality->id]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $placeA->id,
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $placeA->id,
        'document_number' => '9200000001',
        'status' => VoterStatus::DUPLICATE,
        'duplicate_sequence' => 1,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $placeB->id,
    ]);

    Livewire::test(TopPollingPlacesTable::class)
        ->assertCanSeeTableRecords([$placeA, $placeB])
        ->assertTableColumnStateSet('apoyos_count', 2, $placeA->getKey())
        ->assertTableColumnStateSet('apoyos_count', 2, $placeB->getKey());
});

test('top polling places table does not break when a voter has no polling_place_id', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => null,
    ]);

    Livewire::test(TopPollingPlacesTable::class)
        ->assertOk();
});

test('top polling places table export header action exists', function () {
    Livewire::test(TopPollingPlacesTable::class)
        ->assertTableHeaderActionsExistInOrder(['export']);
});
