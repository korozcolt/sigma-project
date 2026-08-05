<?php

use App\Models\Municipality;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('redirects unauthenticated users away from registraduria routes', function () {
    $this->get(route('registraduria.result', ['id' => 'test-id']))
        ->assertRedirect();

    $this->post(route('registraduria.lookup'), ['cedula' => '123'])
        ->assertRedirect();

    $this->get(route('registraduria.viewport', ['id' => 'test-id']))
        ->assertRedirect();
});

it('returns session id from lookup when service responds successfully', function () {
    Http::fake([
        '*/lookup' => Http::response(['session_id' => 'abc-123'], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.lookup'), ['cedula' => '1234567890'])
        ->assertOk()
        ->assertJson(['session_id' => 'abc-123']);
});

it('returns the cached permanent lookup and never calls the python microservice when the cedula was already resolved', function () {
    // Regression test for the closing task in 260804-us6: RegistraduriaController::lookup()
    // used to call the Python microservice unconditionally, with no permanent-cache check
    // at all, even when the cédula already has a row in registraduria_lookups.
    $municipality = Municipality::factory()->create();

    RegistraduriaLookup::factory()->create([
        'document_number' => '1102812122',
        'puesto_nombre' => 'IE LA CAMPIÑA',
        'municipio' => $municipality->name,
        'mesa_numero' => '05',
        'direccion' => 'Calle Falsa 123',
    ]);

    Http::fake([
        '*/lookup' => Http::response(['session_id' => 'should-not-happen'], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('registraduria.lookup'), ['cedula' => '1102812122'])
        ->assertOk()
        ->assertJson([
            'session_id' => null,
            'status' => 'done',
            'error' => null,
        ]);

    expect($response->json('data.puesto_nombre'))->toBe('IE LA CAMPIÑA');

    Http::assertNothingSent();
});

it('returns 422 when cedula is missing from lookup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.lookup'), [])
        ->assertUnprocessable();
});

it('returns 503 when python service is unreachable for lookup', function () {
    Http::fake([
        '*/lookup' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.lookup'), ['cedula' => '1234567890'])
        ->assertStatus(503);
});

it('proxies result status from python service', function () {
    Http::fake([
        '*/result/*' => Http::response(['status' => 'waiting_captcha', 'data' => null, 'error' => null], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.result', ['id' => 'abc-123']))
        ->assertOk()
        ->assertJson(['status' => 'waiting_captcha']);
});

it('parses raw_message_html into structured fields for the browser polling loop', function () {
    // Regression test for .planning/debug/resolved/registraduria-interactive-result-not-parsed.md:
    // the controller used to proxy the raw microservice JSON straight to the browser
    // (including unparsed raw_message_html), so a real successful live lookup still showed
    // "Error desconocido al consultar la Registraduría" because puesto_nombre never arrived.
    $html = file_get_contents(base_path('tests/fixtures/registraduria/consulta-sample.html'));

    Http::fake([
        '*/result/*' => Http::response([
            'status' => 'done',
            'outcome' => 'success',
            'data' => ['raw_message_html' => $html],
            'error' => null,
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('registraduria.result', ['id' => 'abc-123']))
        ->assertOk();

    $response->assertJson(['status' => 'done']);
    $data = $response->json('data');

    expect($data)->toHaveKeys([
        'puesto_nombre', 'puesto_codigo', 'zona_codigo',
        'mesa_numero', 'departamento', 'municipio', 'direccion',
    ])
        ->and($data)->not->toHaveKey('raw_message_html')
        ->and($data['puesto_nombre'])->not->toBe('')
        ->and($data['departamento'])->not->toBe('')
        ->and($data['municipio'])->not->toBe('');
});

it('returns 404 when result session not found in python service', function () {
    Http::fake([
        '*/result/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.result', ['id' => 'nonexistent']))
        ->assertNotFound();
});

it('proxies screenshot as image/png', function () {
    $fakePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    Http::fake([
        '*/screenshot/*' => Http::response($fakePng, 200, ['Content-Type' => 'image/png']),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.screenshot', ['id' => 'abc-123']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('forwards click coordinates to python service', function () {
    Http::fake([
        '*/click/*' => Http::response(['ok' => true], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.click', ['id' => 'abc-123']), ['x' => 640, 'y' => 400])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('returns 422 when click coordinates are missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.click', ['id' => 'abc-123']), [])
        ->assertUnprocessable();
});

it('proxies viewport dimensions from python service', function () {
    Http::fake([
        '*/viewport/*' => Http::response(['width' => 1280, 'height' => 800], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.viewport', ['id' => 'abc-123']))
        ->assertOk()
        ->assertJson(['width' => 1280, 'height' => 800]);
});
