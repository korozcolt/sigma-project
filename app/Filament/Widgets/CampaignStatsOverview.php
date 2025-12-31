<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CampaignStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        return [
            $this->getTotalVotersStat(),
            $this->getConfirmedVotersStat(),
            $this->getActiveLeadersStat(),
            $this->getValidationProgressStat(),
        ];
    }

    protected function getTotalVotersStat(): Stat
    {
        $activeCampaign = Campaign::where('status', 'active')->first();

        if (! $activeCampaign) {
            return Stat::make('Total de Votantes', 0)
                ->description('No hay campaña activa')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning');
        }

        $total = Voter::where('campaign_id', $activeCampaign->id)->count();
        $lastWeek = Voter::where('campaign_id', $activeCampaign->id)
            ->whereBetween('created_at', [now()->subWeek(), now()])
            ->count();

        return Stat::make('Total de Votantes', number_format($total))
            ->description($lastWeek.' nuevos esta semana')
            ->descriptionIcon('heroicon-m-user-group')
            ->color('primary')
            ->chart($this->getVotersGrowthChart($activeCampaign->id));
    }

    protected function getConfirmedVotersStat(): Stat
    {
        $activeCampaign = Campaign::where('status', 'active')->first();

        if (! $activeCampaign) {
            return Stat::make('Votantes Confirmados', 0);
        }

        $confirmed = Voter::where('campaign_id', $activeCampaign->id)
            ->where('status', VoterStatus::CONFIRMED->value)
            ->count();

        $total = Voter::where('campaign_id', $activeCampaign->id)->count();
        $percentage = $total > 0 ? ($confirmed / $total) * 100 : 0;

        $color = match (true) {
            $percentage >= 80 => 'success',
            $percentage >= 50 => 'warning',
            default => 'danger',
        };

        return Stat::make('Votantes Confirmados', number_format($confirmed))
            ->description(round($percentage, 1).'% del total')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color($color);
    }

    protected function getActiveLeadersStat(): Stat
    {
        $activeCampaign = Campaign::where('status', 'active')->first();

        if (! $activeCampaign) {
            return Stat::make('Líderes Activos', 0);
        }

        // Líderes son usuarios que tienen votantes registrados
        $leadersCount = User::query()
            ->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $activeCampaign->id))
            ->whereHas('registeredVoters', fn ($q) => $q->where('campaign_id', $activeCampaign->id))
            ->count();

        $totalUsers = User::query()
            ->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $activeCampaign->id))
            ->count();

        if ($leadersCount > 0) {
            $totalVotersForLeaders = Voter::where('campaign_id', $activeCampaign->id)
                ->whereIn('registered_by', function ($query) use ($activeCampaign) {
                    $query->select('users.id')
                        ->from('users')
                        ->join('campaign_user', 'users.id', '=', 'campaign_user.user_id')
                        ->where('campaign_user.campaign_id', $activeCampaign->id);
                })
                ->count();

            $avgVoters = $totalVotersForLeaders / $leadersCount;
        } else {
            $avgVoters = 0;
        }

        return Stat::make('Líderes Activos', number_format($leadersCount))
            ->description(round($avgVoters, 1).' votantes/líder promedio')
            ->descriptionIcon('heroicon-m-star')
            ->color('success');
    }

    protected function getValidationProgressStat(): Stat
    {
        $activeCampaign = Campaign::where('status', 'active')->first();

        if (! $activeCampaign) {
            return Stat::make('Progreso de Validación', '0%');
        }

        $total = Voter::where('campaign_id', $activeCampaign->id)->count();
        $validated = Voter::where('campaign_id', $activeCampaign->id)
            ->whereNotNull('call_verified_at')
            ->count();

        $percentage = $total > 0 ? ($validated / $total) * 100 : 0;

        $color = match (true) {
            $percentage >= 90 => 'success',
            $percentage >= 70 => 'info',
            $percentage >= 40 => 'warning',
            default => 'danger',
        };

        return Stat::make('Progreso de Validación', round($percentage, 1).'%')
            ->description($validated.' de '.number_format($total).' validados')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color($color)
            ->chart($this->getValidationProgressChart($activeCampaign->id));
    }

    protected function getVotersGrowthChart(int $campaignId): array
    {
        return Voter::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('campaign_id', $campaignId)
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }

    protected function getValidationProgressChart(int $campaignId): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $validated = Voter::where('campaign_id', $campaignId)
                ->whereDate('call_verified_at', '<=', $date)
                ->count();
            $total = Voter::where('campaign_id', $campaignId)
                ->whereDate('created_at', '<=', $date)
                ->count();

            $days[] = $total > 0 ? ($validated / $total) * 100 : 0;
        }

        return $days;
    }
}
