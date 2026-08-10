<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

it('area_coordinator role is seeded by RoleSeeder', function () {
    expect(Role::where('name', UserRole::AREA_COORDINATOR->value)->exists())->toBeTrue();
});

it('a coordinador belongs to an area coordinator via area_coordinator_user_id', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => $areaCoordinator->id,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    expect($coordinator->areaCoordinator())->toBeInstanceOf(BelongsTo::class)
        ->and($coordinator->areaCoordinator?->id)->toBe($areaCoordinator->id)
        ->and($areaCoordinator->coordinators())->toBeInstanceOf(HasMany::class)
        ->and($areaCoordinator->coordinators()->pluck('id')->all())->toContain($coordinator->id);
});

it('area_coordinator_user_id is independent of coordinator_user_id (ARTIC-04)', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinator = User::factory()->create([
        'area_coordinator_user_id' => $areaCoordinator->id,
        'coordinator_user_id' => null,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    expect($coordinator->area_coordinator_user_id)->toBe($areaCoordinator->id)
        ->and($coordinator->coordinator_user_id)->toBeNull();
});

it('a coordinador keeps its existing leader relation unaffected by an assigned area coordinator (no regression)', function () {
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

    expect($leader->coordinator?->id)->toBe($coordinator->id)
        ->and($coordinator->leaders()->pluck('id')->all())->toContain($leader->id);
});

it('an area coordinator can have any number of coordinadores with no backend-enforced cap (ARTIC-05)', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinators = User::factory()->count(25)->create([
        'area_coordinator_user_id' => $areaCoordinator->id,
    ]);
    foreach ($coordinators as $coordinator) {
        $coordinator->assignRole(UserRole::COORDINATOR->value);
    }

    expect($areaCoordinator->coordinators()->count())->toBe(25);
});
