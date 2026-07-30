<?php

declare(strict_types=1);

use App\Enums\DailyCaptchaCostStatus;
use App\Models\RegistraduriaLookup;
use App\Models\TwoCaptchaBalanceSnapshot;
use App\Services\TwoCaptchaDailyCostService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(TwoCaptchaDailyCostService::class);
    $this->bogotaToday = CarbonImmutable::now('America/Bogota')->startOfDay();
});

it('computes the average cost for a normal day', function () {
    $day = $this->bogotaToday->subDays(2);

    // Snapshot antes del inicio del día (apertura)
    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 10.0,
        'checked_at' => $day->subHour()->utc(),
    ]);

    // Snapshot dentro del día (cierre)
    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 9.0,
        'checked_at' => $day->addHours(12)->utc(),
    ]);

    // 5 consultas live ese día
    RegistraduriaLookup::factory()->count(5)->create([
        'source' => 'live',
        'created_at' => $day->addHours(10)->utc(),
    ]);

    $result = $this->service->forDay($day);

    expect($result->status)->toBe(DailyCaptchaCostStatus::Computed)
        ->and($result->averageUsd)->toBe((10.0 - 9.0) / 5);
});

it('detects a recharge day when the balance increases or stays the same', function () {
    $day = $this->bogotaToday->subDays(2);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 5.0,
        'checked_at' => $day->subHour()->utc(),
    ]);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 20.0,
        'checked_at' => $day->addHours(12)->utc(),
    ]);

    RegistraduriaLookup::factory()->count(3)->create([
        'source' => 'live',
        'created_at' => $day->addHours(10)->utc(),
    ]);

    $result = $this->service->forDay($day);

    expect($result->status)->toBe(DailyCaptchaCostStatus::RechargeDetected)
        ->and($result->averageUsd)->toBeNull();
});

it('returns no data on cold start when there is no snapshot before the day', function () {
    $day = $this->bogotaToday->subDays(2);

    // Solo hay snapshot DENTRO del día, ninguno antes
    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 9.0,
        'checked_at' => $day->addHours(12)->utc(),
    ]);

    RegistraduriaLookup::factory()->count(2)->create([
        'source' => 'live',
        'created_at' => $day->addHours(10)->utc(),
    ]);

    $result = $this->service->forDay($day);

    expect($result->status)->toBe(DailyCaptchaCostStatus::NoData)
        ->and($result->averageUsd)->toBeNull();
});

it('returns no data when there is a positive delta but zero live lookups that day', function () {
    $day = $this->bogotaToday->subDays(2);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 10.0,
        'checked_at' => $day->subHour()->utc(),
    ]);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 9.0,
        'checked_at' => $day->addHours(12)->utc(),
    ]);

    $result = $this->service->forDay($day);

    expect($result->status)->toBe(DailyCaptchaCostStatus::NoData)
        ->and($result->averageUsd)->toBeNull();
});

it('does not count a lookup that falls just outside the Bogota day boundary', function () {
    $day = $this->bogotaToday->subDays(2);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 10.0,
        'checked_at' => $day->subHour()->utc(),
    ]);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 9.0,
        'checked_at' => $day->addHours(12)->utc(),
    ]);

    // Un lookup live 1 minuto antes de que empiece el día Bogotá -> NO debe contar
    RegistraduriaLookup::factory()->create([
        'source' => 'live',
        'created_at' => $day->subMinute()->utc(),
    ]);

    // Un lookup dentro del día -> SÍ debe contar
    RegistraduriaLookup::factory()->create([
        'source' => 'live',
        'created_at' => $day->addHours(10)->utc(),
    ]);

    $result = $this->service->forDay($day);

    expect($result->status)->toBe(DailyCaptchaCostStatus::Computed)
        ->and($result->averageUsd)->toBe((10.0 - 9.0) / 1);
});

it('returns 7 buckets in Bogota order from lastDays', function () {
    $days = $this->service->lastDays(7);

    expect($days)->toHaveCount(7);

    for ($i = 0; $i < 6; $i++) {
        expect($days[$i]->day->greaterThan($days[$i + 1]->day))->toBeTrue();
    }
});
