<?php

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\EditVoter;
use App\Models\CensusRecord;
use App\Models\Municipality;
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
