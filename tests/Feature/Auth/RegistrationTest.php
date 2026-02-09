<?php

// Registration feature is disabled in Fortify config (config/fortify.php line 147)
// Users can only be created by authorized administrators

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(404);
    $this->assertGuest();
});
