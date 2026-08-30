<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
    Session::put('campaign_context.mode', 'all');
    Session::forget('campaign_context.campaign_id');
});

it('shows the leader coordinator and their articulador when a leader registers the apoyo', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => $areaCoordinator->id,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create([
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    $voter = Voter::factory()->create([
        'registered_by' => $leader->id,
    ]);

    Livewire::test(ListVoters::class)
        ->assertTableColumnStateSet('coordinador', $coordinator->name, record: $voter)
        ->assertTableColumnStateSet('articulador', $areaCoordinator->name, record: $voter);
});

it('shows the coordinator itself and their articulador when a coordinator registers the apoyo directly', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => $areaCoordinator->id,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $voter = Voter::factory()->create([
        'registered_by' => $coordinator->id,
    ]);

    Livewire::test(ListVoters::class)
        ->assertTableColumnStateSet('coordinador', $coordinator->name, record: $voter)
        ->assertTableColumnStateSet('articulador', $areaCoordinator->name, record: $voter);
});

it('shows N/A in articulador when the registrant hierarchy has no articulador assigned', function () {
    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => null,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create([
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    $voter = Voter::factory()->create([
        'registered_by' => $leader->id,
    ]);

    Livewire::test(ListVoters::class)
        ->assertTableColumnStateSet('coordinador', $coordinator->name, record: $voter)
        ->assertTableColumnStateSet('articulador', 'N/A', record: $voter);
});
