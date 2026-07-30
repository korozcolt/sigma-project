<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('saves a snapshot row when the balance is available', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);

    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 0, 'balance' => 0.93958]),
    ]);

    $this->artisan('balances:snapshot-2captcha')->assertSuccessful();

    $this->assertDatabaseHas('two_captcha_balance_snapshots', [
        'balance' => 0.93958,
    ]);
});

it('does not save a snapshot row when the balance is unavailable', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);

    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 1, 'errorCode' => 'ERROR_KEY_DOES_NOT_EXIST']),
    ]);

    $this->artisan('balances:snapshot-2captcha')->assertSuccessful();

    $this->assertDatabaseCount('two_captcha_balance_snapshots', 0);
});
