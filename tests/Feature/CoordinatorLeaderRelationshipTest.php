<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

it('un líder pertenece a un coordinador', function () {
    $coordinator = User::factory()->create();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create([
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    expect($leader->coordinator?->id)->toBe($coordinator->id);
    expect($coordinator->leaders()->pluck('id')->all())->toContain($leader->id);
});

it('un coordinador puede asignarse a sí mismo como líder', function () {
    $user = User::factory()->create();
    $user->assignRole([UserRole::COORDINATOR->value, UserRole::LEADER->value]);
    $user->update(['coordinator_user_id' => $user->id]);

    expect($user->coordinator?->id)->toBe($user->id);
    expect($user->leaders()->pluck('id')->all())->toContain($user->id);
});

