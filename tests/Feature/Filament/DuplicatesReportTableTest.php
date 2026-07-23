<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\DuplicatesReportTable;
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

test('duplicates report table shows both sides of a cross-campaign duplicate cedula while Campaign A is active (D-06)', function () {
    $voterX = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5551111111',
        'duplicate_sequence' => 0,
    ]);

    $campaignB = Campaign::factory()->create(['status' => 'active']);

    Session::put('campaign_context.campaign_id', $campaignB->id);
    Session::put('campaign_context.mode', 'single');

    $voterY = Voter::factory()->create([
        'campaign_id' => $campaignB->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5551111111',
        'duplicate_sequence' => 1,
        'status' => VoterStatus::DUPLICATE,
    ]);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(DuplicatesReportTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$voterX, $voterY])
        ->assertTableHeaderActionsExistInOrder(['export']);
});

test('duplicates report table does not show a duplicate group that only touches an unrelated campaign', function () {
    $campaignC = Campaign::factory()->create(['status' => 'active']);

    Session::put('campaign_context.campaign_id', $campaignC->id);
    Session::put('campaign_context.mode', 'single');

    $unrelatedOriginal = Voter::factory()->create([
        'campaign_id' => $campaignC->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5552222222',
        'duplicate_sequence' => 0,
    ]);

    $unrelatedDuplicate = Voter::factory()->create([
        'campaign_id' => $campaignC->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5552222222',
        'duplicate_sequence' => 1,
        'status' => VoterStatus::DUPLICATE,
    ]);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(DuplicatesReportTable::class)
        ->assertCanNotSeeTableRecords([$unrelatedOriginal, $unrelatedDuplicate]);
});

test('duplicates report table does not show a normal non-disputed voter with no siblings', function () {
    $normalVoter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5553333333',
        'duplicate_sequence' => 0,
    ]);

    Livewire::test(DuplicatesReportTable::class)
        ->assertCanNotSeeTableRecords([$normalVoter]);
});
