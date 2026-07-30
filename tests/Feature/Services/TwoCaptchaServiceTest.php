<?php

declare(strict_types=1);

use App\Services\TwoCaptchaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('returns the balance as a float on a successful response', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);

    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 0, 'balance' => 0.93958]),
    ]);

    expect(app(TwoCaptchaService::class)->getBalance())->toBe(0.93958);
});

it('returns null without throwing when 2captcha reports an error', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);

    Http::fake([
        'api.2captcha.com/getBalance' => Http::response(['errorId' => 1, 'errorCode' => 'ERROR_KEY_DOES_NOT_EXIST']),
    ]);

    expect(app(TwoCaptchaService::class)->getBalance())->toBeNull();
});

it('returns null without throwing on a connection timeout', function () {
    config(['services.twocaptcha.api_key' => 'test-key']);

    Http::fake(function (): never {
        throw new ConnectionException('timeout');
    });

    expect(app(TwoCaptchaService::class)->getBalance())->toBeNull();
});

it('returns null without any HTTP call when the api key is missing', function () {
    config(['services.twocaptcha.api_key' => null]);

    Http::fake();

    expect(app(TwoCaptchaService::class)->getBalance())->toBeNull();

    Http::assertNothingSent();
});
