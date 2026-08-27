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

    $this->gremio = Gremio::factory()->create();
    $this->subcategoria = Subcategoria::factory()->create(['gremio_id' => $this->gremio->id]);
    $this->otherGremio = Gremio::factory()->create();
});

it('guardar con gremio/subcategoria/lugar_expedicion_cedula/placa persiste los 4 campos y registered_by = leader', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leader-add-voter', ['leader' => $this->leader])
        ->set('document_number', '1102812188')
        ->set('first_name', 'Maria')
        ->set('last_name', 'Lopez')
        ->set('phone', '3001112233')
        ->set('municipality_id', $this->municipality->id)
        ->set('gremio_id', $this->gremio->id)
        ->set('subcategoria_id', $this->subcategoria->id)
        ->set('lugar_expedicion_cedula', 'Sincelejo')
        ->set('placa', 'ABC123')
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1102812188')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->registered_by)->toBe($this->leader->id)
        ->and($voter->gremio_id)->toBe($this->gremio->id)
        ->and($voter->subcategoria_id)->toBe($this->subcategoria->id)
        ->and($voter->lugar_expedicion_cedula)->toBe('Sincelejo')
        ->and($voter->placa)->toBe('ABC123');
});

it('guardar con los 4 campos vacíos sigue funcionando', function () {
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
        ->and($voter->gremio_id)->toBeNull()
        ->and($voter->subcategoria_id)->toBeNull()
        ->and($voter->lugar_expedicion_cedula)->toBeNull()
        ->and($voter->placa)->toBeNull();
});

it('seleccionar una subcategoria de un gremio distinto falla la validación', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leader-add-voter', ['leader' => $this->leader])
        ->set('document_number', '1102812188')
        ->set('first_name', 'Maria')
        ->set('last_name', 'Lopez')
        ->set('phone', '3001112233')
        ->set('municipality_id', $this->municipality->id)
        ->set('gremio_id', $this->otherGremio->id)
        // Set directly after gremio_id: there is no updatedSubcategoriaId() hook that would
        // re-clear this, so this reproduces a mismatched gremio/subcategoria pair reaching save().
        ->set('subcategoria_id', $this->subcategoria->id)
        ->call('save')
        ->assertHasErrors('subcategoria_id');
});
