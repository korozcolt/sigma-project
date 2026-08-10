<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
});

test('area_coordinator role can access the area_coordinator panel', function () {
    $user = User::factory()->create();
    $user->assignRole('area_coordinator');

    expect($user->canAccessPanel(Filament::getPanel('area_coordinator')))->toBeTrue();
});

test('coordinator role without area_coordinator cannot access the area_coordinator panel', function () {
    $user = User::factory()->create();
    $user->assignRole('coordinator');

    expect($user->canAccessPanel(Filament::getPanel('area_coordinator')))->toBeFalse();
});

test('admin_campaign role can access the area_coordinator panel', function () {
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');

    expect($user->canAccessPanel(Filament::getPanel('area_coordinator')))->toBeTrue();
});

test('super_admin role can access the area_coordinator panel', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect($user->canAccessPanel(Filament::getPanel('area_coordinator')))->toBeTrue();
});

test('articulador reaches the /articulador panel dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('area_coordinator');

    $this->actingAs($user)
        ->get('/articulador')
        ->assertOk();
});

test('coordinador is forbidden from the /articulador panel', function () {
    $user = User::factory()->create();
    $user->assignRole('coordinator');

    $this->actingAs($user)
        ->get('/articulador')
        ->assertForbidden();
});
