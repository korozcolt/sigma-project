<?php

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Services\LiveSourceAdapter;
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
