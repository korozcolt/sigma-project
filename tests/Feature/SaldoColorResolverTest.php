<?php

declare(strict_types=1);

use App\Services\SaldoColorResolver;

test('twoCaptcha returns gray when balance is null', function () {
    expect(SaldoColorResolver::twoCaptcha(null))->toBe('gray');
});

test('hablame returns gray when balance is null', function () {
    expect(SaldoColorResolver::hablame(null))->toBe('gray');
});

test('twoCaptcha color resolves by threshold', function (?float $usd, string $expected) {
    expect(SaldoColorResolver::twoCaptcha($usd))->toBe($expected);
})->with([
    'at green threshold' => [5.0, 'success'],
    'above green threshold' => [10.0, 'success'],
    'at yellow threshold' => [1.0, 'warning'],
    'between yellow and green' => [2.5, 'warning'],
    'below yellow threshold' => [0.5, 'danger'],
    'zero' => [0.0, 'danger'],
]);

test('hablame color resolves by threshold', function (?float $cop, string $expected) {
    expect(SaldoColorResolver::hablame($cop))->toBe($expected);
})->with([
    'at green threshold' => [500000.0, 'success'],
    'above green threshold' => [1000000.0, 'success'],
    'at yellow threshold' => [100000.0, 'warning'],
    'between yellow and green' => [250000.0, 'warning'],
    'below yellow threshold' => [50000.0, 'danger'],
    'zero' => [0.0, 'danger'],
]);
