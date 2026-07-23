<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\RejectionsReportTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\VerificationCall;
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

test('rejections report table shows status-rejected voters, call-rejected voters, and never double-counts a voter rejected both ways', function () {
    $voterA = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    $voterB = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);
    VerificationCall::factory()->rejected()->create(['voter_id' => $voterB->id]);

    $voterC = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);
    VerificationCall::factory()->notInterested()->create(['voter_id' => $voterC->id]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    Livewire::test(RejectionsReportTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$voterA, $voterB, $voterC])
        ->assertCountTableRecords(3)
        ->assertTableHeaderActionsExistInOrder(['export']);
});
