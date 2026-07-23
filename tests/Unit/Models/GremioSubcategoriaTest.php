<?php

declare(strict_types=1);

use App\Models\Gremio;
use App\Models\Subcategoria;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// (Plan 02.1-02): Verifies the Gremio -> Subcategoria global catalog for
// D-04/D-05/D-09. (Plan 02.1-08): Adds voters.gremio_id/subcategoria_id/
// lugar_expedicion_cedula/placa coverage now that those columns exist.

it('a Subcategoria belongs to exactly one Gremio', function () {
    $gremio = Gremio::factory()->create();
    $subcategoria = Subcategoria::factory()->create(['gremio_id' => $gremio->id]);

    expect($subcategoria->gremio())->toBeInstanceOf(BelongsTo::class)
        ->and($subcategoria->gremio->id)->toBe($gremio->id);
});

it('a Gremio has many Subcategorias', function () {
    $gremio = Gremio::factory()->create();
    Subcategoria::factory()->count(3)->create(['gremio_id' => $gremio->id]);

    expect($gremio->subcategorias())->toBeInstanceOf(HasMany::class)
        ->and($gremio->subcategorias)->toHaveCount(3);
});

it('Gremio and Subcategoria have no campaign_id column (global catalog, D-09)', function () {
    expect(Schema::hasTable('gremios'))->toBeTrue()
        ->and(Schema::hasTable('subcategorias'))->toBeTrue()
        ->and(Schema::hasColumn('gremios', 'campaign_id'))->toBeFalse()
        ->and(Schema::hasColumn('subcategorias', 'campaign_id'))->toBeFalse();
});

it('a Voter can be created without gremio_id or subcategoria_id (both optional, D-05)', function () {
    $voter = Voter::factory()->create([
        'gremio_id' => null,
        'subcategoria_id' => null,
    ]);

    expect($voter->gremio_id)->toBeNull()
        ->and($voter->subcategoria_id)->toBeNull();
});

it('a Voter gremio relation resolves to null or to a Gremio instance (D-05)', function () {
    $withoutGremio = Voter::factory()->create(['gremio_id' => null]);
    $gremio = Gremio::factory()->create();
    $withGremio = Voter::factory()->create(['gremio_id' => $gremio->id]);

    expect($withoutGremio->gremio)->toBeNull()
        ->and($withGremio->gremio)->toBeInstanceOf(Gremio::class)
        ->and($withGremio->gremio->id)->toBe($gremio->id);
});

it('a Voter subcategoria relation resolves to null or to a Subcategoria instance (D-05)', function () {
    $withoutSubcategoria = Voter::factory()->create(['subcategoria_id' => null]);
    $subcategoria = Subcategoria::factory()->create();
    $withSubcategoria = Voter::factory()->create(['subcategoria_id' => $subcategoria->id]);

    expect($withoutSubcategoria->subcategoria)->toBeNull()
        ->and($withSubcategoria->subcategoria)->toBeInstanceOf(Subcategoria::class)
        ->and($withSubcategoria->subcategoria->id)->toBe($subcategoria->id);
});

it('a Voter can be created without lugar_expedicion_cedula or placa (both optional)', function () {
    $voter = Voter::factory()->create([
        'lugar_expedicion_cedula' => null,
        'placa' => null,
    ]);

    expect($voter->lugar_expedicion_cedula)->toBeNull()
        ->and($voter->placa)->toBeNull();
});

it('GremioFactory and SubcategoriaFactory produce valid records', function () {
    $gremio = Gremio::factory()->create();
    $subcategoria = Subcategoria::factory()->create();

    expect($gremio->name)->not->toBeEmpty()
        ->and($subcategoria->name)->not->toBeEmpty()
        ->and($subcategoria->gremio_id)->not->toBeNull();
});
