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

it('resolves consulta_censo.url through the real 3-level env fallback chain: own var > REGISTRADURIA_SERVICE_URL > localhost default', function () {
    $original = [
        'CONSULTA_CENSO_SERVICE_URL' => $_ENV['CONSULTA_CENSO_SERVICE_URL'] ?? null,
        'REGISTRADURIA_SERVICE_URL' => $_ENV['REGISTRADURIA_SERVICE_URL'] ?? null,
    ];

    $setEnv = function (string $key, ?string $value): void {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    };

    try {
        // Own var unset, middle-tier set -> resolves to REGISTRADURIA_SERVICE_URL (the actual bug this plan fixes).
        $setEnv('CONSULTA_CENSO_SERVICE_URL', null);
        $setEnv('REGISTRADURIA_SERVICE_URL', 'http://sigma-registraduria:5757');
        $config = require base_path('config/services.php');
        expect($config['consulta_censo']['url'])->toBe('http://sigma-registraduria:5757');

        // Both unset -> falls through to the hardcoded localhost default.
        $setEnv('REGISTRADURIA_SERVICE_URL', null);
        $config = require base_path('config/services.php');
        expect($config['consulta_censo']['url'])->toBe('http://localhost:5757');

        // Own var set -> wins over the middle-tier fallback (zero regression for explicit configs).
        $setEnv('CONSULTA_CENSO_SERVICE_URL', 'http://custom-consulta:9999');
        $setEnv('REGISTRADURIA_SERVICE_URL', 'http://sigma-registraduria:5757');
        $config = require base_path('config/services.php');
        expect($config['consulta_censo']['url'])->toBe('http://custom-consulta:9999');
    } finally {
        $setEnv('CONSULTA_CENSO_SERVICE_URL', $original['CONSULTA_CENSO_SERVICE_URL']);
        $setEnv('REGISTRADURIA_SERVICE_URL', $original['REGISTRADURIA_SERVICE_URL']);
    }
});
