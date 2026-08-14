<?php

use App\Exceptions\RegistraduriaLookupInProgressException;
use App\Jobs\CollectRegistraduriaLookupResult;
use App\Models\RegistraduriaLiveSession;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
use App\Services\RegistraduriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

// .planning/debug/resolved/2captcha-duplicate-spend.md — concurrency guard + orphaned-
// result collection. Uses the REAL, container-bound RegistraduriaService adapter (not
// an anonymous test double — see PollingPlaceResolver::isDispatchableAdapter()'s
// docblock) so the dispatch-eligibility check actually engages.

beforeEach(function () {
    config([
        'services.registraduria.live_enabled' => true,
        'services.infovotantes.live_enabled' => false,
        'services.consulta_censo.live_enabled' => false,
    ]);
});

test('startLiveLookup claims a RegistraduriaLiveSession and records the adapter/session_id on success', function () {
    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
        '*/lookup' => Http::response(['session_id' => 'wsp-session-1'], 200),
    ]);

    $resolver = app(PollingPlaceResolver::class);

    $sessionId = $resolver->startLiveLookup('3000000001');

    expect($sessionId)->toBe('wsp-session-1');

    $session = RegistraduriaLiveSession::where('document_number', '3000000001')->first();

    expect($session)->not->toBeNull()
        ->and($session->session_id)->toBe('wsp-session-1')
        ->and($session->adapter_class)->toBe(RegistraduriaService::class);
});

test('startLiveLookup throws RegistraduriaLookupInProgressException and never calls any adapter when a claim already exists', function () {
    RegistraduriaLiveSession::factory()->create([
        'document_number' => '3000000002',
        'expires_at' => now()->addMinutes(5),
    ]);

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
        '*/lookup' => Http::response(['session_id' => 'should-not-be-created'], 200),
    ]);

    $resolver = app(PollingPlaceResolver::class);

    expect(fn () => $resolver->startLiveLookup('3000000002'))
        ->toThrow(RegistraduriaLookupInProgressException::class);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/lookup'));
});

test('resolveAutomated skips the live attempt entirely and falls through to snapshot when a RegistraduriaLiveSession claim already exists', function () {
    $voter = Voter::factory()->create(['polling_place_source' => null, 'document_number' => '3000000003']);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '3000000003',
        'expires_at' => now()->addMinutes(5),
    ]);

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
        '*/lookup' => Http::response(['session_id' => 'should-not-be-created'], 200),
    ]);

    $resolver = app(PollingPlaceResolver::class);

    $result = $resolver->resolveAutomated('3000000003', $voter);

    expect($result)->toBeNull();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/lookup'));

    // The pre-existing claim (simulating another concurrent attempt) is left untouched.
    expect(RegistraduriaLiveSession::where('document_number', '3000000003')->count())->toBe(1);
});

test('attemptLiveAutomated releases the claim without dispatching a collector when the real adapter resolves within the sync window', function () {
    Bus::fake();

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
        '*/lookup' => Http::response(['session_id' => 'wsp-session-4'], 200),
        '*/result/wsp-session-4' => Http::response([
            'status' => 'done',
            'data' => ['raw_message_html' => ''],
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create(['polling_place_source' => null, 'document_number' => '3000000004']);
    $resolver = app(PollingPlaceResolver::class);

    $result = $resolver->resolveAutomated('3000000004', $voter);

    expect($result)->toBeNull()
        ->and(RegistraduriaLiveSession::where('document_number', '3000000004')->count())->toBe(0);

    Bus::assertNotDispatched(CollectRegistraduriaLookupResult::class);
});

test('attemptLiveAutomated keeps the claim alive and dispatches CollectRegistraduriaLookupResult when the real adapter is still pending after exhausting the sync poll window', function () {
    Sleep::fake();
    Bus::fake();

    Http::fake([
        config('services.registraduria.probe_url').'*' => Http::response('', 200),
        '*/lookup' => Http::response(['session_id' => 'wsp-session-5'], 200),
        '*/result/wsp-session-5' => Http::response([
            'status' => 'pending',
            'data' => null,
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create(['polling_place_source' => null, 'document_number' => '3000000005']);
    $resolver = app(PollingPlaceResolver::class);

    $result = $resolver->resolveAutomated('3000000005', $voter);

    expect($result)->toBeNull();

    $session = RegistraduriaLiveSession::where('document_number', '3000000005')->first();

    expect($session)->not->toBeNull()
        ->and($session->session_id)->toBe('wsp-session-5')
        ->and($session->adapter_class)->toBe(RegistraduriaService::class)
        ->and($session->voter_id)->toBe($voter->id)
        ->and($session->campaign_id)->toBe($voter->campaign_id);

    Bus::assertDispatched(CollectRegistraduriaLookupResult::class, fn ($job) => $job->documentNumber === '3000000005');
});

test('releaseLiveSession deletes the claim row for the given cédula, if any', function () {
    RegistraduriaLiveSession::factory()->create(['document_number' => '3000000006']);

    app(PollingPlaceResolver::class)->releaseLiveSession('3000000006');

    expect(RegistraduriaLiveSession::where('document_number', '3000000006')->exists())->toBeFalse();
});

test('releaseLiveSession is a safe no-op when no claim exists for the given cédula', function () {
    app(PollingPlaceResolver::class)->releaseLiveSession('3000000007');

    expect(RegistraduriaLiveSession::count())->toBe(0);
});
