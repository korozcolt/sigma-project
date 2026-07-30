<?php

use App\Enums\UserRole;
use App\Filament\Widgets\CallQueueTable;
use App\Models\CallAssignment;
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
    $this->caller = User::factory()->create();
    $this->caller->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($this->caller);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('call queue table renders an assignment with an existing verification call without throwing', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $assignment = CallAssignment::factory()->pending()->create([
        'voter_id' => $voter->id,
        'assigned_to' => $this->caller->id,
        'campaign_id' => $this->campaign->id,
    ]);

    VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'assignment_id' => $assignment->id,
        'caller_id' => $this->caller->id,
    ]);

    Livewire::test(CallQueueTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$assignment]);
});

test('call queue table renders with zero assignments without error', function () {
    Livewire::test(CallQueueTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([]);
});

test('sorting call queue table by a joined relation column does not throw an ambiguous column error', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $assignment = CallAssignment::factory()->pending()->create([
        'voter_id' => $voter->id,
        'assigned_to' => $this->caller->id,
        'campaign_id' => $this->campaign->id,
    ]);

    Livewire::test(CallQueueTable::class)
        ->sortTable('voter.municipality.name')
        ->assertOk()
        ->assertCanSeeTableRecords([$assignment]);
});

test('call queue table still filters to pending/in-progress assignments only when sorted by a joined column', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $pending = CallAssignment::factory()->pending()->create([
        'voter_id' => $voter->id,
        'assigned_to' => $this->caller->id,
        'campaign_id' => $this->campaign->id,
    ]);

    $completed = CallAssignment::factory()->completed()->create([
        'voter_id' => $voter->id,
        'assigned_to' => $this->caller->id,
        'campaign_id' => $this->campaign->id,
    ]);

    Livewire::test(CallQueueTable::class)
        ->sortTable('voter.municipality.name')
        ->assertOk()
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$completed]);
});
