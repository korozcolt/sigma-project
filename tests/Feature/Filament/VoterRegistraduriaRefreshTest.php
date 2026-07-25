<?php

use App\Enums\PollingPlaceSource;
use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\EditVoter;
use App\Models\CensusRecord;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\User;
use App\Models\Voter;
use App\Services\RegistraduriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
    Session::put('campaign_context.mode', 'all');
    Session::forget('campaign_context.campaign_id');
});

function createEditableVoter(array $attributes = []): Voter
{
    $municipality = Municipality::factory()->create();

    $coordinator = User::factory()->create(['municipality_id' => $municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->update(['coordinator_user_id' => $coordinator->id]);

    $leader = User::factory()->create([
        'municipality_id' => $municipality->id,
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    return Voter::factory()->create(array_merge([
        'municipality_id' => $municipality->id,
        'registered_by' => $leader->id,
    ], $attributes));
}

// ============ Task 1: forceRefreshFromRegistraduria() bypasses Redis + DB ============

it('forces a fresh 2captcha lookup even when the Redis cache is warm and a CensusRecord already resolves the cedula', function () {
    $cedula = '1102812122';
    $voter = createEditableVoter(['document_number' => $cedula]);

    // Layer 1 (Redis) warm
    Cache::put("registraduria:cedula:{$cedula}", [
        'puesto_nombre' => 'CACHED PLACE',
        'puesto_codigo' => '01',
        'zona_codigo' => '01',
        'mesa_numero' => '001',
        'departamento' => 'SUCRE',
        'municipio' => $voter->municipality->name,
        'direccion' => 'Calle Falsa 123',
    ], now()->addDays(30));

    // Layer 2 (DB reconstruction) also would succeed
    CensusRecord::factory()->create([
        'document_number' => $cedula,
        'polling_station' => 'CENSUS PLACE',
        'municipality_code' => $voter->municipality->code,
    ]);

    $this->mock(RegistraduriaService::class, function ($mock) use ($cedula) {
        $mock->shouldReceive('startLookup')
            ->once()
            ->with($cedula)
            ->andReturn('session-xyz');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('forceRefreshFromRegistraduria', $cedula)
        ->assertSet('registraduriaSessionId', 'session-xyz')
        ->assertSet('registraduriaOpen', true);
});

it('does not call the service and shows a warning when cedula is blank', function () {
    $voter = createEditableVoter();

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldNotReceive('startLookup');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('forceRefreshFromRegistraduria', '')
        ->assertSet('registraduriaOpen', false);
});

// ============ Task 2: secondary suffixAction wiring ============

it('hides the secondary refresh action until a polling place is resolved', function () {
    $voter = createEditableVoter(['polling_place_id' => null]);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertFormComponentActionExists('document_number', 'actualizar_registraduria')
        ->assertFormComponentActionHidden('document_number', 'actualizar_registraduria');
});

it('shows the secondary refresh action once a polling place is resolved and invokes forceRefreshFromRegistraduria', function () {
    $cedula = '1102815878';
    $voter = createEditableVoter(['document_number' => $cedula]);
    $pollingPlace = \App\Models\PollingPlace::factory()->create([
        'municipality_id' => $voter->municipality_id,
    ]);
    $voter->update(['polling_place_id' => $pollingPlace->id]);

    $this->mock(RegistraduriaService::class, function ($mock) use ($cedula) {
        $mock->shouldReceive('startLookup')
            ->once()
            ->with($cedula)
            ->andReturn('session-abc');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertFormComponentActionVisible('document_number', 'actualizar_registraduria')
        ->callFormComponentAction('document_number', 'actualizar_registraduria')
        ->assertSet('registraduriaSessionId', 'session-abc');
});

// ============ Task 2: PollingPlaceResolver-backed cascade (reorder, snapshot fallback, no-downgrade guard) ============

it('opens the live modal instead of resolving from DB when live is reachable, even though DB resolution would have succeeded', function () {
    $cedula = '1102816001';
    $voter = createEditableVoter(['document_number' => $cedula]);

    CensusRecord::factory()->create([
        'document_number' => $cedula,
        'polling_station' => 'CENSUS PLACE A',
        'municipality_code' => $voter->municipality->code,
    ]);

    $this->mock(RegistraduriaService::class, function ($mock) use ($cedula) {
        $mock->shouldReceive('isReachable')->andReturn(true);
        $mock->shouldReceive('startLookup')
            ->once()
            ->with($cedula)
            ->andReturn('session-live-first');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('openRegistraduriaBrowser', $cedula)
        ->assertSet('registraduriaOpen', true)
        ->assertSet('registraduriaSessionId', 'session-live-first');

    expect($voter->fresh()->polling_place_source)->toBeNull();
});

it('resolves from DB reconstruction without opening the modal when live is unreachable and a matching CensusRecord exists', function () {
    $cedula = '1102816002';
    $voter = createEditableVoter(['document_number' => $cedula]);

    CensusRecord::factory()->create([
        'document_number' => $cedula,
        'polling_station' => 'CENSUS PLACE B',
        'municipality_code' => $voter->municipality->code,
    ]);

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldReceive('isReachable')->andReturn(false);
        $mock->shouldNotReceive('startLookup');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('openRegistraduriaBrowser', $cedula)
        ->assertSet('registraduriaOpen', false)
        ->assertNotified('Puesto de votación (desde base de datos)');

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::DB_RECONSTRUCTION);
});

it('resolves from the national snapshot when live is unreachable, no CensusRecord exists, but a NationalCensusRecord does', function () {
    $cedula = '1102816003';
    $voter = createEditableVoter(['document_number' => $cedula]);

    $pollingPlace = PollingPlace::factory()->create([
        'municipality_id' => $voter->municipality_id,
    ]);

    NationalCensusRecord::factory()->create([
        'document_number' => $cedula,
        'polling_place_id' => $pollingPlace->id,
    ]);

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldReceive('isReachable')->andReturn(false);
        $mock->shouldNotReceive('startLookup');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('openRegistraduriaBrowser', $cedula)
        ->assertSet('registraduriaOpen', false)
        ->assertNotified('Puesto de votación (snapshot nacional)');

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::SNAPSHOT);
});

it('never downgrades an already-LIVE voter through the ordinary Save button when the DB-reconstruction tier resolves a different place', function () {
    $cedula = '1102816004';

    $existingMunicipality = Municipality::factory()->create();
    $existingPollingPlace = PollingPlace::factory()->create([
        'municipality_id' => $existingMunicipality->id,
    ]);

    $voter = createEditableVoter([
        'document_number' => $cedula,
        'municipality_id' => $existingMunicipality->id,
        'polling_place_id' => $existingPollingPlace->id,
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_resolved_at' => now()->subDay(),
    ]);

    $otherMunicipality = Municipality::factory()->create();
    $otherPollingPlace = PollingPlace::factory()->create([
        'municipality_id' => $otherMunicipality->id,
    ]);

    CensusRecord::factory()->create([
        'document_number' => $cedula,
        'polling_station' => $otherPollingPlace->name,
        'municipality_code' => $otherMunicipality->code,
    ]);

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldReceive('isReachable')->andReturn(false);
        $mock->shouldNotReceive('startLookup');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('openRegistraduriaBrowser', $cedula)
        ->call('save');

    $voter->refresh();

    expect($voter->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($voter->polling_place_id)->toBe($existingPollingPlace->id)
        ->and(PollingPlaceResolution::count())->toBe(0);
});

it('forceRefreshFromRegistraduria still calls startLookup on an already-LIVE voter (bypasses the no-downgrade guard per D-10)', function () {
    $cedula = '1102816005';
    $existingPollingPlace = PollingPlace::factory()->create();

    $voter = createEditableVoter([
        'document_number' => $cedula,
        'municipality_id' => $existingPollingPlace->municipality_id,
        'polling_place_id' => $existingPollingPlace->id,
        'polling_place_source' => PollingPlaceSource::LIVE,
        'polling_place_resolved_at' => now()->subDay(),
    ]);

    config(['services.registraduria.live_enabled' => true]);

    $this->mock(RegistraduriaService::class, function ($mock) use ($cedula) {
        $mock->shouldReceive('startLookup')
            ->once()
            ->with($cedula)
            ->andReturn('session-force-live');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('forceRefreshFromRegistraduria', $cedula)
        ->assertSet('registraduriaSessionId', 'session-force-live')
        ->assertSet('registraduriaOpen', true);
});

it('forceRefreshFromRegistraduria shows the disabled warning and never calls startLookup when the live kill switch is off', function () {
    $cedula = '1102816006';
    $voter = createEditableVoter(['document_number' => $cedula]);

    config(['services.registraduria.live_enabled' => false]);

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldNotReceive('startLookup');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('forceRefreshFromRegistraduria', $cedula)
        ->assertSet('registraduriaOpen', false)
        ->assertNotified('Servicio en vivo deshabilitado');
});

it('never mislabels a DB-reconstruction result as LIVE on a later lookup via a stale cache hit', function () {
    $cedula = '1102816007';
    $voter = createEditableVoter(['document_number' => $cedula]);

    CensusRecord::factory()->create([
        'document_number' => $cedula,
        'polling_station' => 'CENSUS PLACE G',
        'municipality_code' => $voter->municipality->code,
    ]);

    $this->mock(RegistraduriaService::class, function ($mock) {
        $mock->shouldReceive('isReachable')->andReturn(false);
        $mock->shouldNotReceive('startLookup');
    });

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('openRegistraduriaBrowser', $cedula)
        ->assertNotified('Puesto de votación (desde base de datos)');

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::DB_RECONSTRUCTION)
        ->and(Cache::get("registraduria:cedula:{$cedula}"))->toBeNull();

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->call('openRegistraduriaBrowser', $cedula)
        ->assertNotified('Puesto de votación (desde base de datos)');

    expect($voter->fresh()->polling_place_source)->toBe(PollingPlaceSource::DB_RECONSTRUCTION);
});

// ============ D-06: role gate on the "actualizar_registraduria" (force-refresh) suffixAction ============

it('shows the "actualizar_registraduria" action for admin_campaign, coordinator, and super_admin roles', function (string $role) {
    $voter = createEditableVoter();
    $pollingPlace = PollingPlace::factory()->create(['municipality_id' => $voter->municipality_id]);
    $voter->update(['polling_place_id' => $pollingPlace->id]);

    $user = User::factory()->create();
    $user->assignRole($role);
    actingAs($user);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertFormComponentActionVisible('document_number', 'actualizar_registraduria');
})->with([
    UserRole::ADMIN_CAMPAIGN->value,
    UserRole::COORDINATOR->value,
    UserRole::SUPER_ADMIN->value,
]);

it('hides the "actualizar_registraduria" action for leader and reviewer roles even when a polling place is resolved', function (string $role) {
    $voter = createEditableVoter();
    $pollingPlace = PollingPlace::factory()->create(['municipality_id' => $voter->municipality_id]);
    $voter->update(['polling_place_id' => $pollingPlace->id]);

    $user = User::factory()->create();
    $user->assignRole($role);
    actingAs($user);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertFormComponentActionHidden('document_number', 'actualizar_registraduria');
})->with([
    UserRole::LEADER->value,
    UserRole::REVIEWER->value,
]);

// ============ D-01/SRC-04: the original "consultar_registraduria" manual re-check stays available to every role ============

it('keeps the "consultar_registraduria" lookup action visible for leader and reviewer roles regardless of the D-06 role gate', function (string $role) {
    $voter = createEditableVoter();

    $user = User::factory()->create();
    $user->assignRole($role);
    actingAs($user);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertFormComponentActionVisible('document_number', 'consultar_registraduria');
})->with([
    UserRole::LEADER->value,
    UserRole::REVIEWER->value,
]);
