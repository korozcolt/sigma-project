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
});

test('saving with gremio, subcategoria, lugar de expedicion and placa persists all 4 fields', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->set('gremio_id', $this->gremio->id)
        ->set('subcategoria_id', $this->subcategoria->id)
        ->set('lugar_expedicion_cedula', 'Sincelejo')
        ->set('placa', 'ABC123')
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567890')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->gremio_id)->toBe($this->gremio->id)
        ->and($voter->subcategoria_id)->toBe($this->subcategoria->id)
        ->and($voter->lugar_expedicion_cedula)->toBe('Sincelejo')
        ->and($voter->placa)->toBe('ABC123');
});

test('saving with all 4 additional fields left empty still saves successfully', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567890')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->gremio_id)->toBeNull()
        ->and($voter->subcategoria_id)->toBeNull()
        ->and($voter->lugar_expedicion_cedula)->toBeNull()
        ->and($voter->placa)->toBeNull();
});

test('changing gremio_id resets subcategoria_id back to null', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('subcategoria_id', $this->subcategoria->id)
        ->set('gremio_id', $this->gremio->id)
        ->assertSet('subcategoria_id', null);
});
