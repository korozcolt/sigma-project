<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\CallAssignment;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FollowUpBacklogOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Rezago de Seguimiento';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                Stat::make('Apoyos Pendientes de Validar', 0)->color('warning'),
                Stat::make('Llamadas Pendientes', 0)->color('warning'),
            ];
        }

        $pendingValidation = Voter::where('campaign_id', $activeCampaign->id)
            ->where('status', VoterStatus::PENDING_REVIEW->value)
            ->count();

        $pendingCalls = CallAssignment::where('campaign_id', $activeCampaign->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return [
            Stat::make('Apoyos Pendientes de Validar', number_format($pendingValidation))
                ->description('Sin validar contra el censo electoral')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingValidation > 0 ? 'warning' : 'success')
                ->url(VoterResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['values' => [VoterStatus::PENDING_REVIEW->value]],
                    ],
                ])),

            Stat::make('Llamadas Pendientes', number_format($pendingCalls))
                ->description('Asignaciones de llamada pendientes o en progreso')
                ->descriptionIcon('heroicon-m-phone')
                ->color($pendingCalls > 0 ? 'warning' : 'success'),
        ];
    }
}
