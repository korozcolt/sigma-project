<?php

use App\Enums\PollingPlaceSource;
use App\Services\PollingPlaceResolutionResult;

it('exposes readonly source, fields, pollingPlaceId, and tableNumber from the constructor', function () {
    $result = new PollingPlaceResolutionResult(
        source: PollingPlaceSource::LIVE,
        fields: ['puesto_nombre' => 'Test Place', 'mesa_numero' => '007'],
        pollingPlaceId: 42,
        tableNumber: '7',
    );

    expect($result->source)->toBe(PollingPlaceSource::LIVE)
        ->and($result->fields)->toBe(['puesto_nombre' => 'Test Place', 'mesa_numero' => '007'])
        ->and($result->pollingPlaceId)->toBe(42)
        ->and($result->tableNumber)->toBe('7');
});

it('defaults pollingPlaceId and tableNumber to null when omitted', function () {
    $result = new PollingPlaceResolutionResult(source: PollingPlaceSource::SNAPSHOT, fields: []);

    expect($result->pollingPlaceId)->toBeNull()
        ->and($result->tableNumber)->toBeNull();
});

it('is immutable — the class is declared readonly', function () {
    $reflection = new ReflectionClass(PollingPlaceResolutionResult::class);

    expect($reflection->isReadOnly())->toBeTrue();
});
