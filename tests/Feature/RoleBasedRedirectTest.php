<?php

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses()->group('role-redirect');

beforeEach(function () {
    // Crear roles
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'admin_campaign']);
    Role::firstOrCreate(['name' => 'coordinator']);
    Role::firstOrCreate(['name' => 'leader']);

    // Crear datos básicos
    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();
});

test('admin_campaign user can access campaign admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('campaign-admin.dashboard'));

    $response->assertOk();
});

test('admin_campaign is redirected from general dashboard to their dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('campaign-admin.dashboard'));
});

test('coordinator can access coordinator dashboard', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole('coordinator');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('coordinator.dashboard'));

    $response->assertOk();
});

test('coordinator is redirected from general dashboard to their dashboard', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole('coordinator');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('coordinator.dashboard'));
});

test('coordinator can access leaders management', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole('coordinator');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('coordinator.leaders'));

    $response->assertOk();
});

test('coordinator can access create leader form', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole('coordinator');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('coordinator.leaders.create'));

    $response->assertOk();
});

test('leader is redirected from general dashboard to their dashboard', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole('leader');
    $user->campaigns()->attach($this->campaign);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('leader.dashboard'));
});

test('non-admin_campaign cannot access campaign admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $response = $this->actingAs($user)->get(route('campaign-admin.dashboard'));

    $response->assertForbidden();
});

test('non-coordinator cannot access coordinator dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $response = $this->actingAs($user)->get(route('coordinator.dashboard'));

    $response->assertForbidden();
});

test('non-coordinator cannot access leaders management', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $response = $this->actingAs($user)->get(route('coordinator.leaders'));

    $response->assertForbidden();
});

test('user without role can access general dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});
