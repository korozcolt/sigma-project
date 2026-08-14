<?php

namespace App\Filament\Widgets;

use App\Enums\CallResult;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class RejectionsCountersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Contadores de Rechazos';

    protected function getStats(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                Stat::make('Rechazados', 0)
                    ->description('No hay campaña seleccionada')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
                Stat::make('Duplicados', 0),
                Stat::make('Fuera de Jurisdicción', 0),
            ];
        }

        $rejectionCallResults = [
            CallResult::REJECTED->value,
            CallResult::INVALID_NUMBER->value,
            CallResult::NOT_INTERESTED->value,
        ];

        $rechazados = Voter::query()
            ->where('campaign_id', $activeCampaign->id)
            ->where(fn (Builder $q) => $q
                ->whereIn('status', [
                    VoterStatus::REJECTED_CENSUS->value,
                    VoterStatus::CENSUS_NOT_FOUND->value,
                    VoterStatus::CORRECTION_REQUIRED->value,
                ])
                ->orWhereHas('verificationCalls', fn ($q2) => $q2->whereIn('call_result', $rejectionCallResults)))
            ->count();

        $duplicados = DuplicatesReportTable::disputedDocumentNumbers($activeCampaign)->count();

        $fueraJurisdiccion = Voter::query()
            ->where('campaign_id', $activeCampaign->id)
            ->where('status', VoterStatus::REJECTED_OUT_OF_SCOPE->value)
            ->count();

        return [
            Stat::make('Rechazados', number_format($rechazados))
                ->description('Rechazados en censo o llamada de verificación')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->url(VoterResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['values' => [
                            VoterStatus::REJECTED_CENSUS->value,
                            VoterStatus::CENSUS_NOT_FOUND->value,
                            VoterStatus::CORRECTION_REQUIRED->value,
                        ]],
                    ],
                ])),

            Stat::make('Duplicados', number_format($duplicados))
                ->description('Cédulas duplicadas activas en disputa (incluye otras campañas)')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('warning'),
            // No ->url(): "duplicados en disputa" is a cross-campaign document_number-group
            // criterion (see DuplicatesReportTable::disputedDocumentNumbers()), not a single
            // Voter::status value VoterResource's table filter can express. The one place that
            // DOES render this data (DuplicatesReportTable, on /reports) requires the
            // REPORTS_VIEWER role, which not every viewer of this widget on /admin will have —
            // linking there could 403. Dropping the link is safer than a broken or wrong one.

            Stat::make('Fuera de Jurisdicción', number_format($fueraJurisdiccion))
                ->description('Rechazados por quedar fuera del alcance territorial')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('danger')
                ->url(VoterResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['values' => [VoterStatus::REJECTED_OUT_OF_SCOPE->value]],
                    ],
                ])),
        ];
    }
}
