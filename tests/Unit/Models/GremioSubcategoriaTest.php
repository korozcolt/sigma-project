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
// D-04/D-05/D-09. One assertion (Voter optional gremio_id/subcategoria_id)
// is skipped until those columns land on `voters` in plan 02.1-08.

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
})->skip(
    fn (): bool => ! Schema::hasColumn('voters', 'gremio_id') || ! Schema::hasColumn('voters', 'subcategoria_id'),
    'voters.gremio_id/subcategoria_id land in plan 02.1-08'
);

it('GremioFactory and SubcategoriaFactory produce valid records', function () {
    $gremio = Gremio::factory()->create();
    $subcategoria = Subcategoria::factory()->create();

    expect($gremio->name)->not->toBeEmpty()
        ->and($subcategoria->name)->not->toBeEmpty()
        ->and($subcategoria->gremio_id)->not->toBeNull();
});
