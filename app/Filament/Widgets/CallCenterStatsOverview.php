<?php

namespace App\Filament\Widgets;

use App\Services\CallCenterService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CallCenterStatsOverview extends StatsOverviewWidget
{


    protected ?string $heading = 'Mi Rendimiento Hoy';

    protected ?string $description = 'Estadísticas personales del revisor en sesión';

    protected ?string $pollingInterval = '30s';

    public function getStats(): array
    {
        $callCenterService = app(CallCenterService::class);
        $stats = $callCenterService->getReviewerStats(Auth::id());

        return [
            Stat::make('Mis Llamadas Hoy', $stats['calls_today'])
                ->description('Llamadas que realizaste hoy')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Total Acumulado', $stats['total_calls'])
                ->description('Todas tus llamadas históricas')
                ->descriptionIcon('heroicon-m-phone')
                ->color('success'),

            Stat::make('Mi Tasa de Éxito', $stats['success_rate'].'%')
                ->description($stats['successful_calls'].' llamadas exitosas en total')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($stats['success_rate'] >= 70 ? 'success' : ($stats['success_rate'] >= 40 ? 'warning' : 'danger')),

            Stat::make('Duración Promedio', $stats['avg_duration_formatted'])
                ->description('Tiempo medio por llamada contestada')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
