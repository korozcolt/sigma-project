<?php

use App\Enums\PollingPlaceSource;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Models\Voter;
use App\Services\InfovotantesService;
use App\Services\PollingPlaceResolver;
use App\Services\RegistraduriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->department = Department::factory()->create([
        'name' => 'SUCRE',
        'code' => '28',
    ]);

    $this->municipality = Municipality::factory()->create([
        'department_id' => $this->department->id,
        'name' => 'SINCELEJO',
        'code' => '001',
    ]);

    $this->pollingPlace = PollingPlace::factory()->create([
        'department_id' => $this->department->id,
        'municipality_id' => $this->municipality->id,
        'dane_department_code' => 28,
        'dane_municipality_code' => 1,
        'zone_code' => 1,
        'place_code' => 1,
        'name' => 'IE LA CAMPIÑA',
        'address' => 'CALLE FALSA 123',
    ]);
});

test('resolveAutomated tries infovotantes first and never calls the wsp lookup endpoint when infovotantes succeeds', function () {
    config([
        'services.infovotantes.live_enabled' => true,
        'services.registraduria.live_enabled' => true,
    ]);

    Http::fake([
        config('services.infovotantes.probe_url').'*' => Http::response('', 200),
        '*/lookup/infovotantes' => Http::response(['session_id' => 'info-session'], 200),
        '*/result/info-session' => Http::response([
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

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = app(PollingPlaceResolver::class);

    $result = $resolver->resolveAutomated('1102812122', $voter);

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::LIVE);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/lookup/infovotantes'));

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/lookup') && ! str_contains($request->url(), '/lookup/infovotantes'));
});

test('resolveAutomated falls back to wsp when infovotantes is unreachable, without ever calling its lookup endpoint', function () {
    config([
        'services.infovotantes.live_enabled' => true,
        'services.registraduria.live_enabled' => true,
    ]);

    Http::fake([
        config('services.infovotantes.probe_url').'*' => Http::failedConnection(),
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
        '*/lookup' => Http::response(['session_id' => 'wsp-session'], 200),
        '*/result/wsp-session' => Http::response([
            'status' => 'done',
            'data' => ['raw_message_html' => ''],
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = app(PollingPlaceResolver::class);

    $resolver->resolveAutomated('1102812122', $voter);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/lookup/infovotantes'));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/lookup'));
});

test('AppServiceProvider registers InfovotantesService ahead of RegistraduriaService in liveAdapters', function () {
    $resolver = app(PollingPlaceResolver::class);

    $property = new ReflectionProperty(PollingPlaceResolver::class, 'liveAdapters');
    $property->setAccessible(true);

    $adapters = $property->getValue($resolver);

    if (! is_array($adapters)) {
        $adapters = iterator_to_array($adapters);
    }

    expect($adapters[0])->toBeInstanceOf(InfovotantesService::class)
        ->and($adapters[1])->toBeInstanceOf(RegistraduriaService::class);
});
