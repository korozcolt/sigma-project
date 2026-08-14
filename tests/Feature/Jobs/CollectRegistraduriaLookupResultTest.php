<?php

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Jobs\CollectRegistraduriaLookupResult;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\RegistraduriaLiveSession;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use App\Services\RegistraduriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// .planning/debug/resolved/2captcha-duplicate-spend.md — the real queue-worker-driven
// collector that recovers an already-paid-for live result the synchronous ~40s cascade
// window gave up on too early, without ever burning a genuine reconciliation_attempts
// bump on an artificial timeout.

beforeEach(function () {
    $this->department = Department::factory()->create(['name' => 'SUCRE']);
    $this->municipality = Municipality::factory()->create(['name' => 'SINCELEJO', 'department_id' => $this->department->id]);
});

test('a done+success result persists the permanent lookup, upgrades the voter to LIVE, resets reconciliation counters, and releases the claim', function () {
    Http::fake([
        '*/result/wsp-session-1' => Http::response([
            'status' => 'done',
            'data' => [
                'puesto_nombre' => 'IE LA CAMPIÑA',
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

    $voter = Voter::factory()->create([
        'document_number' => '4000000001',
        'polling_place_source' => null,
        'status' => VoterStatus::PENDING_REVIEW,
        'reconciliation_attempts' => 3,
    ]);

    $session = RegistraduriaLiveSession::factory()->create([
        'document_number' => '4000000001',
        'session_id' => 'wsp-session-1',
        'adapter_class' => RegistraduriaService::class,
        'voter_id' => $voter->id,
        'campaign_id' => $voter->campaign_id,
        'resolved_via' => 'reconciliation',
    ]);

    (new CollectRegistraduriaLookupResult('4000000001'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    $fresh = $voter->fresh();

    expect($fresh->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($fresh->status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($fresh->reconciliation_attempts)->toBe(0)
        ->and($fresh->reconciliation_exhausted_at)->toBeNull();

    expect(RegistraduriaLookup::where('document_number', '4000000001')->exists())->toBeTrue()
        ->and(RegistraduriaLiveSession::where('document_number', '4000000001')->exists())->toBeFalse();
});

test('a done result with a blank puesto_nombre counts as a genuine failure and bumps reconciliation_attempts', function () {
    Http::fake([
        '*/result/wsp-session-2' => Http::response([
            'status' => 'done',
            'data' => ['raw_message_html' => ''],
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '4000000002',
        'reconciliation_attempts' => 2,
    ]);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '4000000002',
        'session_id' => 'wsp-session-2',
        'adapter_class' => RegistraduriaService::class,
        'voter_id' => $voter->id,
    ]);

    (new CollectRegistraduriaLookupResult('4000000002'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(3)
        ->and(RegistraduriaLiveSession::where('document_number', '4000000002')->exists())->toBeFalse();
});

test('an error status counts as a genuine failure and can reach exhaustion on the 5th consecutive failure', function () {
    Http::fake([
        '*/result/wsp-session-3' => Http::response([
            'status' => 'error',
            'data' => null,
            'error' => 'boom',
        ]),
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '4000000003',
        'reconciliation_attempts' => 4,
    ]);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '4000000003',
        'session_id' => 'wsp-session-3',
        'adapter_class' => RegistraduriaService::class,
        'voter_id' => $voter->id,
    ]);

    (new CollectRegistraduriaLookupResult('4000000003'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(5)
        ->and($fresh->reconciliation_exhausted_at)->not->toBeNull();
});

test('a still-pending result within the collection window re-dispatches itself and keeps the claim alive without bumping attempts', function () {
    Bus::fake();

    Http::fake([
        '*/result/wsp-session-4' => Http::response([
            'status' => 'solving_captcha',
            'data' => null,
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '4000000004',
        'reconciliation_attempts' => 1,
    ]);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '4000000004',
        'session_id' => 'wsp-session-4',
        'adapter_class' => RegistraduriaService::class,
        'voter_id' => $voter->id,
        'expires_at' => now()->addMinutes(5),
    ]);

    (new CollectRegistraduriaLookupResult('4000000004'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(1)
        ->and(RegistraduriaLiveSession::where('document_number', '4000000004')->exists())->toBeTrue();

    Bus::assertDispatched(CollectRegistraduriaLookupResult::class, fn ($job) => $job->documentNumber === '4000000004');
});

test('a still-pending result past the collection window expiry counts as a genuine failure and releases the claim', function () {
    Http::fake([
        '*/result/wsp-session-5' => Http::response([
            'status' => 'pending',
            'data' => null,
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '4000000005',
        'reconciliation_attempts' => 0,
    ]);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '4000000005',
        'session_id' => 'wsp-session-5',
        'adapter_class' => RegistraduriaService::class,
        'voter_id' => $voter->id,
        'expires_at' => now()->subMinute(),
    ]);

    (new CollectRegistraduriaLookupResult('4000000005'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(1)
        ->and(RegistraduriaLiveSession::where('document_number', '4000000005')->exists())->toBeFalse();
});

test('is a safe no-op when no matching claim row exists (already released elsewhere)', function () {
    (new CollectRegistraduriaLookupResult('4000000006'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    expect(RegistraduriaLiveSession::count())->toBe(0);
});

test('deletes the claim without touching any voter when the session has no voter_id (interactive flow with no record yet)', function () {
    Http::fake([
        '*/result/wsp-session-7' => Http::response([
            'status' => 'error',
            'data' => null,
            'error' => 'boom',
        ]),
    ]);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '4000000007',
        'session_id' => 'wsp-session-7',
        'adapter_class' => RegistraduriaService::class,
        'voter_id' => null,
    ]);

    (new CollectRegistraduriaLookupResult('4000000007'))->handle(app(\App\Services\PollingPlaceResolver::class), app(\App\Services\VoterValidationService::class));

    expect(RegistraduriaLiveSession::where('document_number', '4000000007')->exists())->toBeFalse();
});
