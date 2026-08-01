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

it('el listado de líderes sigue mostrando el conteo correcto de apoyos, incluyendo apoyos creados vía el flujo nuevo de "Agregar Apoyo"', function () {
    // Un apoyo creado directamente vía factory (simulando el flujo del propio líder)...
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
    ]);

    // ...y otro creado a través del flujo nuevo de la Task 5 (coordinador agregando
    // un apoyo desde el detalle del líder), que también debe quedar con registered_by = leader->id.
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leader-add-voter', ['leader' => $this->leader])
        ->set('document_number', '1102812166')
        ->set('first_name', 'Ana')
        ->set('last_name', 'Torres')
        ->set('phone', '3007778899')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Voter::where('registered_by', $this->leader->id)->count())->toBe(2);

    Volt::test('coordinator.leaders')
        ->assertSee('2 apoyos registrados');
});
