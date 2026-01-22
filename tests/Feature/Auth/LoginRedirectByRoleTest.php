<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('leader');
    Role::findOrCreate('super_admin');
});

test('leader login ignora el destino /admin y redirige a su panel', function () {
    $leader = User::factory()->withoutTwoFactor()->create();
    $leader->assignRole('leader');

    $response = $this
        ->withSession(['url.intended' => '/admin'])
        ->post(route('login.store'), [
            'email' => $leader->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('leader.dashboard', absolute: false));
});

test('super administrador respeta el destino /admin después de iniciar sesión', function () {
    $superAdmin = User::factory()->withoutTwoFactor()->create();
    $superAdmin->assignRole('super_admin');

    $response = $this
        ->withSession(['url.intended' => '/admin'])
        ->post(route('login.store'), [
            'email' => $superAdmin->email,
            'password' => 'password',
        ]);

    $response->assertRedirect('/admin');
});

