<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use App\Models\Voter;
use Filament\Widgets\ChartWidget;

class ValidationProgressChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Progreso de Validación (Últimos 30 días)';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $activeCampaign = Campaign::where('status', 'active')->first();

        if (! $activeCampaign) {
            return [
                'datasets' => [
                    [
                        'label' => 'Validados',
                        'data' => [],
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    ],
                ],
                'labels' => [],
            ];
        }

        $days = [];
        $validatedData = [];
        $totalData = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days[] = $date->format('d M');

            // Votantes validados hasta esta fecha
            $validated = Voter::where('campaign_id', $activeCampaign->id)
                ->whereDate('call_verified_at', '<=', $date)
                ->count();

            // Total de votantes creados hasta esta fecha
            $total = Voter::where('campaign_id', $activeCampaign->id)
                ->whereDate('created_at', '<=', $date)
                ->count();

            $validatedData[] = $validated;
            $totalData[] = $total;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Votantes',
                    'data' => $totalData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Validados',
                    'data' => $validatedData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}
