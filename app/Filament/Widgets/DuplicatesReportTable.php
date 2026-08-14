<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Exports\DuplicatesExport;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Campaign;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * INTENTIONAL EXCEPTION TO CAMPAIGN ISOLATION (D-06).
 *
 * Every other widget/export added in Phase 04.1 is strictly scoped to the active
 * campaign. This ONE widget is not: a disputed cédula's sibling rows can legitimately
 * live under a DIFFERENT campaign than the one currently active (confirmed real-world
 * scenario, see the 02.1-10 cross-campaign duplicate_sequence bugfix in STATE.md and
 * VoterDuplicateAssignmentService::siblingsFor(), which already bypasses the campaign
 * global scope for the same reason). Do not "fix" this to add a campaign_id filter on
 * the sibling rows themselves — that would silently hide real disputes from admins.
 * The ROW SET (which document_number groups are shown) IS still gated to groups that
 * touch the active campaign; only the SIBLINGS WITHIN a shown group cross campaigns.
 */
class DuplicatesReportTable extends TableWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Informe de Duplicados';

    protected static ?string $description = 'Cédulas en disputa entre registros — incluye duplicados de otras campañas (ver nota)';

    protected int|string|array $columnSpan = 'full';

    /**
     * Document numbers with more than one Voter row system-wide (withoutGlobalScopes,
     * withTrashed) where at least one of those rows belongs to the given campaign — i.e.
     * cédulas actively in dispute that this campaign is party to. Shared with
     * RejectionsCountersOverview's "Duplicados" stat so both surfaces use the exact same
     * definition of "duplicate" (see the class docblock above for the D-06 cross-campaign
     * rationale — this method does not change that query's semantics).
     *
     * @return Collection<int, string>
     */
    public static function disputedDocumentNumbers(?Campaign $activeCampaign): Collection
    {
        if (! $activeCampaign) {
            return collect();
        }

        return Voter::withoutGlobalScopes()
            ->withTrashed()
            ->select('document_number')
            ->groupBy('document_number')
            ->havingRaw('COUNT(*) > 1')
            ->havingRaw('SUM(CASE WHEN campaign_id = ? THEN 1 ELSE 0 END) > 0', [$activeCampaign->id])
            ->pluck('document_number');
    }

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $documentNumbers = self::disputedDocumentNumbers($activeCampaign);

        return $table
            ->query(fn (): Builder => Voter::withoutGlobalScopes()
                ->withTrashed()
                ->when($documentNumbers->isNotEmpty(), fn ($q) => $q->whereIn('document_number', $documentNumbers), fn ($q) => $q->whereRaw('1 = 0'))
                ->with(['registeredBy.coordinator', 'campaign'])
                ->orderBy('document_number')
                ->orderBy('duplicate_sequence'))
            ->columns([
                TextColumn::make('display_suffix')
                    ->label('Cédula-Secuencia'),

                TextColumn::make('full_name')
                    ->label('Apoyo'),

                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),

                TextColumn::make('registeredBy.name')
                    ->label('Registrado por'),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(fn () => (new DuplicatesExport)->download('informe-duplicados.xlsx')),
            ])
            ->recordUrl(function (Voter $record) use ($activeCampaign): ?string {
                // D-06: sibling rows within a shown duplicate group can belong to a
                // DIFFERENT campaign than the active one — only link when this specific
                // row's own campaign matches, never route into another campaign's data.
                if (! $activeCampaign || $record->campaign_id !== $activeCampaign->id) {
                    return null;
                }

                return VoterResource::getUrl('view', ['record' => $record]);
            });
    }
}
