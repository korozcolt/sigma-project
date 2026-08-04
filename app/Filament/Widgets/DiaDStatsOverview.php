<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DiaDStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Estado del Día D';

    protected ?string $description = 'Participación en tiempo real — actualiza cada 10 segundos';

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $campaign = CampaignContext::currentCampaign();

        if (! $campaign) {
            return [
                Stat::make('Total Apoyos', 0)->description('No hay campaña seleccionada')->color('warning'),
            ];
        }

        $total = $this->scopedVoterQuery($campaign)->count();
        $confirmed = $this->scopedVoterQuery($campaign)->where('status', VoterStatus::CONFIRMED->value)->count();
        $voted = $this->scopedVoterQuery($campaign)->voted()->count();
        $didNotVote = $this->scopedVoterQuery($campaign)->didNotVote()->count();

        $participation = $total > 0 ? round(($voted / $total) * 100, 1) : 0;

        return [
            Stat::make('Total Apoyos', number_format($total))
                ->description('En campaña activa')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Confirmados', number_format($confirmed))
                ->description('Listos para votar')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Votaron', number_format($voted))
                ->description($participation.'% participación')
                ->descriptionIcon('heroicon-m-hand-thumb-up')
                ->color('success'),

            Stat::make('No Votaron', number_format($didNotVote))
                ->description('Marcados')
                ->descriptionIcon('heroicon-m-hand-thumb-down')
                ->color('danger'),
        ];
    }

    private function scopedVoterQuery(Campaign $campaign): Builder
    {
        $user = Auth::user();
        $query = Voter::forCampaign($campaign->id);

        if ($user?->hasRole(UserRole::LEADER->value)) {
            return $query->where('registered_by', $user->id);
        }

        if ($user?->hasRole(UserRole::COORDINATOR->value)) {
            return $query->whereIn('registered_by', $user->leaders()->pluck('id'));
        }

        return $query;
    }
}
