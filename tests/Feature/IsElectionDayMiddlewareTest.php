<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Http\Middleware\IsElectionDay;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->middleware = new IsElectionDay;
    $this->user = User::factory()->create();
});

it('allows access on election day', function () {
    actingAs($this->user);

    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'election_date' => Carbon::today(),
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getContent())->toBe('OK');
});

it('redirects when no active campaign exists', function () {
    actingAs($this->user);

    Campaign::factory()->create([
        'status' => CampaignStatus::DRAFT,
        'election_date' => Carbon::today(),
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain(route('home', absolute: false));
});

it('redirects when campaign has no election date', function () {
    actingAs($this->user);

    Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'election_date' => null,
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302);
});

it('redirects when accessed before election day', function () {
    actingAs($this->user);

    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'election_date' => Carbon::tomorrow(),
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain(route('home', absolute: false));
});

it('redirects when accessed after election day', function () {
    actingAs($this->user);

    $campaign = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'election_date' => Carbon::yesterday(),
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain(route('home', absolute: false));
});

it('includes appropriate message when redirecting before election day', function () {
    actingAs($this->user);

    $electionDate = Carbon::tomorrow();
    Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'election_date' => $electionDate,
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302);
    expect(session()->has('warning'))->toBeTrue();
    expect(session('warning'))->toContain('Aún no es el día de votación');
});

it('includes appropriate message when redirecting after election day', function () {
    actingAs($this->user);

    $electionDate = Carbon::yesterday();
    Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'election_date' => $electionDate,
    ]);

    $request = Request::create('/dia-d', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $this->middleware->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(302);
    expect(session()->has('warning'))->toBeTrue();
    expect(session('warning'))->toContain('El periodo de votación ha finalizado');
});
