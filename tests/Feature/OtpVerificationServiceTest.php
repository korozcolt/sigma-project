<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\OtpVerification;
use App\Services\OtpVerificationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.hablame.sandbox_mode', true);
});

test('generate creates a 6-digit otp verification row', function () {
    $campaign = Campaign::factory()->create();
    $service = app(OtpVerificationService::class);

    $otp = $service->generate('3001234567', $campaign)->fresh();

    expect($otp)->toBeInstanceOf(OtpVerification::class)
        ->and($otp->code)->toMatch('/^\d{6}$/')
        ->and($otp->attempts)->toBe(0)
        ->and($otp->verified_at)->toBeNull()
        ->and($otp->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(10)
        ->and($otp->expires_at->isFuture())->toBeTrue();
});

test('verify returns true and sets verified_at for the correct code', function () {
    $campaign = Campaign::factory()->create();
    $service = app(OtpVerificationService::class);

    $otp = $service->generate('3001234567', $campaign);

    $result = $service->verify('3001234567', $campaign, $otp->code);

    expect($result)->toBeTrue();
    expect($otp->fresh()->verified_at)->not->toBeNull();
});

test('verify returns false and increments attempts for a wrong code', function () {
    $campaign = Campaign::factory()->create();
    $service = app(OtpVerificationService::class);

    $otp = $service->generate('3001234567', $campaign);

    $result = $service->verify('3001234567', $campaign, '000000');

    expect($result)->toBeFalse();
    $otp->refresh();
    expect($otp->attempts)->toBe(1)
        ->and($otp->verified_at)->toBeNull();
});

test('verify returns false for an expired otp even with the correct code', function () {
    $campaign = Campaign::factory()->create();
    $service = app(OtpVerificationService::class);

    $otp = $service->generate('3001234567', $campaign);

    test()->travel(11)->minutes();

    $result = $service->verify('3001234567', $campaign, $otp->code);

    expect($result)->toBeFalse();
});

test('verify returns false once attempts reach the lockout threshold', function () {
    $campaign = Campaign::factory()->create();
    $service = app(OtpVerificationService::class);

    $otp = $service->generate('3001234567', $campaign);

    for ($i = 0; $i < 5; $i++) {
        $service->verify('3001234567', $campaign, '000000');
    }

    expect($otp->fresh()->attempts)->toBe(5);

    $result = $service->verify('3001234567', $campaign, $otp->code);

    expect($result)->toBeFalse();
});

test('generate sends the otp via HablameSmsService::sendRaw with priority true using the campaign template', function () {
    Config::set('services.hablame.sandbox_mode', false);
    Config::set('services.hablame.api_key', 'test_api_key');

    Http::fake([
        '*/sms/v5/send' => Http::response([
            'payLoad' => [
                'batch_id' => 'test_batch_otp',
                'messages' => [
                    ['statusId' => 102, 'price' => 0.034, 'to' => '3001234567'],
                ],
            ],
            'statusCode' => 201,
            'statusMessage' => 'Message sent successfully',
        ], 201),
    ]);

    $campaign = Campaign::factory()->create([
        'settings' => ['otp_message_template' => 'Codigo SIGMA: {codigo}'],
    ]);

    $service = app(OtpVerificationService::class);
    $otp = $service->generate('3001234567', $campaign);

    Http::assertSent(function ($request) use ($otp) {
        $body = $request->data();
        $message = $body['messages'][0] ?? [];

        return ($body['priority'] ?? false) === true
            && ($message['text'] ?? '') === "Codigo SIGMA: {$otp->code}";
    });
});
