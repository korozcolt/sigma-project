<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PollingPlaceSource;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FallbackSourceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Procedencia del Puesto de Votación';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                Stat::make('Apoyos en Fuente de Respaldo', 0)->color('warning'),
            ];
        }

        $fallbackCount = Voter::where('campaign_id', $activeCampaign->id)
            ->whereNotNull('polling_place_source')
            ->where('polling_place_source', '!=', PollingPlaceSource::LIVE->value)
            ->count();

        return [
            Stat::make('Apoyos en Fuente de Respaldo', number_format($fallbackCount))
                ->description('Puesto de votación resuelto por reconstrucción, snapshot nacional o manual (no verificado en vivo)')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color($fallbackCount > 0 ? 'warning' : 'success')
                ->url(VoterResource::getUrl('index', [
                    'tableFilters' => [
                        'polling_place_source' => ['values' => [
                            PollingPlaceSource::DB_RECONSTRUCTION->value,
                            PollingPlaceSource::SNAPSHOT->value,
                            PollingPlaceSource::MANUAL->value,
                        ]],
                    ],
                ])),
        ];
    }
}
