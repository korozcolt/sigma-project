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

    // Número de 10 dígitos - retorna el mismo número limpio
    expect($method->invoke($service, '3001234567'))->toBe('3001234567');

    // Número con +57 - remueve el prefijo y retorna 10 dígitos
    expect($method->invoke($service, '+573001234567'))->toBe('3001234567');

    // Número con 57 - remueve el prefijo y retorna 10 dígitos
    expect($method->invoke($service, '573001234567'))->toBe('3001234567');

    // Número con espacios - limpia y retorna 10 dígitos
    expect($method->invoke($service, '300 123 4567'))->toBe('3001234567');

    // Número inválido - retorna null
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
                'messages' => [
                    [
                        'statusId' => 102, // 102 = enviado exitosamente
                        'price' => 0.034, // price no cost (nombre del campo en API real)
                        'to' => '3001234567',
                    ],
                ],
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
        $body = $request->data();

        return $request->hasHeader('X-Hablame-Key', 'test_api_key')
            && $request->url() === config('services.hablame.api_url').'/sms/v5/send'
            && ($body['from'] ?? null) === config('services.hablame.from')
            && ! array_key_exists('priority', $body);
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

test('classifies statusId 1 (processing) as sent, not failed', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'payLoad' => [
                'batch_id' => 'test_batch_processing',
                'messages' => [
                    [
                        'statusId' => 1, // 1 = en procesamiento (async, confirmado por Hablame vía polling)
                        'price' => 0.034,
                        'to' => '3001234567',
                    ],
                ],
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

    expect($result['sent'])->toBe(1)
        ->and($result['failed'])->toBe(0);
});

test('still classifies a genuinely unknown statusId as failed', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'payLoad' => [
                'batch_id' => 'test_batch_unknown',
                'messages' => [
                    [
                        'statusId' => 999, // código desconocido/fallo genuino
                        'price' => 0.034,
                        'to' => '3001234567',
                    ],
                ],
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

    expect($result['failed'])->toBe(1)
        ->and($result['sent'])->toBe(0);
});

test('sendRaw returns success in sandbox mode without a real HTTP call', function () {
    $service = app(HablameSmsService::class);

    $result = $service->sendRaw('3001234567', 'Tu código es 123456', priority: true);

    expect($result['success'])->toBeTrue();
});

test('sendRaw posts a priority true message when priority is requested', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'payLoad' => [
                'batch_id' => 'test_batch_raw_priority',
                'messages' => [
                    ['statusId' => 102, 'price' => 0.034, 'to' => '3001234567'],
                ],
            ],
            'statusCode' => 201,
            'statusMessage' => 'Message sent successfully',
        ], 201),
    ]);

    $service = app(HablameSmsService::class);
    $result = $service->sendRaw('3001234567', 'Tu código es 123456', priority: true);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->data();
        $message = $body['messages'][0] ?? [];

        return ($body['priority'] ?? false) === true
            && ($body['from'] ?? null) === config('services.hablame.from')
            && ($message['text'] ?? '') === 'Tu código es 123456';
    });
});

test('sendRaw does not include a priority key when priority is false', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'payLoad' => [
                'batch_id' => 'test_batch_raw_regular',
                'messages' => [
                    ['statusId' => 102, 'price' => 0.034, 'to' => '3001234567'],
                ],
            ],
            'statusCode' => 201,
            'statusMessage' => 'Message sent successfully',
        ], 201),
    ]);

    $service = app(HablameSmsService::class);
    $result = $service->sendRaw('3001234567', 'Mensaje regular');

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->data();
        $message = $body['messages'][0] ?? [];

        return ! array_key_exists('priority', $body)
            && ($body['from'] ?? null) === config('services.hablame.from')
            && ! array_key_exists('priority', $message);
    });
});

test('sendRaw throws for an invalid phone number', function () {
    $service = app(HablameSmsService::class);

    $service->sendRaw('12345', 'Texto');
})->throws(\Exception::class, 'Número de teléfono inválido: 12345');

test('can get real account info', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/account/v5/info' => Http::response([
            'statusCode' => 200,
            'statusMessage' => 'OK',
            'payLoad' => [
                'accountId' => 10011897,
                'billing' => [
                    'availableBalance' => 25228,
                    'billingAccount' => 99910011897,
                    'billingCountry' => null,
                    'billingType' => 'prepaid',
                    'monthlyCreditLimit' => 0,
                    'monthlyUsage' => null,
                    'taxId' => null,
                ],
                'blockStatus' => [
                    'billing' => false,
                    'fraud' => false,
                    'general' => false,
                ],
                'createdAt' => '2018-04-19T23:57:54-05:00',
                'currency' => '',
                'email' => null,
                'paymentUrl' => null,
            ],
        ], 200),
    ]);

    $service = app(HablameSmsService::class);
    $result = $service->getAccountInfo();

    expect($result['success'])->toBeTrue()
        ->and($result['account_id'])->toBe(10011897)
        ->and($result['status'])->toBe('active')
        ->and($result['balance'])->toBe(25228)
        ->and($result['billing_type'])->toBe('prepaid')
        ->and($result['created_at'])->toBe('2018-04-19T23:57:54-05:00');
});

test('reports blocked status from real account info when blockStatus is true', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/account/v5/info' => Http::response([
            'statusCode' => 200,
            'statusMessage' => 'OK',
            'payLoad' => [
                'accountId' => 10011897,
                'billing' => [
                    'availableBalance' => 0,
                    'billingType' => 'prepaid',
                ],
                'blockStatus' => [
                    'billing' => false,
                    'fraud' => false,
                    'general' => true,
                ],
                'createdAt' => '2018-04-19T23:57:54-05:00',
            ],
        ], 200),
    ]);

    $service = app(HablameSmsService::class);
    $result = $service->getAccountInfo();

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('blocked');
});
