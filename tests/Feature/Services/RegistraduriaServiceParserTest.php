<?php

use App\Services\RegistraduriaService;
use Illuminate\Support\Facades\Http;

it('parses a real captured wsp success response into the 7 structured fields', function () {
    $html = file_get_contents(base_path('tests/fixtures/registraduria/consulta-sample.html'));

    Http::fake([
        '*/result/*' => Http::response([
            'status' => 'done',
            'outcome' => 'success',
            'data' => ['raw_message_html' => $html],
            'error' => null,
        ]),
    ]);

    $service = new RegistraduriaService;
    $result = $service->getResult('fake-session');

    expect($result['status'])->toBe('done')
        ->and($result['data'])->toHaveKeys([
            'puesto_nombre', 'puesto_codigo', 'zona_codigo',
            'mesa_numero', 'departamento', 'municipio', 'direccion',
        ])
        ->and($result['data']['puesto_nombre'])->not->toBe('')
        ->and($result['data']['departamento'])->not->toBe('')
        ->and($result['data']['municipio'])->not->toBe('');
});

it('leaves a non-success done payload with null data unchanged', function () {
    Http::fake([
        '*/result/*' => Http::response([
            'status' => 'done',
            'outcome' => 'not_found',
            'data' => null,
            'error' => null,
        ]),
    ]);

    $service = new RegistraduriaService;
    $result = $service->getResult('fake-session');

    expect($result['data'])->toBeNull();
});

it('leaves a pending payload unchanged', function () {
    Http::fake([
        '*/result/*' => Http::response([
            'status' => 'pending',
            'data' => null,
            'error' => null,
        ]),
    ]);

    $service = new RegistraduriaService;
    $result = $service->getResult('fake-session');

    expect($result['status'])->toBe('pending')
        ->and($result['data'])->toBeNull();
});
