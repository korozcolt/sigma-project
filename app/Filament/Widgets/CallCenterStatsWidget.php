<?php

namespace App\Filament\Widgets;

use App\Enums\CallResult;
use App\Models\VerificationCall;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CallCenterStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            $this->getTotalCallsStat(),
            $this->getContactRateStat(),
            $this->getAverageDurationStat(),
            $this->getConfirmationsStat(),
        ];
    }

    protected function getTotalCallsStat(): Stat
    {
        $today = VerificationCall::whereDate('call_date', today())->count();
        $yesterday = VerificationCall::whereDate('call_date', today()->subDay())->count();

        $trend = $yesterday > 0 ? (($today - $yesterday) / $yesterday) * 100 : 0;
        $trendIcon = $trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $trend >= 0 ? 'success' : 'danger';

        return Stat::make('Llamadas Hoy', $today)
            ->description(abs(round($trend, 1)).'% vs ayer')
            ->descriptionIcon($trendIcon)
            ->color($trendColor)
            ->chart($this->getLastWeekCallsChart());
    }

    protected function getContactRateStat(): Stat
    {
        $totalCalls = VerificationCall::whereDate('call_date', today())->count();
        $successfulCalls = VerificationCall::whereDate('call_date', today())
            ->whereIn('call_result', [
                CallResult::ANSWERED,
                CallResult::CONFIRMED,
            ])
            ->count();

        $contactRate = $totalCalls > 0 ? ($successfulCalls / $totalCalls) * 100 : 0;

        $color = match (true) {
            $contactRate >= 70 => 'success',
            $contactRate >= 50 => 'warning',
            default => 'danger',
        };

        return Stat::make('Tasa de Contacto', round($contactRate, 1).'%')
            ->description($successfulCalls.' de '.$totalCalls.' llamadas')
            ->descriptionIcon('heroicon-m-phone')
            ->color($color);
    }

    protected function getAverageDurationStat(): Stat
    {
        $avgDuration = VerificationCall::whereDate('call_date', today())
            ->where('call_result', CallResult::ANSWERED)
            ->avg('call_duration') ?? 0;

        $minutes = floor($avgDuration / 60);
        $seconds = $avgDuration % 60;

        return Stat::make('Duración Promedio', sprintf('%d:%02d min', $minutes, $seconds))
            ->description('Llamadas contestadas hoy')
            ->descriptionIcon('heroicon-m-clock')
            ->color('info');
    }

    protected function getConfirmationsStat(): Stat
    {
        $today = VerificationCall::whereDate('call_date', today())
            ->where('call_result', CallResult::CONFIRMED)
            ->count();

        $thisWeek = VerificationCall::whereBetween('call_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])
            ->where('call_result', CallResult::CONFIRMED)
            ->count();

        return Stat::make('Confirmaciones Hoy', $today)
            ->description($thisWeek.' esta semana')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color('success')
            ->chart($this->getWeekConfirmationsChart());
    }

    protected function getLastWeekCallsChart(): array
    {
        return VerificationCall::query()
            ->select(DB::raw('DATE(call_date) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('call_date', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }

    protected function getWeekConfirmationsChart(): array
    {
        return VerificationCall::query()
            ->select(DB::raw('DATE(call_date) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('call_date', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->where('call_result', CallResult::CONFIRMED)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }
}
