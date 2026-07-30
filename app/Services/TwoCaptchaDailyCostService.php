<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DailyCaptchaCostStatus;
use App\Models\RegistraduriaLookup;
use App\Models\TwoCaptchaBalanceSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class TwoCaptchaDailyCostService
{
    private const TIMEZONE = 'America/Bogota';

    /**
     * Calcula el costo promedio por consulta de 2captcha para un día dado,
     * bucketeado en America/Bogota (no UTC).
     *
     * Aproximación conocida: el conteo de consultas usa `registraduria_lookups`
     * con `source = 'live'`. Un force-refresh de un documento ya resuelto hace
     * `updateOrCreate` sobre la fila existente sin cambiar `created_at`, por lo
     * que este conteo puede subestimar los solves reales de 2captcha ese día.
     */
    public function forDay(CarbonInterface $day): DailyCaptchaCost
    {
        $start = CarbonImmutable::parse($day)->timezone(self::TIMEZONE)->startOfDay();
        $endExclusive = $start->addDay();

        // Los timestamps se guardan en UTC; convertir el rango Bogotá a UTC
        // en vez de depender de las tablas de zona horaria de MySQL.
        $startUtc = $start->utc();
        $endUtc = $endExclusive->utc();

        $opening = TwoCaptchaBalanceSnapshot::query()
            ->where('checked_at', '<', $startUtc)
            ->orderByDesc('checked_at')
            ->first();

        $closing = TwoCaptchaBalanceSnapshot::query()
            ->where('checked_at', '>=', $startUtc)
            ->where('checked_at', '<', $endUtc)
            ->orderByDesc('checked_at')
            ->first();

        if ($opening === null || $closing === null) {
            return new DailyCaptchaCost($start, DailyCaptchaCostStatus::NoData);
        }

        $spend = (float) $opening->balance - (float) $closing->balance;

        if ($spend <= 0) {
            return new DailyCaptchaCost($start, DailyCaptchaCostStatus::RechargeDetected);
        }

        $count = RegistraduriaLookup::query()
            ->where('source', 'live')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->count();

        if ($count === 0) {
            return new DailyCaptchaCost($start, DailyCaptchaCostStatus::NoData);
        }

        return new DailyCaptchaCost($start, DailyCaptchaCostStatus::Computed, $spend / $count);
    }

    /**
     * @return array<int, DailyCaptchaCost> Hoy y los ($days - 1) días anteriores,
     *                                       bucketeados en Bogotá, del más reciente al más antiguo.
     */
    public function lastDays(int $days = 7): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        $results = [];

        for ($i = 0; $i < $days; $i++) {
            $results[] = $this->forDay($today->subDays($i));
        }

        return $results;
    }
}
