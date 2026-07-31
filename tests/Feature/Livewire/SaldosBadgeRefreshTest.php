<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\SaldosBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::ADMIN_CAMPAIGN->value, 'guard_name' => 'web']);
});

test('a super admin refreshing the balance persists a new snapshot with the live value', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);
    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 0, 'balance' => 7.5]),
    ]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    Livewire::test(SaldosBadge::class)
        ->call('refreshTwoCaptchaBalance')
        ->assertOk();

    $this->assertDatabaseHas('two_captcha_balance_snapshots', [
        'balance' => 7.5,
    ]);
});

test('a failed live check persists no new snapshot', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);
    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 1, 'errorCode' => 'ERROR_KEY_DOES_NOT_EXIST']),
    ]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    Livewire::test(SaldosBadge::class)
        ->call('refreshTwoCaptchaBalance')
        ->assertOk();

    $this->assertDatabaseCount('two_captcha_balance_snapshots', 0);
});

test('a non-super-admin cannot trigger a refresh', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);
    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 0, 'balance' => 7.5]),
    ]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $this->actingAs($user);

    Livewire::test(SaldosBadge::class)
        ->call('refreshTwoCaptchaBalance')
        ->assertOk();

    $this->assertDatabaseCount('two_captcha_balance_snapshots', 0);
    Http::assertNothingSent();
});
