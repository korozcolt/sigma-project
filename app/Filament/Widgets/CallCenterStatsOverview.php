<?php

namespace App\Filament\Widgets;

use App\Services\CallCenterService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CallCenterStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    public function getStats(): array
    {
        $callCenterService = app(CallCenterService::class);
        $stats = $callCenterService->getReviewerStats(Auth::id());

        return [
            Stat::make('Llamadas Hoy', $stats['calls_today'])
                ->description('Llamadas realizadas en el día')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Total de Llamadas', $stats['total_calls'])
                ->description('Acumulado histórico')
                ->descriptionIcon('heroicon-m-phone')
                ->color('success'),

            Stat::make('Tasa de Éxito', $stats['success_rate'].'%')
                ->description($stats['successful_calls'].' llamadas exitosas')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($stats['success_rate'] >= 70 ? 'success' : ($stats['success_rate'] >= 40 ? 'warning' : 'danger')),

            Stat::make('Duración Promedio', $stats['avg_duration_formatted'])
                ->description('Tiempo promedio por llamada')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
