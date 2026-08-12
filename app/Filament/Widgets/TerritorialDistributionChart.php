<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TerritorialDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Top 10 Municipios con más Apoyos';

    protected ?string $description = 'Distribución territorial de la campaña activa';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                'datasets' => [
                    [
                        'label' => 'Apoyos',
                        'data' => [],
                        'backgroundColor' => '#3b82f6',
                    ],
                ],
                'labels' => [],
            ];
        }

        // Obtener top 10 municipios con más apoyos
        $user = Auth::user();

        $data = Voter::query()
            ->select('municipalities.name', DB::raw('COUNT(*) as total'))
            ->join('municipalities', 'voters.municipality_id', '=', 'municipalities.id')
            ->where('voters.campaign_id', $activeCampaign->id)
            ->when(
                $user?->hasRole(UserRole::LEADER->value),
                fn ($q) => $q->where('voters.registered_by', Auth::id())
            )
            ->when(
                $user?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value]),
                fn ($q) => $q->whereIn('voters.registered_by', User::whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())->pluck('id'))
            )
            ->groupBy('municipalities.id', 'municipalities.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Apoyos',
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
