<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\Pages\ListCoordinators;
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

it('shows the articulador name when the coordinator has one assigned', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => $areaCoordinator->id,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    Livewire::test(ListCoordinators::class)
        ->assertTableColumnStateSet('areaCoordinator.name', $areaCoordinator->name, record: $coordinator);
});

it('shows the placeholder when the coordinator has no articulador assigned', function () {
    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => null,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    Livewire::test(ListCoordinators::class)
        ->assertTableColumnStateSet('areaCoordinator.name', null, record: $coordinator);
});
