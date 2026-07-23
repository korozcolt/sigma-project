<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ValidationProgressChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Progreso de Validación — 30 días';

    protected ?string $description = 'Total de apoyos registrados vs validados por llamada';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

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

            // Apoyos validados hasta esta fecha
            $validated = $this->scopedVoterQuery($activeCampaign)
                ->whereDate('call_verified_at', '<=', $date)
                ->count();

            // Total de apoyos creados hasta esta fecha
            $total = $this->scopedVoterQuery($activeCampaign)
                ->whereDate('created_at', '<=', $date)
                ->count();

            $validatedData[] = $validated;
            $totalData[] = $total;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Apoyos',
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

    private function scopedVoterQuery(Campaign $campaign): Builder
    {
        $user = Auth::user();
        $query = Voter::where('campaign_id', $campaign->id);

        if ($user?->hasRole(UserRole::LEADER->value)) {
            return $query->where('registered_by', $user->id);
        }

        if ($user?->hasRole(UserRole::COORDINATOR->value)) {
            return $query->whereIn('registered_by', $user->leaders()->pluck('id'));
        }

        return $query;
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
