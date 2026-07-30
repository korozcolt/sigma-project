<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DailyCaptchaCostStatus;
use Carbon\CarbonImmutable;

final readonly class DailyCaptchaCost
{
    /**
     * @param  CarbonImmutable  $day  Inicio del día (medianoche) en America/Bogota.
     * @param  DailyCaptchaCostStatus  $status  Computed | NoData | RechargeDetected.
     * @param  ?float  $averageUsd  Costo promedio por consulta ese día (USD), null salvo status Computed.
     */
    public function __construct(
        public CarbonImmutable $day,
        public DailyCaptchaCostStatus $status,
        public ?float $averageUsd = null,
    ) {}
}
