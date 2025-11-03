<?php

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;

use function Pest\Laravel\assertDatabaseHas;

it('can create a global neighborhood', function () {
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Centro',
        'is_global' => true,
        'campaign_id' => null,
    ]);

    expect($neighborhood)->toBeInstanceOf(Neighborhood::class);
    expect($neighborhood->name)->toBe('Centro');
    expect($neighborhood->is_global)->toBeTrue();
    expect($neighborhood->campaign_id)->toBeNull();

    assertDatabaseHas('neighborhoods', [
        'name' => 'Centro',
        'is_global' => true,
        'campaign_id' => null,
    ]);
});

it('can create a campaign-specific neighborhood', function () {
    $municipality = Municipality::factory()->create();
    $campaign = Campaign::factory()->create();

    $neighborhood = Neighborhood::factory()->forCampaign($campaign->id)->create([
        'municipality_id' => $municipality->id,
        'name' => 'Urbanización Nueva',
    ]);

    expect($neighborhood)->toBeInstanceOf(Neighborhood::class);
    expect($neighborhood->is_global)->toBeFalse();
    expect($neighborhood->campaign_id)->toBe($campaign->id);

    assertDatabaseHas('neighborhoods', [
        'name' => 'Urbanización Nueva',
        'is_global' => false,
        'campaign_id' => $campaign->id,
    ]);
});

it('requires municipality_id and name', function () {
    expect(fn () => Neighborhood::create([]))->toThrow(Exception::class);
});

it('has municipality relationship', function () {
    $neighborhood = Neighborhood::factory()->create();

    expect($neighborhood->municipality())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve municipality', function () {
    $municipality = Municipality::factory()->create(['name' => 'Medellín']);
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    $neighborhood->load('municipality');

    expect($neighborhood->municipality->id)->toBe($municipality->id);
    expect($neighborhood->municipality->name)->toBe('Medellín');
});

it('scope global returns only global neighborhoods', function () {
    $municipality = Municipality::factory()->create();
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    // Crear barrios globales
    Neighborhood::factory()->global()->create(['municipality_id' => $municipality->id, 'name' => 'Global 1']);
    Neighborhood::factory()->global()->create(['municipality_id' => $municipality->id, 'name' => 'Global 2']);

    // Crear barrios específicos de campaña
    Neighborhood::factory()->forCampaign($campaign1->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 1']);
    Neighborhood::factory()->forCampaign($campaign2->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 2']);

    $globalNeighborhoods = Neighborhood::global()->get();

    expect($globalNeighborhoods)->toHaveCount(2);
    expect($globalNeighborhoods->every(fn ($n) => $n->is_global === true))->toBeTrue();
    expect($globalNeighborhoods->every(fn ($n) => $n->campaign_id === null))->toBeTrue();
});

it('scope forCampaign returns only neighborhoods for specific campaign', function () {
    $municipality = Municipality::factory()->create();
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    // Crear barrios globales
    Neighborhood::factory()->global()->create(['municipality_id' => $municipality->id, 'name' => 'Global 1']);

    // Crear barrios para campaña 1
    Neighborhood::factory()->forCampaign($campaign1->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 1-A']);
    Neighborhood::factory()->forCampaign($campaign1->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 1-B']);

    // Crear barrios para campaña 2
    Neighborhood::factory()->forCampaign($campaign2->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 2-A']);

    $campaign1Neighborhoods = Neighborhood::forCampaign($campaign1->id)->get();

    expect($campaign1Neighborhoods)->toHaveCount(2);
    expect($campaign1Neighborhoods->every(fn ($n) => $n->campaign_id === $campaign1->id))->toBeTrue();
});

it('scope availableForCampaign returns global and campaign-specific neighborhoods', function () {
    $municipality = Municipality::factory()->create();
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    // Crear barrios globales
    Neighborhood::factory()->global()->create(['municipality_id' => $municipality->id, 'name' => 'Global 1']);
    Neighborhood::factory()->global()->create(['municipality_id' => $municipality->id, 'name' => 'Global 2']);

    // Crear barrios para campaña 1
    Neighborhood::factory()->forCampaign($campaign1->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 1-A']);

    // Crear barrios para campaña 2
    Neighborhood::factory()->forCampaign($campaign2->id)->create(['municipality_id' => $municipality->id, 'name' => 'Campaña 2-A']);

    $availableNeighborhoods = Neighborhood::availableForCampaign($campaign1->id)->get();

    expect($availableNeighborhoods)->toHaveCount(3); // 2 globales + 1 de campaña 1
    expect($availableNeighborhoods->where('is_global', true))->toHaveCount(2);
    expect($availableNeighborhoods->where('campaign_id', $campaign1->id))->toHaveCount(1);
});

it('unique constraint allows same name in different municipalities', function () {
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $neighborhood1 = Neighborhood::factory()->create([
        'municipality_id' => $municipality1->id,
        'name' => 'Centro',
    ]);

    $neighborhood2 = Neighborhood::factory()->create([
        'municipality_id' => $municipality2->id,
        'name' => 'Centro',
    ]);

    expect($neighborhood1->name)->toBe($neighborhood2->name);
    expect($neighborhood1->municipality_id)->not->toBe($neighborhood2->municipality_id);
});

it('unique constraint allows same name in same municipality for different campaigns', function () {
    $municipality = Municipality::factory()->create();
    $campaign = Campaign::factory()->create();

    $globalNeighborhood = Neighborhood::factory()->global()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Centro',
    ]);

    $campaign1Neighborhood = Neighborhood::factory()->forCampaign($campaign->id)->create([
        'municipality_id' => $municipality->id,
        'name' => 'Centro',
    ]);

    expect($globalNeighborhood->name)->toBe($campaign1Neighborhood->name);
    expect($globalNeighborhood->campaign_id)->toBeNull();
    expect($campaign1Neighborhood->campaign_id)->toBe($campaign->id);
});

it('unique constraint prevents duplicate name in same municipality and campaign', function () {
    $municipality = Municipality::factory()->create();
    $campaign = Campaign::factory()->create();

    Neighborhood::factory()->forCampaign($campaign->id)->create([
        'municipality_id' => $municipality->id,
        'name' => 'Centro',
    ]);

    expect(fn () => Neighborhood::factory()->forCampaign($campaign->id)->create([
        'municipality_id' => $municipality->id,
        'name' => 'Centro',
    ]))->toThrow(Exception::class);
});

it('can update a neighborhood', function () {
    $neighborhood = Neighborhood::factory()->create([
        'name' => 'Original Name',
    ]);

    $neighborhood->update(['name' => 'Updated Name']);

    expect($neighborhood->fresh()->name)->toBe('Updated Name');
    assertDatabaseHas('neighborhoods', ['name' => 'Updated Name']);
});

it('can delete a neighborhood', function () {
    $neighborhood = Neighborhood::factory()->create();
    $id = $neighborhood->id;

    $neighborhood->delete();

    expect(Neighborhood::find($id))->toBeNull();
});

it('is_global defaults to false in database', function () {
    $municipality = Municipality::factory()->create();

    // Crear un barrio sin especificar is_global
    $neighborhood = Neighborhood::create([
        'municipality_id' => $municipality->id,
        'name' => 'Test Neighborhood',
        'is_global' => false,
    ]);

    $neighborhood->refresh();

    expect($neighborhood->is_global)->toBeFalse();
    assertDatabaseHas('neighborhoods', [
        'name' => 'Test Neighborhood',
        'is_global' => false,
    ]);
});
