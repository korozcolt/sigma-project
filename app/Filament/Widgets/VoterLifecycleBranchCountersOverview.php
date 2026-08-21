<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VoterLifecycleBranchCountersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 23;

    protected ?string $heading = 'Estados Alternos y Terminales';

    // D-02/D-03: branch/terminal VoterStatus states shown here instead of forced into
    // VoterHappyPathFunnelChart's funnel shape. DID_NOT_VOTE lives here (D-03), not as a 5th
    // funnel stage - a voter who reached CONFIRMED but didn't vote is a branch outcome.
    private const BRANCH_STATUSES = [
        VoterStatus::REJECTED_CENSUS,
        VoterStatus::REJECTED_OUT_OF_SCOPE,
        VoterStatus::CENSUS_NOT_FOUND,
        VoterStatus::DUPLICATE,
        VoterStatus::CORRECTION_REQUIRED,
        VoterStatus::DID_NOT_VOTE,
    ];

    protected function getStats(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return array_map(
                fn (VoterStatus $status) => Stat::make($status->getLabel(), 0)
                    ->description('No hay campaña seleccionada')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
                self::BRANCH_STATUSES,
            );
        }

        return array_map(function (VoterStatus $status) use ($activeCampaign) {
            $count = Voter::query()
                ->where('campaign_id', $activeCampaign->id)
                ->where('status', $status->value)
                ->count();

            return Stat::make($status->getLabel(), number_format($count))
                ->description($status->getDescription())
                ->descriptionIcon($status->getIcon())
                ->color($status->getColor())
                ->url(VoterResource::getUrl('index', [
                    'tableFilters' => ['status' => ['values' => [$status->value]]],
                ]));
        }, self::BRANCH_STATUSES);
    }
}
