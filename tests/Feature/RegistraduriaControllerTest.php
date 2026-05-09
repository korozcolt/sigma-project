<?php

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
