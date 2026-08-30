<?php

declare(strict_types=1);

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('findByFuzzyName returns the exact match without normalizing when one exists', function () {
    $municipality = Municipality::factory()->create(['name' => 'Sincelejo']);

    expect(Municipality::findByFuzzyName('SINCELEJO')->id)->toBe($municipality->id);
});

test('findByFuzzyName matches a live result missing an accent and a space', function () {
    $municipality = Municipality::factory()->create(['name' => 'Tolú Viejo']);

    expect(Municipality::findByFuzzyName('TOLUVIEJO')->id)->toBe($municipality->id);
});

test('findByFuzzyName matches a live result with an extra period', function () {
    $municipality = Municipality::factory()->create(['name' => 'Bogotá D.C.']);

    expect(Municipality::findByFuzzyName('BOGOTA. D.C.')->id)->toBe($municipality->id);
});

test('findByFuzzyName matches a live result with a parenthetical alternate name', function () {
    $municipality = Municipality::factory()->create(['name' => 'Coloso']);

    expect(Municipality::findByFuzzyName('COLOSO (RICAURTE)')->id)->toBe($municipality->id);
});

test('findByFuzzyName returns null and does not guess when normalization matches more than one municipality', function () {
    // Neither candidate exactly (case-insensitively) matches the query "TOLUVIEJO" itself
    // (both have a space the query lacks), so this only reaches the normalized fallback —
    // where both collapse to the same "TOLUVIEJO" key and the match becomes ambiguous.
    Municipality::factory()->create(['name' => 'Tolú Viejo']);
    Municipality::factory()->create(['name' => 'Tolu Viejo']);

    expect(Municipality::findByFuzzyName('TOLUVIEJO'))->toBeNull();
});

test('findByFuzzyName returns null when the municipality genuinely does not exist in the catalog', function () {
    expect(Municipality::findByFuzzyName('MUNICIPIO INEXISTENTE'))->toBeNull();
});

test('findByFuzzyName returns null for a blank name', function () {
    expect(Municipality::findByFuzzyName(''))->toBeNull();
});
