<?php

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Jobs\ReconcileFallbackPollingPlaces;
use App\Models\Campaign;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\RegistraduriaLiveSession;
use App\Models\Voter;
use App\Services\LiveSourceAdapter;
use App\Services\PollingPlaceResolver;
use App\Services\VoterValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function bindResolverWithAdapter(?LiveSourceAdapter $adapter = null): void
{
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver($adapter ? [$adapter] : []));
}

function unreachableAdapter(): LiveSourceAdapter
{
    return new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'done', 'data' => [], 'error' => null];
        }

        public function isReachable(): bool
        {
            return false;
        }
    };
}

function liveSuccessAdapter(): LiveSourceAdapter
{
    return new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-live';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'done', 'data' => [
                'puesto_nombre' => 'IE TEST', 'puesto_codigo' => '1', 'zona_codigo' => '1',
                'mesa_numero' => '01', 'departamento' => 'SUCRE', 'municipio' => 'SINCELEJO', 'direccion' => 'CL 1',
            ], 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };
}

// RECON-04: circuit breaker
test('skips the entire run and updates nothing when the live source is unreachable', function () {
    bindResolverWithAdapter(unreachableAdapter());

    $voter = Voter::factory()->create(['polling_place_source' => PollingPlaceSource::SNAPSHOT, 'reconciliation_attempts' => 0]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(0);
});

// RECON-04: per-run cap
test('never processes more than 50 voters in a single run', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'done', 'data' => [], 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };
    bindResolverWithAdapter($adapter);

    $voters = Voter::factory()->count(51)->sequence(fn ($sequence) => [
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'polling_place_resolved_at' => now()->subDays(51 - $sequence->index),
    ])->create();

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    $touched = Voter::whereIn('id', $voters->pluck('id'))->where('reconciliation_attempts', '>', 0)->count();

    expect($touched)->toBe(50);
});

// RECON-01/03: genuine live upgrade resets counters and writes an audit row
test('upgrades a voter to LIVE, resets reconciliation_attempts, and writes an audit row', function () {
    bindResolverWithAdapter(liveSuccessAdapter());

    $department = \App\Models\Department::factory()->create(['name' => 'SUCRE']);
    $municipality = \App\Models\Municipality::factory()->create(['name' => 'SINCELEJO', 'department_id' => $department->id]);

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'reconciliation_attempts' => 2,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    $fresh = $voter->fresh();

    expect($fresh->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($fresh->reconciliation_attempts)->toBe(0)
        ->and($fresh->reconciliation_exhausted_at)->toBeNull()
        ->and($fresh->pollingPlaceResolutions()->count())->toBe(1);
});

// status-polling-place-source-desync: a genuine LIVE upgrade must also sync `status` in
// the same pass, reusing the already-fetched result instead of waiting on the separate
// census:reconcile-validation cron job.
test('syncs status to VERIFIED_CENSUS in the same pass a voter is upgraded to LIVE', function () {
    bindResolverWithAdapter(liveSuccessAdapter());

    \App\Models\Municipality::factory()->create(['name' => 'SINCELEJO']);

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->status)->toBe(VoterStatus::VERIFIED_CENSUS);
});

// status-polling-place-source-desync: the NON_DOWNGRADABLE_STATUSES guard on
// VoterValidationService::updateVoterStatus() must still protect stronger, post-verification
// statuses even when this job also upgrades polling_place_source to LIVE.
test('does not downgrade a non-downgradable status when upgrading polling_place_source to LIVE', function () {
    bindResolverWithAdapter(liveSuccessAdapter());

    \App\Models\Municipality::factory()->create(['name' => 'SINCELEJO']);

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'status' => VoterStatus::CONFIRMED,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->status)->toBe(VoterStatus::CONFIRMED)
        ->and($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::LIVE);
});

// RECON-05: the critical SNAPSHOT-fallthrough-is-a-failure branch
test('counts a SNAPSHOT fallthrough as a failed attempt, never as success', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-captcha';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'waiting_captcha', 'data' => null, 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };
    bindResolverWithAdapter($adapter);

    $pollingPlace = PollingPlace::factory()->create();
    NationalCensusRecord::factory()->create([
        'document_number' => '5000000001',
        'polling_place_id' => $pollingPlace->id,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '5000000001',
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'reconciliation_attempts' => 0,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(1);
});

// RECON-05: terminal exhaustion state after the 5th consecutive failure
test('sets reconciliation_exhausted_at on the 5th consecutive failed attempt', function () {
    // Reachable adapter whose lookup consistently misses (no live data, no snapshot
    // match) — a reachable-but-failed attempt, distinct from the circuit-breaker's
    // unreachable case, since an unreachable adapter would skip the run entirely
    // before ever reaching this voter.
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'done', 'data' => [], 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };
    bindResolverWithAdapter($adapter);

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'reconciliation_attempts' => 4,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(5)
        ->and($fresh->reconciliation_exhausted_at)->not->toBeNull();
});

// RECON-05: exhausted voters are skipped entirely
test('skips a voter whose reconciliation_exhausted_at is already set', function () {
    bindResolverWithAdapter(unreachableAdapter());

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'reconciliation_attempts' => 5,
        'reconciliation_exhausted_at' => now(),
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(5);
});

// RECON-02: no ambient campaign scoping — voters across every campaign are considered
test('processes voters across multiple campaigns without any authenticated/ambient campaign context', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'done', 'data' => [], 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };
    bindResolverWithAdapter($adapter);

    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();

    $voterA = Voter::factory()->create(['campaign_id' => $campaignA->id, 'polling_place_source' => PollingPlaceSource::SNAPSHOT]);
    $voterB = Voter::factory()->create(['campaign_id' => $campaignB->id, 'polling_place_source' => PollingPlaceSource::SNAPSHOT]);

    expect(auth()->check())->toBeFalse();

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voterA->fresh()->reconciliation_attempts)->toBe(1)
        ->and($voterB->fresh()->reconciliation_attempts)->toBe(1);
});

// 2captcha-duplicate-spend: an artificial ~40s sync timeout must never be counted as a
// genuine reconciliation failure when the real (already-paid-for) attempt is still
// being collected in the background — see .planning/debug/resolved/2captcha-duplicate-spend.md
test('does not bump reconciliation_attempts when a RegistraduriaLiveSession claim exists for the voter (pending background collection)', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-should-not-be-called';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'done', 'data' => [], 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };
    bindResolverWithAdapter($adapter);

    $voter = Voter::factory()->create([
        'document_number' => '6000000001',
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'reconciliation_attempts' => 2,
    ]);

    // Simulates a session already claimed elsewhere (this run's own dispatched
    // collector, the sibling census:reconcile-validation cron, or an interactive
    // lookup) — resolveAutomated() will skip live entirely for this cédula (claim
    // fails) and fall through, but that must not count as a genuine failure.
    RegistraduriaLiveSession::factory()->create([
        'document_number' => '6000000001',
        'expires_at' => now()->addMinutes(5),
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(2)
        ->and($voter->fresh()->reconciliation_exhausted_at)->toBeNull();
});

// RECON-06: correctly-unitted lock expiry
test('census:reconcile-live is scheduled hourly with a 10-minute withoutOverlapping lock', function () {
    $consoleRoutes = file_get_contents(base_path('routes/console.php'));

    expect($consoleRoutes)->toContain("Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10)");
});

// Command wiring sanity check
test('census:reconcile-live command dispatches the job', function () {
    Bus::fake();

    Artisan::call('census:reconcile-live');

    Bus::assertDispatched(ReconcileFallbackPollingPlaces::class);
});
