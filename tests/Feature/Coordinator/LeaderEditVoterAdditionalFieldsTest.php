<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Gremio;
use App\Models\Municipality;
use App\Models\Subcategoria;
use App\Models\User;
use App\Models\Voter;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);
    $this->coordinator->campaigns()->attach($this->campaign->id);

    $this->leader = User::factory()->create(['municipality_id' => $this->municipality->id, 'coordinator_user_id' => $this->coordinator->id]);
    $this->leader->assignRole(UserRole::LEADER->value);
    $this->leader->campaigns()->attach($this->campaign->id);

    $this->voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $this->gremio = Gremio::factory()->create();
    $this->subcategoria = Subcategoria::factory()->create(['gremio_id' => $this->gremio->id]);
});

it('un coordinador puede abrir el formulario de editar para un apoyo de su propio líder', function () {
    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.leaders.voters.edit', [$this->leader, $this->voter]));

    $response->assertOk();
});

it('devuelve 403 cuando el líder pertenece a otro coordinador del mismo municipio y campaña', function () {
    $otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $otherCoordinator->campaigns()->attach($this->campaign->id);

    $otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $otherCoordinator->id,
    ]);
    $otherLeader->assignRole(UserRole::LEADER->value);
    $otherLeader->campaigns()->attach($this->campaign->id);

    $otherVoter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $otherLeader->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.leaders.voters.edit', [$otherLeader, $otherVoter]));

    $response->assertForbidden();
});

it('devuelve 403 cuando el apoyo no fue registrado por el líder de la ruta', function () {
    $otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $this->coordinator->id,
    ]);
    $otherLeader->assignRole(UserRole::LEADER->value);
    $otherLeader->campaigns()->attach($this->campaign->id);

    $mismatchedVoter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $otherLeader->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $this->actingAs($this->coordinator);

    // $this->leader belongs to $this->coordinator, but $mismatchedVoter was registered by $otherLeader.
    $response = $this->get(route('coordinator.leaders.voters.edit', [$this->leader, $mismatchedVoter]));

    $response->assertForbidden();
});

it('guardar actualiza los 4 campos y deja intactas las demás columnas', function () {
    $this->actingAs($this->coordinator);

    $originalFirstName = $this->voter->first_name;
    $originalDocumentNumber = $this->voter->document_number;

    Volt::test('coordinator.leader-edit-voter', ['leader' => $this->leader, 'voter' => $this->voter])
        ->set('gremio_id', $this->gremio->id)
        ->set('subcategoria_id', $this->subcategoria->id)
        ->set('lugar_expedicion_cedula', 'Sincelejo')
        ->set('placa', 'ABC123')
        ->call('save')
        ->assertHasNoErrors();

    $this->voter->refresh();

    expect($this->voter->gremio_id)->toBe($this->gremio->id)
        ->and($this->voter->subcategoria_id)->toBe($this->subcategoria->id)
        ->and($this->voter->lugar_expedicion_cedula)->toBe('Sincelejo')
        ->and($this->voter->placa)->toBe('ABC123')
        ->and($this->voter->first_name)->toBe($originalFirstName)
        ->and($this->voter->document_number)->toBe($originalDocumentNumber);
});

it('el enlace "Editar" aparece en el detalle de apoyos del líder', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leader-voters', ['leader' => $this->leader])
        ->assertSeeHtml(route('coordinator.leaders.voters.edit', [$this->leader, $this->voter]));
});
