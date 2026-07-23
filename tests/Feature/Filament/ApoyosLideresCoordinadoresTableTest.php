<?php

use App\Enums\UserRole;
use App\Filament\Widgets\ApoyosLideresCoordinadoresTable;
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
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('apoyos lideres coordinadores table renders, is campaign-scoped, and has an export header action', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $otherCampaign = Campaign::factory()->create(['status' => 'active']);

    Session::put('campaign_context.campaign_id', $otherCampaign->id);
    Session::put('campaign_context.mode', 'single');

    $otherVoter = Voter::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(ApoyosLideresCoordinadoresTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$voter])
        ->assertCanNotSeeTableRecords([$otherVoter])
        ->assertTableHeaderActionsExistInOrder(['export']);
});
