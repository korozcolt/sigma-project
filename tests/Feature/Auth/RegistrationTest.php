<?php

// Registration feature is disabled in Fortify config (config/fortify.php line 147)
// Users can only be created by authorized administrators

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
})->skip('Registration feature is disabled - users are created by administrators');

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
})->skip('Registration feature is disabled - users are created by administrators');
