<?php

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Ejecutar RoleSeeder antes de cada test
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

it('creates all roles from UserRole enum', function () {
    $roles = Role::all();

    expect($roles)->toHaveCount(5);

    foreach (UserRole::cases() as $role) {
        assertDatabaseHas('roles', [
            'name' => $role->value,
            'guard_name' => 'web',
        ]);
    }
});

it('can assign a role to a user', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::LEADER->value);

    expect($user->hasRole(UserRole::LEADER->value))->toBeTrue();
});

it('can assign multiple roles to a user', function () {
    $user = User::factory()->create();

    $user->assignRole([
        UserRole::LEADER->value,
        UserRole::REVIEWER->value,
    ]);

    expect($user->hasRole(UserRole::LEADER->value))->toBeTrue();
    expect($user->hasRole(UserRole::REVIEWER->value))->toBeTrue();
});

it('can remove a role from a user', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::LEADER->value);

    $user->removeRole(UserRole::LEADER->value);

    expect($user->hasRole(UserRole::LEADER->value))->toBeFalse();
});

it('can check if user has any role', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::COORDINATOR->value);

    expect($user->hasAnyRole([
        UserRole::COORDINATOR->value,
        UserRole::LEADER->value,
    ]))->toBeTrue();

    expect($user->hasAnyRole([
        UserRole::SUPER_ADMIN->value,
        UserRole::ADMIN_CAMPAIGN->value,
    ]))->toBeFalse();
});

it('super admin role exists and can be assigned', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::SUPER_ADMIN->value);

    expect($user->hasRole(UserRole::SUPER_ADMIN->value))->toBeTrue();
});

it('admin campaign role exists and can be assigned', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    expect($user->hasRole(UserRole::ADMIN_CAMPAIGN->value))->toBeTrue();
});

it('coordinator role exists and can be assigned', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::COORDINATOR->value);

    expect($user->hasRole(UserRole::COORDINATOR->value))->toBeTrue();
});

it('leader role exists and can be assigned', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::LEADER->value);

    expect($user->hasRole(UserRole::LEADER->value))->toBeTrue();
});

it('reviewer role exists and can be assigned', function () {
    $user = User::factory()->create();

    $user->assignRole(UserRole::REVIEWER->value);

    expect($user->hasRole(UserRole::REVIEWER->value))->toBeTrue();
});

it('enum has correct label for each role', function () {
    expect(UserRole::SUPER_ADMIN->getLabel())->toBe('Super Administrador');
    expect(UserRole::ADMIN_CAMPAIGN->getLabel())->toBe('Administrador de Campaña');
    expect(UserRole::COORDINATOR->getLabel())->toBe('Coordinador');
    expect(UserRole::LEADER->getLabel())->toBe('Líder');
    expect(UserRole::REVIEWER->getLabel())->toBe('Revisor');
});

it('enum has correct color for each role', function () {
    expect(UserRole::SUPER_ADMIN->getColor())->toBe('danger');
    expect(UserRole::ADMIN_CAMPAIGN->getColor())->toBe('warning');
    expect(UserRole::COORDINATOR->getColor())->toBe('primary');
    expect(UserRole::LEADER->getColor())->toBe('success');
    expect(UserRole::REVIEWER->getColor())->toBe('info');
});

it('enum has correct icon for each role', function () {
    expect(UserRole::SUPER_ADMIN->getIcon())->toBe('heroicon-m-shield-check');
    expect(UserRole::ADMIN_CAMPAIGN->getIcon())->toBe('heroicon-m-user-circle');
    expect(UserRole::COORDINATOR->getIcon())->toBe('heroicon-m-users');
    expect(UserRole::LEADER->getIcon())->toBe('heroicon-m-user');
    expect(UserRole::REVIEWER->getIcon())->toBe('heroicon-m-eye');
});

it('enum has description for each role', function () {
    expect(UserRole::SUPER_ADMIN->getDescription())->not->toBeNull();
    expect(UserRole::ADMIN_CAMPAIGN->getDescription())->not->toBeNull();
    expect(UserRole::COORDINATOR->getDescription())->not->toBeNull();
    expect(UserRole::LEADER->getDescription())->not->toBeNull();
    expect(UserRole::REVIEWER->getDescription())->not->toBeNull();
});
