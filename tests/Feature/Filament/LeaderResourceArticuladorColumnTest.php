<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\Pages\ListLeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

it('shows the articulador name when the leader coordinator has one assigned', function () {
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

    Livewire::test(ListLeaders::class)
        ->assertTableColumnStateSet('coordinator.areaCoordinator.name', $areaCoordinator->name, record: $leader);
});

it('shows the placeholder when the leader coordinator has no articulador assigned', function () {
    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => null,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create([
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    Livewire::test(ListLeaders::class)
        ->assertTableColumnStateSet('coordinator.areaCoordinator.name', null, record: $leader);
});
