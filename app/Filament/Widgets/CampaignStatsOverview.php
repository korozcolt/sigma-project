<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return Stat::make('Total de Apoyos', 0)
                ->description('No hay campaña seleccionada')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning');
        }

        $total = $this->scopedVoterQuery($activeCampaign)->count();
        $lastWeek = $this->scopedVoterQuery($activeCampaign)
            ->whereBetween('created_at', [now()->subWeek(), now()])
            ->count();

        $stat = Stat::make('Total de Apoyos', number_format($total))
            ->description($lastWeek.' nuevos esta semana')
            ->descriptionIcon('heroicon-m-user-group')
            ->color('primary')
            ->chart($this->getVotersGrowthChart($activeCampaign->id));

        if ($url = $this->voterResourceUrl('index')) {
            $stat->url($url);
        }

        return $stat;
    }

    protected function getConfirmedVotersStat(): Stat
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return Stat::make('Apoyos Confirmados', 0);
        }

        $confirmed = $this->scopedVoterQuery($activeCampaign)
            ->where('status', VoterStatus::CONFIRMED->value)
            ->count();

        $total = $this->scopedVoterQuery($activeCampaign)->count();
        $percentage = $total > 0 ? ($confirmed / $total) * 100 : 0;

        $color = match (true) {
            $percentage >= 80 => 'success',
            $percentage >= 50 => 'warning',
            default => 'danger',
        };

        $stat = Stat::make('Apoyos Confirmados', number_format($confirmed))
            ->description(round($percentage, 1).'% del total')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color($color);

        if ($url = $this->voterResourceUrl('index', [
            'tableFilters' => [
                'status' => ['values' => [VoterStatus::CONFIRMED->value]],
            ],
        ])) {
            $stat->url($url);
        }

        return $stat;
    }

    protected function getActiveLeadersStat(): Stat
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return Stat::make('Líderes Activos', 0);
        }

        $user = Auth::user();

        if ($user?->hasRole(UserRole::LEADER->value)) {
            $ownVotersCount = $user->registeredVoters()->where('campaign_id', $activeCampaign->id)->count();

            return Stat::make('Mis Apoyos Registrados', number_format($ownVotersCount))
                ->descriptionIcon('heroicon-m-user')
                ->color('success');
        }

        if ($user?->hasRole(UserRole::COORDINATOR->value)) {
            $leadersCount = $user->leaders()->count();
            $totalVotersForLeaders = Voter::where('campaign_id', $activeCampaign->id)
                ->whereIn('registered_by', $user->leaders()->pluck('id'))
                ->count();

            $avgVoters = $leadersCount > 0 ? $totalVotersForLeaders / $leadersCount : 0;

            return Stat::make('Líderes Activos', number_format($leadersCount))
                ->description(round($avgVoters, 1).' apoyos/líder promedio')
                ->descriptionIcon('heroicon-m-star')
                ->color('success');
        }

        if ($user?->hasRole(UserRole::AREA_COORDINATOR->value)) {
            $teamCoordinatorIds = $user->teamCoordinatorUserIds();
            $leadersCount = User::whereIn('coordinator_user_id', $teamCoordinatorIds)->count();
            $totalVotersForLeaders = Voter::where('campaign_id', $activeCampaign->id)
                ->whereIn('registered_by', User::whereIn('coordinator_user_id', $teamCoordinatorIds)->pluck('id'))
                ->count();

            $avgVoters = $leadersCount > 0 ? $totalVotersForLeaders / $leadersCount : 0;

            return Stat::make('Líderes Activos', number_format($leadersCount))
                ->description(round($avgVoters, 1).' apoyos/líder promedio')
                ->descriptionIcon('heroicon-m-star')
                ->color('success');
        }

        // Líderes son usuarios que tienen apoyos registrados
        $leadersCount = User::query()
            ->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $activeCampaign->id))
            ->whereHas('registeredVoters', fn ($q) => $q->where('campaign_id', $activeCampaign->id))
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
            ->description(round($avgVoters, 1).' apoyos/líder promedio')
            ->descriptionIcon('heroicon-m-star')
            ->color('success');
    }

    protected function getValidationProgressStat(): Stat
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return Stat::make('Progreso de Validación', '0%');
        }

        $total = $this->scopedVoterQuery($activeCampaign)->count();
        $validated = $this->scopedVoterQuery($activeCampaign)
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
        $campaign = Campaign::find($campaignId);

        return $this->scopedVoterQuery($campaign)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }

    protected function getValidationProgressChart(int $campaignId): array
    {
        $campaign = Campaign::find($campaignId);

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $validated = $this->scopedVoterQuery($campaign)
                ->whereDate('call_verified_at', '<=', $date)
                ->count();
            $total = $this->scopedVoterQuery($campaign)
                ->whereDate('created_at', '<=', $date)
                ->count();

            $days[] = $total > 0 ? ($validated / $total) * 100 : 0;
        }

        return $days;
    }

    private function scopedVoterQuery(Campaign $campaign): Builder
    {
        $user = Auth::user();
        $query = Voter::where('campaign_id', $campaign->id);

        if ($user?->hasRole(UserRole::LEADER->value)) {
            return $query->where('registered_by', $user->id);
        }

        if ($user?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value])) {
            return $query->whereIn('registered_by', User::whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())->pluck('id'));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function voterResourceUrl(string $name, array $parameters = []): ?string
    {
        $panelId = Filament::getCurrentOrDefaultPanel()?->getId();

        if (! $panelId || ! Route::has("filament.{$panelId}.resources.voters.{$name}")) {
            return null;
        }

        return VoterResource::getUrl($name, $parameters);
    }
}
