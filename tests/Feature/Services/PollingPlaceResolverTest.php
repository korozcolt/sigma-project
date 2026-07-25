<?php

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\Voter;
use App\Services\LiveSourceAdapter;
use App\Services\PollingPlaceResolutionResult;
use App\Services\PollingPlaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

// Test 1
test('resolveFromCampaignCensus returns a DB_RECONSTRUCTION result when a matching CensusRecord exists', function () {
    CensusRecord::factory()->create([
        'document_number' => '1000000001',
        'municipality_code' => $this->municipality->code,
        'polling_station' => $this->pollingPlace->name,
    ]);

    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveFromCampaignCensus('1000000001');

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::DB_RECONSTRUCTION)
        ->and($result->pollingPlaceId)->toBe($this->pollingPlace->id);
});

// Test 2
test('resolveFromCampaignCensus returns null when no CensusRecord exists or polling_station is blank', function () {
    $resolver = new PollingPlaceResolver([]);

    expect($resolver->resolveFromCampaignCensus('9999999999'))->toBeNull();

    CensusRecord::factory()->create([
        'document_number' => '1000000002',
        'municipality_code' => $this->municipality->code,
        'polling_station' => null,
    ]);

    expect($resolver->resolveFromCampaignCensus('1000000002'))->toBeNull();
});

// Test 3
test('resolveFromNationalSnapshot returns a SNAPSHOT result with resolved pollingPlaceId', function () {
    NationalCensusRecord::factory()->create([
        'document_number' => '1000000003',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveFromNationalSnapshot('1000000003');

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and($result->pollingPlaceId)->toBe($this->pollingPlace->id);
});

// Test 4
test('resolveFromNationalSnapshot returns null when no record exists or polling_place_id is null', function () {
    $resolver = new PollingPlaceResolver([]);

    expect($resolver->resolveFromNationalSnapshot('8888888888'))->toBeNull();

    NationalCensusRecord::factory()->create([
        'document_number' => '1000000004',
        'polling_place_id' => null,
    ]);

    expect($resolver->resolveFromNationalSnapshot('1000000004'))->toBeNull();
});

// Test 5
test('isLiveReachable returns true when at least one adapter is reachable, false when all are not', function () {
    $unreachable = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-unreachable';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'error', 'data' => null, 'error' => null];
        }

        public function isReachable(): bool
        {
            return false;
        }
    };

    $reachable = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-reachable';
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

    $resolverWithReachable = new PollingPlaceResolver([$unreachable, $reachable]);
    expect($resolverWithReachable->isLiveReachable())->toBeTrue();

    $resolverAllUnreachable = new PollingPlaceResolver([$unreachable]);
    expect($resolverAllUnreachable->isLiveReachable())->toBeFalse();
});

// Test 6
test('startLiveLookup calls startLookup on the FIRST adapter only, never the second', function () {
    $firstCalled = false;
    $secondCalled = false;

    $first = new class($firstCalled) implements LiveSourceAdapter
    {
        public function __construct(private bool &$called) {}

        public function startLookup(string $cedula): string
        {
            $this->called = true;

            return 'session-first';
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

    $second = new class($secondCalled) implements LiveSourceAdapter
    {
        public function __construct(private bool &$called) {}

        public function startLookup(string $cedula): string
        {
            $this->called = true;

            return 'session-second';
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

    $resolver = new PollingPlaceResolver([$first, $second]);

    $sessionId = $resolver->startLiveLookup('1000000005');

    expect($sessionId)->toBe('session-first')
        ->and($firstCalled)->toBeTrue()
        ->and($secondCalled)->toBeFalse();
});

// Test 7
test('PollingPlaceSource::outranks reflects precedence correctly, including equal precedence', function () {
    expect(PollingPlaceSource::LIVE->outranks(PollingPlaceSource::SNAPSHOT))->toBeTrue()
        ->and(PollingPlaceSource::SNAPSHOT->outranks(PollingPlaceSource::LIVE))->toBeFalse()
        ->and(PollingPlaceSource::LIVE->outranks(PollingPlaceSource::LIVE))->toBeFalse();
});

// Test 8
test('persist blocks an automatic downgrade from LIVE to SNAPSHOT when isExplicitOverride is false', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_resolved_at' => now()->subDay(),
    ]);

    $result = new PollingPlaceResolutionResult(
        source: PollingPlaceSource::SNAPSHOT,
        fields: [],
        pollingPlaceId: $this->pollingPlace->id,
    );

    $resolver = new PollingPlaceResolver([]);

    $return = $resolver->persist($voter, $result, isExplicitOverride: false, resolvedVia: 'reconciliation');

    expect($return)->toBeNull()
        ->and($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and(PollingPlaceResolution::count())->toBe(0);
});

// Test 9
test('persist allows the same downgrade when isExplicitOverride is true', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_resolved_at' => now()->subDay(),
    ]);

    $result = new PollingPlaceResolutionResult(
        source: PollingPlaceSource::SNAPSHOT,
        fields: [],
        pollingPlaceId: $this->pollingPlace->id,
    );

    $resolver = new PollingPlaceResolver([]);

    $return = $resolver->persist($voter, $result, isExplicitOverride: true, resolvedVia: 'interactive');

    expect($return)->toBe($result)
        ->and($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and(PollingPlaceResolution::count())->toBe(1);
});

// Test 10
test('persist refreshes polling_place_resolved_at but writes no audit row on a no-op transition', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'polling_place_resolved_at' => now()->subDay(),
    ]);

    $result = new PollingPlaceResolutionResult(
        source: PollingPlaceSource::SNAPSHOT,
        fields: [],
        pollingPlaceId: $this->pollingPlace->id,
    );

    $resolver = new PollingPlaceResolver([]);

    $before = $voter->polling_place_resolved_at;

    $return = $resolver->persist($voter, $result, isExplicitOverride: false, resolvedVia: 'reconciliation');

    expect($return)->toBe($result)
        ->and($voter->fresh()->polling_place_resolved_at->gt($before))->toBeTrue()
        ->and(PollingPlaceResolution::count())->toBe(0);
});

// Test 11
test('persist creates exactly one audit row with previous_source/new_source on a real transition and no headless actor', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => null,
        'polling_place_resolved_at' => null,
    ]);

    $result = new PollingPlaceResolutionResult(
        source: PollingPlaceSource::SNAPSHOT,
        fields: [],
        pollingPlaceId: $this->pollingPlace->id,
    );

    $resolver = new PollingPlaceResolver([]);

    $return = $resolver->persist($voter, $result, isExplicitOverride: false, resolvedVia: 'reconciliation');

    expect($return)->toBe($result)
        ->and(PollingPlaceResolution::count())->toBe(1);

    $audit = PollingPlaceResolution::first();

    expect($audit->previous_source)->toBeNull()
        ->and($audit->new_source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and($audit->resolved_by)->toBeNull();
});

// Test 12
test('persist is a pass-through returning the given result and writing no audit row when voter is null', function () {
    $result = new PollingPlaceResolutionResult(
        source: PollingPlaceSource::SNAPSHOT,
        fields: [],
        pollingPlaceId: $this->pollingPlace->id,
    );

    $resolver = new PollingPlaceResolver([]);

    $return = $resolver->persist(null, $result, isExplicitOverride: false, resolvedVia: 'interactive');

    expect($return)->toBe($result)
        ->and(PollingPlaceResolution::count())->toBe(0);
});
