<?php

use App\Services\InfovotantesService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('returns false with no HTTP call when the live kill switch is off', function () {
    config(['services.infovotantes.live_enabled' => false]);

    Http::fake();

    $service = new InfovotantesService;

    expect($service->isReachable())->toBeFalse();

    Http::assertNothingSent();
});

it('returns true when the probe responds successfully', function () {
    config(['services.infovotantes.live_enabled' => true]);

    Http::fake([
        config('services.infovotantes.probe_url').'*' => Http::response('', 200),
    ]);

    $service = new InfovotantesService;

    expect($service->isReachable())->toBeTrue();
});

it('returns false when the probe is unreachable', function () {
    config(['services.infovotantes.live_enabled' => true]);

    Http::fake([
        config('services.infovotantes.probe_url').'*' => Http::failedConnection(),
    ]);

    $service = new InfovotantesService;

    expect($service->isReachable())->toBeFalse();
});

it('starts a lookup against the /lookup/infovotantes endpoint and returns the session_id', function () {
    Http::fake([
        '*/lookup/infovotantes' => Http::response(['session_id' => 'info-session-abc'], 200),
    ]);

    $service = new InfovotantesService;

    expect($service->startLookup('1102812122'))->toBe('info-session-abc');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/lookup/infovotantes'));
});

it('throws when the lookup endpoint is unreachable', function () {
    Http::fake([
        '*/lookup/infovotantes' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $service = new InfovotantesService;

    expect(fn () => $service->startLookup('1102812122'))->toThrow(ConnectionException::class);
});

it('passes through already-structured fields from getResult without any HTML parsing', function () {
    Http::fake([
        '*/result/*' => Http::response([
            'status' => 'done',
            'data' => [
                'puesto_nombre' => 'IE LA CAMPIÑA',
                'puesto_codigo' => '1',
                'zona_codigo' => '1',
                'mesa_numero' => '05',
                'departamento' => 'SUCRE',
                'municipio' => 'SINCELEJO',
                'direccion' => 'CALLE FALSA 123',
            ],
            'error' => null,
        ]),
    ]);

    $service = new InfovotantesService;

    $result = $service->getResult('info-session-abc');

    expect($result['status'])->toBe('done')
        ->and($result['data'])->toBe([
            'puesto_nombre' => 'IE LA CAMPIÑA',
            'puesto_codigo' => '1',
            'zona_codigo' => '1',
            'mesa_numero' => '05',
            'departamento' => 'SUCRE',
            'municipio' => 'SINCELEJO',
            'direccion' => 'CALLE FALSA 123',
        ]);
});

it('returns an error status when the session is not found', function () {
    Http::fake([
        '*/result/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $service = new InfovotantesService;

    $result = $service->getResult('missing-session');

    expect($result['status'])->toBe('error');
});
