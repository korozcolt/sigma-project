<?php

declare(strict_types=1);

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Jobs\DispatchCensusRevalidation;
use App\Jobs\ReconcileFallbackPollingPlaces;
use App\Models\RevalidationRun;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\LiveSourceAdapter;
use App\Services\PollingPlaceResolver;
use App\Services\VoterValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindEmptyResolver(): void
{
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver([]));
}

// ============ DispatchCensusRevalidation: widened selection + RevalidationRun tracking ============

test('DispatchCensusRevalidation selects a voter with VERIFIED_CENSUS status but polling_place_source IS NULL', function () {
    bindEmptyResolver();

    $voter = Voter::factory()->create([
        'status' => VoterStatus::VERIFIED_CENSUS,
        'polling_place_source' => null,
    ]);

    (new DispatchCensusRevalidation)->handle();

    $run = RevalidationRun::latest()->first();

    expect($run->total)->toBe(1)
        ->and(ValidationHistory::where('voter_id', $voter->id)->exists())->toBeTrue();
});

test('DispatchCensusRevalidation selects PENDING_REVIEW, CENSUS_NOT_FOUND, and REJECTED_CENSUS voters (already-rejected voters re-enter)', function () {
    bindEmptyResolver();

    $pending = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW, 'polling_place_source' => PollingPlaceSource::LIVE]);
    $notFound = Voter::factory()->create(['status' => VoterStatus::CENSUS_NOT_FOUND, 'polling_place_source' => PollingPlaceSource::LIVE]);
    $rejected = Voter::factory()->create(['status' => VoterStatus::REJECTED_CENSUS, 'polling_place_source' => PollingPlaceSource::LIVE]);

    (new DispatchCensusRevalidation)->handle();

    $run = RevalidationRun::latest()->first();

    expect($run->total)->toBe(3);

    expect(ValidationHistory::where('voter_id', $pending->id)->exists())->toBeTrue()
        ->and(ValidationHistory::where('voter_id', $notFound->id)->exists())->toBeTrue()
        ->and(ValidationHistory::where('voter_id', $rejected->id)->exists())->toBeTrue();

    // Unresolvable in this test's fixture (no adapters, no seeded records) — the
    // already-rejected voter lands on the re-triable CENSUS_NOT_FOUND, never a
    // re-produced dead-end REJECTED_CENSUS.
    expect($rejected->fresh()->status)->toBe(VoterStatus::CENSUS_NOT_FOUND);
});

test('DispatchCensusRevalidation does NOT re-touch a voter already VERIFIED_CENSUS with polling_place_source = LIVE', function () {
    bindEmptyResolver();

    $settled = Voter::factory()->create([
        'status' => VoterStatus::VERIFIED_CENSUS,
        'polling_place_source' => PollingPlaceSource::LIVE,
    ]);

    (new DispatchCensusRevalidation)->handle();

    $run = RevalidationRun::latest()->first();

    expect($run->total)->toBe(0)
        ->and(ValidationHistory::where('voter_id', $settled->id)->exists())->toBeFalse();
});

test('after DispatchCensusRevalidation runs, a RevalidationRun row exists with finished_at set, total = selected count, processed = total', function () {
    bindEmptyResolver();

    Voter::factory()->count(3)->create(['status' => VoterStatus::PENDING_REVIEW]);

    (new DispatchCensusRevalidation)->handle();

    $run = RevalidationRun::latest()->first();

    expect($run)->not->toBeNull()
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->total)->toBe(3)
        ->and($run->processed)->toBe(3)
        ->and($run->changed)->toBeGreaterThanOrEqual(0);
});

test('a single DispatchCensusRevalidation pass resolves both census status AND polling_place_source together', function () {
    // Real cascade tier: permanent registraduria_lookups cache (no live adapters needed).
    // Municipality/PollingPlace must genuinely match the lookup's municipio (apoyo-marcado-
    // en-vivo-con-puesto-sin-resolver: persist() no longer fakes polling_place_source=LIVE
    // when the municipality can't be matched, so this test needs a real match to still
    // exercise "both resolve together" rather than accidentally relying on a random,
    // unmatched Faker city name).
    $department = \App\Models\Department::factory()->create(['name' => 'SUCRE']);
    $municipality = \App\Models\Municipality::factory()->create(['name' => 'SINCELEJO', 'department_id' => $department->id]);
    \App\Models\PollingPlace::factory()->create([
        'department_id' => $department->id,
        'municipality_id' => $municipality->id,
        'name' => 'IE LA CAMPIÑA',
    ]);

    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    \App\Models\RegistraduriaLookup::factory()->create([
        'document_number' => $voter->document_number,
        'puesto_nombre' => 'IE LA CAMPIÑA',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'departamento' => 'SUCRE',
        'municipio' => 'SINCELEJO',
    ]);

    (new DispatchCensusRevalidation)->handle();

    $fresh = $voter->fresh();

    expect($fresh->status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($fresh->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($fresh->polling_place_id)->not->toBeNull();
});

// ============ ReconcileFallbackPollingPlaces: whereNotNull guard dropped ============

test('ReconcileFallbackPollingPlaces selects a voter with polling_place_source IS NULL for first-time resolution', function () {
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
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver([$adapter]));

    $voter = Voter::factory()->create([
        'polling_place_source' => null,
        'reconciliation_attempts' => 0,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    // Selected and attempted (reachable adapter's miss increments the failure counter) —
    // proving it was NOT skipped by the (now-removed) whereNotNull guard.
    expect($voter->fresh()->reconciliation_attempts)->toBe(1);
});

test('ReconcileFallbackPollingPlaces still upgrades a non-LIVE-tier voter (whereNotNull removal does not break the existing upgrade path)', function () {
    $adapter = new class implements LiveSourceAdapter
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
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver([$adapter]));

    \App\Models\Department::factory()->create(['name' => 'SUCRE']);
    \App\Models\Municipality::factory()->create(['name' => 'SINCELEJO']);

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'reconciliation_attempts' => 1,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($voter->fresh()->reconciliation_attempts)->toBe(0);
});

test('ReconcileFallbackPollingPlaces still skips a reconciliation_exhausted_at voter, even with polling_place_source NULL', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => null,
        'reconciliation_attempts' => 5,
        'reconciliation_exhausted_at' => now(),
    ]);

    // Force reachability true so the circuit breaker doesn't skip the whole run for an
    // unrelated reason — use a reachable adapter that simply never resolves.
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
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver([$adapter]));

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(5);
});

test('ReconcileFallbackPollingPlaces still skips a LIVE-source voter', function () {
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
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver([$adapter]));

    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'reconciliation_attempts' => 0,
    ]);

    (new ReconcileFallbackPollingPlaces)->handle(app(PollingPlaceResolver::class), app(VoterValidationService::class));

    expect($voter->fresh()->reconciliation_attempts)->toBe(0);
});
