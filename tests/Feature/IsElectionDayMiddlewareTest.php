<?php

declare(strict_types=1);

use App\Http\Middleware\IsElectionDay;
use App\Models\ElectionEvent;
use App\Models\User;
use Illuminate\Http\Request;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->middleware = new IsElectionDay;
    $this->user = User::factory()->create();
});

it('allows access when there is an active event today', function () {
    actingAs($this->user);

    ElectionEvent::factory()->today()->active()->create();

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getContent())->toBe('OK');
});

it('redirects when no active event exists', function () {
    actingAs($this->user);

    ElectionEvent::factory()->today()->inactive()->create();

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain(route('home', absolute: false));
});

it('redirects when accessed before event day', function () {
    actingAs($this->user);

    ElectionEvent::factory()->future()->active()->create();

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain(route('home', absolute: false));
});

it('redirects when accessed after event day', function () {
    actingAs($this->user);

    ElectionEvent::factory()->past()->active()->create();

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain(route('home', absolute: false));
});

it('includes appropriate message when redirecting before event day', function () {
    actingAs($this->user);

    ElectionEvent::factory()->future()->active()->create();

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302);
    expect(session()->has('warning'))->toBeTrue();
    expect(session('warning'))->toContain('Aún no es la fecha correcta');
});

it('includes appropriate message when redirecting after event day', function () {
    actingAs($this->user);

    ElectionEvent::factory()->past()->active()->create();

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302);
    expect(session()->has('warning'))->toBeTrue();
    expect(session('warning'))->toContain('El evento ha finalizado');
});

it('blocks access when event is outside time range', function () {
    actingAs($this->user);

    $now = now();
    ElectionEvent::factory()->today()->active()->create([
        'start_time' => $now->copy()->addHour()->format('H:i:s'),
        'end_time' => $now->copy()->addHours(2)->format('H:i:s'),
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302);
    expect(session()->has('warning'))->toBeTrue();
    expect(session('warning'))->toContain('no está dentro del horario permitido');
});
