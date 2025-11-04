<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Message;
use App\Models\Voter;
use App\Services\HablameSmsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.hablame.sandbox_mode', true);
});

test('can send SMS in sandbox mode', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->for($campaign)->create(['phone' => '+573001234567']);

    $message = Message::factory()->for($campaign)->for($voter)->sms()->create();

    $service = app(HablameSmsService::class);
    $result = $service->send($message);

    expect($result['success'])->toBeTrue()
        ->and($result['batch_id'])->toContain('sandbox_')
        ->and($result['sent'])->toBe(1)
        ->and($result['failed'])->toBe(0);
});

test('formats Colombian phone numbers correctly', function () {
    $service = app(HablameSmsService::class);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('formatPhoneNumber');
    $method->setAccessible(true);

    // Número de 10 dígitos
    expect($method->invoke($service, '3001234567'))->toBe('+573001234567');

    // Número con +57
    expect($method->invoke($service, '+573001234567'))->toBe('+573001234567');

    // Número con 57
    expect($method->invoke($service, '573001234567'))->toBe('+573001234567');

    // Número con espacios
    expect($method->invoke($service, '300 123 4567'))->toBe('+573001234567');

    // Número inválido
    expect($method->invoke($service, '12345'))->toBeNull();
});

test('can get account info in sandbox mode', function () {
    $service = app(HablameSmsService::class);
    $result = $service->getAccountInfo();

    expect($result['success'])->toBeTrue()
        ->and($result['account_id'])->toBe('sandbox_account')
        ->and($result['status'])->toBe('active')
        ->and($result['balance'])->toBeGreaterThan(0);
});

test('validates API key', function () {
    Config::set('services.hablame.api_key', 'test_key');

    $service = app(HablameSmsService::class);

    expect($service->validateApiKey())->toBeTrue();
});

test('throws exception when API key is missing', function () {
    Config::set('services.hablame.api_key', null);
    Config::set('services.hablame.sandbox_mode', false);

    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->for($campaign)->create();
    $message = Message::factory()->for($campaign)->for($voter)->sms()->create();

    $service = app(HablameSmsService::class);
    $service->send($message);
})->throws(\Exception::class, 'Hablame API Key no configurada');

test('can send SMS via real API', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'payLoad' => [
                'batch_id' => 'test_batch_123',
                'sent' => 1,
                'failed' => 0,
                'cost' => 0.034,
            ],
            'statusCode' => 201,
            'statusMessage' => 'Message sent successfully',
            'responseTime' => '85',
        ], 201),
    ]);

    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->for($campaign)->create(['phone' => '+573001234567']);
    $message = Message::factory()->for($campaign)->for($voter)->sms()->create();

    $service = app(HablameSmsService::class);
    $result = $service->send($message);

    expect($result['success'])->toBeTrue()
        ->and($result['batch_id'])->toBe('test_batch_123')
        ->and($result['sent'])->toBe(1)
        ->and($result['cost'])->toBe(0.034);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Hablame-Key', 'test_api_key')
            && $request->url() === config('services.hablame.api_url').'/sms/v5/send';
    });
});

test('handles API errors gracefully', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'statusCode' => 400,
            'statusMessage' => 'Invalid phone number',
        ], 400),
    ]);

    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->for($campaign)->create(['phone' => '+573001234567']);
    $message = Message::factory()->for($campaign)->for($voter)->sms()->create();

    $service = app(HablameSmsService::class);
    $result = $service->send($message);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Invalid phone number');
});

test('can get real account info', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/v5/account/info' => Http::response([
            'statusCode' => 200,
            'statusMessage' => 'OK',
            'payLoad' => [
                'account_id' => 'acc_123',
                'status' => 'active',
                'balance' => 123.45,
                'billing_type' => 'prepaid',
            ],
        ], 200),
    ]);

    $service = app(HablameSmsService::class);
    $result = $service->getAccountInfo();

    expect($result['success'])->toBeTrue()
        ->and($result['account_id'])->toBe('acc_123')
        ->and($result['status'])->toBe('active')
        ->and($result['balance'])->toBe(123.45);
});
