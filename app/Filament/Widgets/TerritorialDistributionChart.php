<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use App\Models\Voter;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TerritorialDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Distribución Territorial de Votantes';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $activeCampaign = Campaign::where('status', 'active')->first();

        if (! $activeCampaign) {
            return [
                'datasets' => [
                    [
                        'label' => 'Votantes',
                        'data' => [],
                        'backgroundColor' => '#3b82f6',
                    ],
                ],
                'labels' => [],
            ];
        }

        // Obtener top 10 municipios con más votantes
        $data = Voter::query()
            ->select('municipalities.name', DB::raw('COUNT(*) as total'))
            ->join('municipalities', 'voters.municipality_id', '=', 'municipalities.id')
            ->where('voters.campaign_id', $activeCampaign->id)
            ->groupBy('municipalities.id', 'municipalities.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Votantes',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6',
                        '#8b5cf6',
                        '#ec4899',
                        '#f59e0b',
                        '#10b981',
                        '#06b6d4',
                        '#6366f1',
                        '#f43f5e',
                        '#14b8a6',
                        '#a855f7',
                    ],
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
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
        ];
    }
}
