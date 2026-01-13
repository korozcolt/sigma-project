<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Voter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiaDStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $campaign = Campaign::where('status', 'active')->first();

        if (! $campaign) {
            return [
                Stat::make('Total Votantes', 0)->description('No hay campaña activa')->color('warning'),
            ];
        }

        $total = Voter::forCampaign($campaign->id)->count();
        $confirmed = Voter::forCampaign($campaign->id)->where('status', VoterStatus::CONFIRMED->value)->count();
        $voted = Voter::forCampaign($campaign->id)->voted()->count();
        $didNotVote = Voter::forCampaign($campaign->id)->didNotVote()->count();

        $participation = $total > 0 ? round(($voted / $total) * 100, 1) : 0;

        return [
            Stat::make('Total Votantes', number_format($total))
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
}
