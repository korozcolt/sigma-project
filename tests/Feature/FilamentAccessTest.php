<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

uses()->group('filament-access');

beforeEach(function () {
    // Crear roles
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'admin_campaign']);
    Role::firstOrCreate(['name' => 'coordinator']);
    Role::firstOrCreate(['name' => 'leader']);
    Role::firstOrCreate(['name' => 'reviewer']);
});

test('super admin can access Filament admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
});

test('admin campaign can access Filament admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
});

test('reviewer can access Filament admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('reviewer');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
});

test('coordinator cannot access Filament admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('coordinator');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertForbidden();
});

test('leader cannot access Filament admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $response = $this->actingAs($user)->get('/admin');

    $response->assertForbidden();
});

test('guest is redirected to Filament login page', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});
