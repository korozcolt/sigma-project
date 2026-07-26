<?php

use App\Services\RegistraduriaService;
use Illuminate\Support\Facades\Http;

it('returns false with no HTTP call when the live kill switch is off', function () {
    config(['services.registraduria.live_enabled' => false]);

    Http::fake();

    $service = new RegistraduriaService;

    expect($service->isReachable())->toBeFalse();

    Http::assertNothingSent();
});

it('returns true when the probe responds successfully', function () {
    config(['services.registraduria.live_enabled' => true]);

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
    ]);

    $service = new RegistraduriaService;

    expect($service->isReachable())->toBeTrue();
});

it('sends a GET request (not HEAD) to the configured probe URL', function () {
    config(['services.registraduria.live_enabled' => true]);

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
    ]);

    $service = new RegistraduriaService;

    $service->isReachable();

    Http::assertSent(fn ($request) => $request->method() === 'GET');
});

it('returns true when the probe responds with a redirect', function () {
    config(['services.registraduria.live_enabled' => true]);

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 302, ['Location' => 'https://apiweb-eleccionescolombia.infovotantes.com/']),
    ]);

    $service = new RegistraduriaService;

    expect($service->isReachable())->toBeTrue();
});

it('returns false when the probe is unreachable', function () {
    config(['services.registraduria.live_enabled' => true]);

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::failedConnection(),
    ]);

    $service = new RegistraduriaService;

    expect($service->isReachable())->toBeFalse();
});
