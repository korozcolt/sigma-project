<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;

test('two factor challenge redirects to login when not authenticated', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        expect(Route::has('two-factor.login'))->toBeFalse();
        $this->get('/two-factor-challenge')->assertStatus(404);
        return;
    }

    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        expect(Route::has('two-factor.login'))->toBeFalse();
        $this->get('/two-factor-challenge')->assertStatus(404);
        return;
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
});
