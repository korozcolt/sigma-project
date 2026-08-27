<?php

use App\Models\Campaign;
use App\Models\Gremio;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\Subcategoria;
use App\Models\User;
use App\Models\Voter;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses()->group('leader-app');

beforeEach(function () {
    Role::create(['name' => 'leader']);
    Role::create(['name' => 'admin']);

    $this->municipality = Municipality::factory()->create();
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->campaign = Campaign::factory()->create();

    $this->leader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'neighborhood_id' => $this->neighborhood->id,
    ]);
    $this->leader->assignRole('leader');
    $this->leader->campaigns()->attach($this->campaign);

    $this->gremio = Gremio::factory()->create();
    $this->subcategoria = Subcategoria::factory()->create(['gremio_id' => $this->gremio->id]);

    $this->voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'municipality_id' => $this->municipality->id,
    ]);
});

test('a leader can open the edit page for a voter they registered', function () {
    $this->actingAs($this->leader);

    $response = $this->get(route('leader.edit-voter', $this->voter));

    $response->assertOk();
});

test('a leader gets 403 for a voter registered by a different leader', function () {
    $otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);
    $otherLeader->assignRole('leader');
    $otherLeader->campaigns()->attach($this->campaign);

    $otherVoter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $otherLeader->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $this->actingAs($this->leader);

    $response = $this->get(route('leader.edit-voter', $otherVoter));

    $response->assertForbidden();
});

test('saving updates the 4 fields and leaves other columns untouched', function () {
    $this->actingAs($this->leader);

    $originalFirstName = $this->voter->first_name;
    $originalDocumentNumber = $this->voter->document_number;

    Volt::test('leader.edit-voter', ['voter' => $this->voter])
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

test('the editar link is visible in my-voters for a voter the leader registered', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.my-voters')
        ->assertSeeHtml(route('leader.edit-voter', $this->voter));
});
