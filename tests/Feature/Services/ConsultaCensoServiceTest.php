<?php

use App\Services\ConsultaCensoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('returns false with no HTTP call when the live kill switch is off', function () {
    config(['services.consulta_censo.live_enabled' => false]);

    Http::fake();

    $service = new ConsultaCensoService;

    expect($service->isReachable())->toBeFalse();

    Http::assertNothingSent();
});

it('returns true when the probe responds successfully', function () {
    config(['services.consulta_censo.live_enabled' => true]);

    Http::fake([
        config('services.consulta_censo.probe_url').'*' => Http::response('', 200),
    ]);

    $service = new ConsultaCensoService;

    expect($service->isReachable())->toBeTrue();
});

it('returns false when the probe is unreachable', function () {
    config(['services.consulta_censo.live_enabled' => true]);

    Http::fake([
        config('services.consulta_censo.probe_url').'*' => Http::failedConnection(),
    ]);

    $service = new ConsultaCensoService;

    expect($service->isReachable())->toBeFalse();
});

it('starts a lookup against the /lookup/censo endpoint and returns the session_id', function () {
    Http::fake([
        '*/lookup/censo' => Http::response(['session_id' => 'censo-session-abc'], 200),
    ]);

    $service = new ConsultaCensoService;

    expect($service->startLookup('1102812122'))->toBe('censo-session-abc');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/lookup/censo'));
});

it('throws when the lookup endpoint is unreachable', function () {
    Http::fake([
        '*/lookup/censo' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $service = new ConsultaCensoService;

    expect(fn () => $service->startLookup('1102812122'))->toThrow(ConnectionException::class);
});

it('passes through already-structured fields from getResult without any HTML parsing', function () {
    Http::fake([
        '*/result/*' => Http::response([
            'status' => 'done',
            'data' => [
                'puesto_nombre' => 'IE LA CAMPINA',
                'puesto_codigo' => '',
                'zona_codigo' => '',
                'mesa_numero' => '05',
                'departamento' => 'SUCRE',
                'municipio' => 'SINCELEJO',
                'direccion' => 'CALLE FALSA 123',
            ],
            'error' => null,
        ]),
    ]);

    $service = new ConsultaCensoService;

    $result = $service->getResult('censo-session-abc');

    expect($result['status'])->toBe('done')
        ->and($result['data']['puesto_nombre'])->toBe('IE LA CAMPINA');
});

it('returns an error status when the session is not found', function () {
    Http::fake([
        '*/result/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $service = new ConsultaCensoService;

    $result = $service->getResult('missing-session');

    expect($result['status'])->toBe('error');
});
