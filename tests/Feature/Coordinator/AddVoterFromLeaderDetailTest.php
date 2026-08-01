<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
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
});

it('un coordinador puede abrir el formulario de agregar apoyo para uno de sus líderes', function () {
    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.leaders.voters.create', $this->leader));

    $response->assertOk();
});

it('devuelve 403 cuando el líder no pertenece al coordinador autenticado', function () {
    // Comparte la misma campaña que el líder (para que el global scope de User
    // no filtre el binding implícito antes de llegar a mount()), pero vive en
    // un municipio distinto al del líder — la misma condición que mount()
    // verifica explícitamente (copiada tal cual de leader-voters.blade.php).
    $otherMunicipality = Municipality::factory()->create();

    $otherCoordinator = User::factory()->create(['municipality_id' => $otherMunicipality->id]);
    $otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $otherCoordinator->campaigns()->attach($this->campaign->id);

    $this->actingAs($otherCoordinator);

    $response = $this->get(route('coordinator.leaders.voters.create', $this->leader));

    $response->assertForbidden();
});

it('guardar el formulario crea un Voter con registered_by = leader->id, no el coordinador', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leader-add-voter', ['leader' => $this->leader])
        ->set('document_number', '1102812188')
        ->set('first_name', 'Maria')
        ->set('last_name', 'Lopez')
        ->set('phone', '3001112233')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1102812188')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->registered_by)->toBe($this->leader->id)
        ->and($voter->registered_by)->not->toBe($this->coordinator->id)
        ->and($voter->campaign_id)->toBe($this->campaign->id);
});

it('el botón "Agregar Apoyo" aparece en el detalle de apoyos del líder', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leader-voters', ['leader' => $this->leader])
        ->assertSeeHtml(route('coordinator.leaders.voters.create', $this->leader));
});
