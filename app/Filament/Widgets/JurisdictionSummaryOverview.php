<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignScope;
use App\Models\Campaign;
use App\Models\Voter;
use App\Services\CampaignContext;
use App\Services\VoterTerritoryScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JurisdictionSummaryOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 8;

    protected ?string $heading = 'Resumen de Jurisdicción';

    protected ?string $description = 'Apoyos dentro vs. fuera del territorio objetivo de la campaña';

    public static function canView(): bool
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return true;
        }

        if ($activeCampaign->scope === CampaignScope::Nacional) {
            return false;
        }

        // Regional scope is not auto-nulled by Campaign::boot() — hide only when there is
        // truly no territorial reference to compare against (both fields null).
        return ! (is_null($activeCampaign->municipality_id) && is_null($activeCampaign->department_id));
    }

    protected function getStats(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                Stat::make('Dentro del Territorio', 0),
                Stat::make('Fuera del Territorio', 0),
            ];
        }

        $total = Voter::query()->where('campaign_id', $activeCampaign->id)->count();
        $inside = $this->insideCount($activeCampaign);
        $outside = $total - $inside;

        return [
            Stat::make('Dentro del Territorio', number_format($inside))
                ->description('Apoyos en el territorio objetivo')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success'),
            Stat::make('Fuera del Territorio', number_format($outside))
                ->description('Apoyos fuera del territorio objetivo')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('danger'),
        ];
    }

    private function insideCount(Campaign $activeCampaign): int
    {
        $territoryScope = new VoterTerritoryScope;

        if (! $territoryScope->isTerritoryDefined($activeCampaign)) {
            return 0;
        }

        return Voter::query()
            ->where('campaign_id', $activeCampaign->id)
            ->with('municipality')
            ->get()
            ->filter(fn (Voter $voter): bool => $territoryScope->isWithinCampaignScope($voter, $activeCampaign))
            ->count();
    }
}
