<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('leader');
});

test('un usuario puede crearse sin correo electrónico gracias a que users.email ahora es nullable', function () {
    $leader = User::factory()->create([
        'email' => null,
        'document_number' => '1234567890',
    ]);

    expect($leader->email)->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $leader->id, 'email' => null]);
});

test('un usuario sin correo puede iniciar sesión con su número de cédula', function () {
    $leader = User::factory()->withoutTwoFactor()->create([
        'email' => null,
        'document_number' => '1234567890',
    ]);
    $leader->assignRole('leader');

    $response = $this->post(route('login.store'), [
        'email' => '1234567890',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('leader.dashboard', absolute: false));
    $this->assertAuthenticatedAs($leader);
});
