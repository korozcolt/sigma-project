<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Forzar locale en inglés para coincidencia con textos de la vista
    app()->setLocale('en');

    $this->twoFactorEnabled = Features::canManageTwoFactorAuthentication();

    if ($this->twoFactorEnabled) {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
    }
});

test('two factor settings page can be rendered', function () {
    if (! $this->twoFactorEnabled || ! Route::has('two-factor.show')) {
        $this->get('/user/two-factor-authentication')->assertStatus(404);
        return;
    }

    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertOk()
        ->assertSee('Two Factor Authentication')
        ->assertSee('Disabled');
});

test('two factor settings page requires password confirmation when enabled', function () {
    if (! $this->twoFactorEnabled || ! Route::has('two-factor.show')) {
        $this->get('/user/two-factor-authentication')->assertStatus(404);
        return;
    }

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('two-factor.show'));

    $response->assertRedirect(route('password.confirm'));
});

test('two factor settings page returns forbidden response when two factor is disabled', function () {
    if (! Route::has('two-factor.show')) {
        $this->get('/user/two-factor-authentication')->assertStatus(404);
        return;
    }

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'));

    $response->assertForbidden();
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    if (! $this->twoFactorEnabled || ! Route::has('two-factor.show')) {
        $this->get('/user/two-factor-authentication')->assertStatus(404);
        return;
    }

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Volt::test('settings.two-factor');

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});
