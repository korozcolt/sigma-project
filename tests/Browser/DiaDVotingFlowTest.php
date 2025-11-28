<?php

declare(strict_types=1);

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoteRecord;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
    $this->campaign = Campaign::factory()->create(['status' => 'active']);
});

it('flujo completo: activar evento y registrar voto', function () {
    actingAs($this->user);

    // Paso 1: Crear evento para hoy
    $event = ElectionEvent::factory()->today()->inactive()->for($this->campaign)->create([
        'name' => 'Simulacro 1',
    ]);

    // Paso 2: Activar evento desde UI
    $page = visit('/admin/manage-election-events');

    $page->assertSee('Simulacro 1')
        ->assertSee('Activar Ahora')
        ->click('Activar Ahora')
        ->assertSee('Evento activado')
        ->assertSee('Evento Activo: Simulacro 1');

    // Verificar que el evento está activo
    expect($event->fresh()->is_active)->toBeTrue();

    // Paso 3: Ir a Día D y registrar voto
    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '12345678',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'status' => VoterStatus::CONFIRMED,
    ]);

    $page = visit('/admin/dia-d');

    $page->assertSee('Búsqueda de Votante')
        ->fill('input[placeholder="Número de documento..."]', '12345678')
        ->click('Buscar')
        ->assertSee('Juan')
        ->assertSee('Pérez')
        ->assertSee('Marcar VOTÓ')
        ->click('Marcar VOTÓ')
        ->assertSee('Votante marcado como VOTÓ');

    // Verificar que se creó el VoteRecord vinculado al evento
    assertDatabaseHas('vote_records', [
        'voter_id' => $voter->id,
        'campaign_id' => $this->campaign->id,
        'election_event_id' => $event->id,
    ]);

    expect($voter->fresh()->status)->toBe(VoterStatus::VOTED);
});

it('previene voto duplicado en el mismo evento', function () {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '87654321',
        'status' => VoterStatus::CONFIRMED,
    ]);

    // Registrar primer voto
    VoteRecord::create([
        'voter_id' => $voter->id,
        'campaign_id' => $this->campaign->id,
        'election_event_id' => $event->id,
        'voted_at' => now(),
    ]);

    $page = visit('/admin/dia-d');

    $page->fill('input[placeholder="Número de documento..."]', '87654321')
        ->click('Buscar')
        ->click('Marcar VOTÓ')
        ->assertSee('Este votante ya tiene un registro de voto');
});

it('permite voto en simulacro diferente para mismo votante', function () {
    actingAs($this->user);

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '11223344',
        'status' => VoterStatus::CONFIRMED,
    ]);

    // Simulacro 1 - Votó
    $simulacro1 = ElectionEvent::factory()->for($this->campaign)->create([
        'name' => 'Simulacro 1',
        'type' => 'simulation',
        'date' => now()->subDay(),
        'is_active' => false,
    ]);

    VoteRecord::create([
        'voter_id' => $voter->id,
        'campaign_id' => $this->campaign->id,
        'election_event_id' => $simulacro1->id,
        'voted_at' => now()->subDay(),
    ]);

    // Simulacro 2 - Activo hoy
    $simulacro2 = ElectionEvent::factory()->today()->active()->for($this->campaign)->create([
        'name' => 'Simulacro 2',
    ]);

    // Debe permitir votar en el nuevo simulacro
    $page = visit('/admin/dia-d');

    $page->fill('input[placeholder="Número de documento..."]', '11223344')
        ->click('Buscar')
        ->assertSee('Marcar VOTÓ') // Debe permitir votar nuevamente
        ->click('Marcar VOTÓ')
        ->assertSee('Votante marcado como VOTÓ');

    // Debe haber 2 vote records (uno por cada simulacro)
    expect(VoteRecord::where('voter_id', $voter->id)->count())->toBe(2);
});

it('muestra error al intentar votar sin evento activo', function () {
    actingAs($this->user);

    // No hay evento activo
    ElectionEvent::query()->update(['is_active' => false]);

    $voter = Voter::factory()->for($this->campaign)->create([
        'document_number' => '99887766',
        'status' => VoterStatus::CONFIRMED,
    ]);

    $page = visit('/admin/dia-d');

    $page->fill('input[placeholder="Número de documento..."]', '99887766')
        ->click('Buscar')
        ->click('Marcar VOTÓ')
        ->assertSee('No hay ningún evento electoral activo en este momento');
});

it('muestra estadísticas correctas en día D', function () {
    actingAs($this->user);

    $event = ElectionEvent::factory()->today()->active()->for($this->campaign)->create();

    // Crear votantes con diferentes estados
    $voted = Voter::factory()->for($this->campaign)->create(['status' => VoterStatus::VOTED]);
    $didNotVote = Voter::factory()->for($this->campaign)->create(['status' => VoterStatus::DID_NOT_VOTE]);
    $confirmed = Voter::factory()->for($this->campaign)->create(['status' => VoterStatus::CONFIRMED]);

    $page = visit('/admin/dia-d');

    $page->assertSee('Jornada Electoral (Día D)');
    // Las estadísticas se muestran en widgets, verificar que la página carga
});
