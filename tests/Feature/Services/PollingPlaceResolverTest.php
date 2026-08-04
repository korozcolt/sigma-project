<?php

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use App\Services\LiveSourceAdapter;
use App\Services\PollingPlaceResolutionResult;
use App\Services\PollingPlaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;

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
test('startLiveLookup calls startLookup on the first REACHABLE adapter, never a later one, when the first is reachable', function () {
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

// Test 6b
test('startLiveLookup skips an unreachable first adapter and uses the next reachable one', function () {
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
            return false;
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

    expect($sessionId)->toBe('session-second')
        ->and($firstCalled)->toBeFalse()
        ->and($secondCalled)->toBeTrue();
});

// Test 6c
test('startLiveLookup throws when no configured adapter is reachable', function () {
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

    $resolver = new PollingPlaceResolver([$unreachable]);

    expect(fn () => $resolver->startLiveLookup('1000000005'))
        ->toThrow(\RuntimeException::class, 'No live source adapters configured.');
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

// Test 13
test('resolveAutomated gives up immediately without sleeping on waiting_captcha and falls back to snapshot', function () {
    Sleep::fake();

    NationalCensusRecord::factory()->create([
        'document_number' => '1000000013',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $calls = 0;

    $adapter = new class($calls) implements LiveSourceAdapter
    {
        public function __construct(private int &$calls) {}

        public function startLookup(string $cedula): string
        {
            return 'session-captcha';
        }

        public function getResult(string $sessionId): array
        {
            $this->calls++;

            return ['status' => 'waiting_captcha', 'data' => null, 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000013', $voter);

    expect($calls)->toBe(1)
        ->and($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT);

    Sleep::assertNeverSlept();
});

// Test 14
test('resolveAutomated polls 9 times, sleeps 8 times, then falls back to snapshot when always pending', function () {
    Sleep::fake();

    NationalCensusRecord::factory()->create([
        'document_number' => '1000000014',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $calls = 0;

    $adapter = new class($calls) implements LiveSourceAdapter
    {
        public function __construct(private int &$calls) {}

        public function startLookup(string $cedula): string
        {
            return 'session-pending';
        }

        public function getResult(string $sessionId): array
        {
            $this->calls++;

            return ['status' => 'pending', 'data' => null, 'error' => null];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000014', $voter);

    expect($calls)->toBe(9)
        ->and($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT);

    Sleep::assertSleptTimes(8);
});

// Test 15
test('resolveAutomated returns a LIVE-sourced, persisted result immediately when getResult is done on the first call', function () {
    Sleep::fake();

    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-done';
        }

        public function getResult(string $sessionId): array
        {
            return [
                'status' => 'done',
                'data' => [
                    'puesto_nombre' => 'IE LA CAMPIÑA',
                    'puesto_codigo' => '1',
                    'zona_codigo' => '1',
                    'mesa_numero' => '05',
                    'departamento' => 'SUCRE',
                    'municipio' => 'SINCELEJO',
                    'direccion' => 'CALLE FALSA 123',
                ],
                'error' => null,
            ];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000015', $voter);

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::LIVE)
        ->and($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::LIVE);

    Sleep::assertNeverSlept();
});

// Test 16
test('resolveAutomated skips straight to the snapshot tier without calling startLookup when unreachable', function () {
    NationalCensusRecord::factory()->create([
        'document_number' => '1000000016',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $startLookupCalled = false;

    $adapter = new class($startLookupCalled) implements LiveSourceAdapter
    {
        public function __construct(private bool &$startLookupCalled) {}

        public function startLookup(string $cedula): string
        {
            $this->startLookupCalled = true;

            return 'session-unreachable';
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

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000016', $voter);

    expect($startLookupCalled)->toBeFalse()
        ->and($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT);
});

// Test 17
test('resolveAutomated returns null on a total miss with no exception when live is unreachable and no snapshot exists', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-miss';
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

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('9999999917', $voter);

    expect($result)->toBeNull();
});

// ============ Regression: blank puesto_codigo/zona_codigo from a REAL live Registraduría
// response (RegistraduriaService::parseConsultaHtml() never gets a CODIGO PUESTO/ZONA
// column, so these are always '' in practice — see
// .planning/debug/resolved/registraduria-interactive-result-not-parsed.md) ============

// Test 18
test('resolveAutomated creates a codeless PollingPlace (null zone_code/place_code) without throwing when the live adapter returns blank puesto_codigo/zona_codigo', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-blank-codes';
        }

        public function getResult(string $sessionId): array
        {
            return [
                'status' => 'done',
                'data' => [
                    'puesto_nombre' => 'IE SAN JOSE C I P',
                    'puesto_codigo' => '',
                    'zona_codigo' => '',
                    'mesa_numero' => '05',
                    'departamento' => 'SUCRE',
                    'municipio' => 'SINCELEJO',
                    'direccion' => 'CL 22 No. 10A-380',
                ],
                'error' => null,
            ];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };

    $voter = Voter::factory()->create(['polling_place_source' => null, 'municipality_id' => $this->municipality->id]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000018', $voter);

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::LIVE)
        ->and($result->pollingPlaceId)->not->toBeNull();

    $created = PollingPlace::find($result->pollingPlaceId);

    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('IE SAN JOSE C I P')
        ->and($created->zone_code)->toBeNull()
        ->and($created->place_code)->toBeNull()
        ->and($created->municipality_id)->toBe($this->municipality->id);
});

// Test 19
test('resolveOrCreatePollingPlace matches an existing codeless PollingPlace by name instead of creating a duplicate', function () {
    $resolver = new PollingPlaceResolver([]);

    $fields = [
        'puesto_nombre' => 'IE SAN JOSE C I P',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => 'SUCRE',
        'municipio' => $this->municipality->name,
        'direccion' => 'CL 22 No. 10A-380',
    ];

    $first = $resolver->resolveOrCreatePollingPlace($fields);
    $second = $resolver->resolveOrCreatePollingPlace($fields);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second->id)->toBe($first->id);
});

// Test 20
test('resolveOrCreatePollingPlace creates a separate codeless PollingPlace for a different-named puesto in the same municipality', function () {
    $resolver = new PollingPlaceResolver([]);

    $first = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => 'IE SAN JOSE C I P',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'municipio' => $this->municipality->name,
        'direccion' => 'CL 22 No. 10A-380',
    ]);

    $second = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => 'IE NORMAL SUPERIOR',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'municipio' => $this->municipality->name,
        'direccion' => 'CALLE 30 No. 5-10',
    ]);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second->id)->not->toBe($first->id)
        ->and($second->name)->toBe('IE NORMAL SUPERIOR');
});

// ============ Permanent registraduria_lookups table (quick task 260726-jao) ============

// Test 21
test('resolveFromPermanentLookup returns null when no matching row exists', function () {
    $resolver = new PollingPlaceResolver([]);

    expect($resolver->resolveFromPermanentLookup('1000000021'))->toBeNull();
});

// Test 22
test('resolveFromPermanentLookup returns a LIVE-sourced result with resolved pollingPlaceId when a matching row exists', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000022',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveFromPermanentLookup('1000000022');

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::LIVE)
        ->and($result->pollingPlaceId)->toBe($this->pollingPlace->id);
});

// Test 23
test('persistPermanentLookup creates a new row when none exists for that document_number', function () {
    $resolver = new PollingPlaceResolver([]);

    expect(RegistraduriaLookup::count())->toBe(0);

    $resolver->persistPermanentLookup('1000000023', [
        'puesto_nombre' => 'IE LA CAMPIÑA',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => 'SUCRE',
        'municipio' => 'SINCELEJO',
        'direccion' => 'CALLE FALSA 123',
    ]);

    expect(RegistraduriaLookup::count())->toBe(1)
        ->and(RegistraduriaLookup::first()->document_number)->toBe('1000000023');
});

// Test 24
test('persistPermanentLookup upserts on a second call for the same cédula without creating a duplicate row', function () {
    $resolver = new PollingPlaceResolver([]);

    $resolver->persistPermanentLookup('1000000024', [
        'puesto_nombre' => 'IE ORIGINAL',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '01',
        'departamento' => 'SUCRE',
        'municipio' => 'SINCELEJO',
        'direccion' => 'DIRECCION ORIGINAL',
    ]);

    $resolver->persistPermanentLookup('1000000024', [
        'puesto_nombre' => 'IE ACTUALIZADA',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '02',
        'departamento' => 'SUCRE',
        'municipio' => 'SINCELEJO',
        'direccion' => 'DIRECCION ACTUALIZADA',
    ]);

    expect(RegistraduriaLookup::count())->toBe(1);

    $lookup = RegistraduriaLookup::where('document_number', '1000000024')->first();

    expect($lookup->puesto_nombre)->toBe('IE ACTUALIZADA')
        ->and($lookup->mesa_numero)->toBe('02');
});

// Test 25
test('resolveAutomated returns the permanent-table result without calling startLookup on any live adapter when a matching row already exists', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000025',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    $startLookupCalled = false;

    $adapter = new class($startLookupCalled) implements LiveSourceAdapter
    {
        public function __construct(private bool &$startLookupCalled) {}

        public function startLookup(string $cedula): string
        {
            $this->startLookupCalled = true;

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

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000025', $voter);

    expect($startLookupCalled)->toBeFalse()
        ->and($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::LIVE)
        ->and($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::LIVE);
});

// Test 26
test('resolveAutomated persists a fresh live success into the permanent lookup table when no row existed beforehand', function () {
    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-fresh-live';
        }

        public function getResult(string $sessionId): array
        {
            return [
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
            ];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    expect(RegistraduriaLookup::count())->toBe(0);

    $result = $resolver->resolveAutomated('1000000026', $voter);

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::LIVE);

    $lookup = RegistraduriaLookup::where('document_number', '1000000026')->first();

    expect($lookup)->not->toBeNull()
        ->and($lookup->puesto_nombre)->toBe('IE LA CAMPIÑA')
        ->and($lookup->mesa_numero)->toBe('05')
        ->and($lookup->campaign_id)->toBe($voter->campaign_id);
});

// ============ max_tables derived from mesa_numero (quick task 260726-k80) ============

// Test 27
test('resolveOrCreatePollingPlace sets max_tables from mesa_numero when creating a NEW PollingPlace via the DIVIPOLE-code branch', function () {
    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => 'IE NUEVA CON CODIGO',
        'puesto_codigo' => '2',
        'zona_codigo' => '2',
        'mesa_numero' => '13',
        'municipio' => $this->municipality->name,
        'direccion' => 'CALLE NUEVA 1',
    ]);

    expect($result)->not->toBeNull()
        ->and($result->max_tables)->toBe(13);
});

// Test 28
test('resolveOrCreatePollingPlace sets max_tables from mesa_numero when creating a NEW PollingPlace via the name-match branch', function () {
    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => 'IE NUEVA SIN CODIGO',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '13',
        'municipio' => $this->municipality->name,
        'direccion' => 'CALLE NUEVA 2',
    ]);

    expect($result)->not->toBeNull()
        ->and($result->max_tables)->toBe(13);
});

// Test 29
test('resolveOrCreatePollingPlace bumps max_tables up when matching an EXISTING PollingPlace whose current value is lower than the incoming mesa_numero', function () {
    $this->pollingPlace->update(['max_tables' => 5]);

    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => (string) $this->pollingPlace->place_code,
        'zona_codigo' => (string) $this->pollingPlace->zone_code,
        'mesa_numero' => '13',
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($this->pollingPlace->id)
        ->and($result->max_tables)->toBe(13)
        ->and($this->pollingPlace->fresh()->max_tables)->toBe(13);
});

// Test 30
test('resolveOrCreatePollingPlace does not downgrade max_tables when matching an EXISTING PollingPlace whose current value already covers the incoming mesa_numero', function () {
    $this->pollingPlace->update(['max_tables' => 20]);

    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => (string) $this->pollingPlace->place_code,
        'zona_codigo' => (string) $this->pollingPlace->zone_code,
        'mesa_numero' => '13',
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($this->pollingPlace->id)
        ->and($result->max_tables)->toBe(20)
        ->and($this->pollingPlace->fresh()->max_tables)->toBe(20);
});

// Test 31
test('resolveOrCreatePollingPlace still sets max_tables to 0 for a newly created PollingPlace when no mesa_numero is present', function () {
    $resolver = new PollingPlaceResolver([]);

    $result = $resolver->resolveOrCreatePollingPlace([
        'puesto_nombre' => 'IE SIN MESA',
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'municipio' => $this->municipality->name,
        'direccion' => 'CALLE SIN MESA',
    ]);

    expect($result)->not->toBeNull()
        ->and($result->max_tables)->toBe(0);
});

// ============ Cascade-by-availability, not cascade-by-result (quick task 260804-gl5) ============

// Test 32
test('resolveAutomated does NOT call a second live adapter startLookup when the first REACHABLE adapter exhausts all polls without reaching done', function () {
    Sleep::fake();

    NationalCensusRecord::factory()->create([
        'document_number' => '1000000032',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $secondCalled = false;

    $first = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-first-pending';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'pending', 'data' => null, 'error' => null];
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

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$first, $second]);

    $result = $resolver->resolveAutomated('1000000032', $voter);

    expect($secondCalled)->toBeFalse()
        ->and($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and(RegistraduriaLookup::count())->toBe(0);
});

// Test 33
test('resolveAutomated does NOT call a second live adapter startLookup when the first REACHABLE adapter returns status=error', function () {
    NationalCensusRecord::factory()->create([
        'document_number' => '1000000033',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $secondCalled = false;

    $first = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-first-error';
        }

        public function getResult(string $sessionId): array
        {
            return ['status' => 'error', 'data' => null, 'error' => 'boom'];
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

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$first, $second]);

    $result = $resolver->resolveAutomated('1000000033', $voter);

    expect($secondCalled)->toBeFalse()
        ->and($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and(RegistraduriaLookup::count())->toBe(0);
});

// Test 34
test('resolveAutomated treats a status=done result with a blank puesto_nombre as non-success and falls back to snapshot', function () {
    NationalCensusRecord::factory()->create([
        'document_number' => '1000000034',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $adapter = new class implements LiveSourceAdapter
    {
        public function startLookup(string $cedula): string
        {
            return 'session-blank-done';
        }

        public function getResult(string $sessionId): array
        {
            // Reproduces the pre-fix infovotantes "not found" bug shape: status=done
            // with all 7 fields as blank strings, no distinct not_found outcome.
            return [
                'status' => 'done',
                'data' => [
                    'puesto_nombre' => '',
                    'puesto_codigo' => '',
                    'zona_codigo' => '',
                    'mesa_numero' => '',
                    'departamento' => '',
                    'municipio' => '',
                    'direccion' => '',
                ],
                'error' => null,
            ];
        }

        public function isReachable(): bool
        {
            return true;
        }
    };

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = new PollingPlaceResolver([$adapter]);

    $result = $resolver->resolveAutomated('1000000034', $voter);

    expect($result)->not->toBeNull()
        ->and($result->source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::SNAPSHOT)
        ->and(RegistraduriaLookup::count())->toBe(0);
});
