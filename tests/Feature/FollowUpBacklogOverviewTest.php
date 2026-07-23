<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\FollowUpBacklogOverview;
use App\Models\CallAssignment;
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

test('follow-up backlog overview shows pending validation and pending call counts', function () {
    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    CallAssignment::factory()->create([
        'campaign_id' => $this->campaign->id,
        'status' => 'pending',
    ]);

    CallAssignment::factory()->create([
        'campaign_id' => $this->campaign->id,
        'status' => 'in_progress',
    ]);

    CallAssignment::factory()->create([
        'campaign_id' => $this->campaign->id,
        'status' => 'completed',
    ]);

    Livewire::test(FollowUpBacklogOverview::class)
        ->assertOk()
        ->assertSee('Apoyos Pendientes de Validar')
        ->assertSee('3')
        ->assertSee('Llamadas Pendientes')
        ->assertSee('2');
});

test('follow-up backlog overview shows zero counts with no pending items', function () {
    Livewire::test(FollowUpBacklogOverview::class)
        ->assertOk()
        ->assertSee('Apoyos Pendientes de Validar')
        ->assertSee('0')
        ->assertSee('Llamadas Pendientes');
});
