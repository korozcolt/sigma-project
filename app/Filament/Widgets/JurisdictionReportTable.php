<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignScope;
use App\Enums\UserRole;
use App\Exports\JurisdictionExport;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Voter;
use App\Services\CampaignContext;
use App\Services\VoterTerritoryScope;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class JurisdictionReportTable extends TableWidget
{
    protected static ?int $sort = 8;

    protected static ?string $heading = 'Informe de Jurisdicción';

    protected static ?string $description = 'Apoyos dentro vs. fuera del territorio objetivo de la campaña';

    protected int|string|array $columnSpan = 'full';

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

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();
        $territoryScope = new VoterTerritoryScope;

        return $table
            ->query(fn (): Builder => Voter::query()
                ->when(
                    $activeCampaign,
                    fn (Builder $query) => $query->where('campaign_id', $activeCampaign->id),
                    fn (Builder $query) => $query->whereRaw('1 = 0')
                )
                ->with('municipality'))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Apoyo')
                    ->searchable(),

                TextColumn::make('municipality.name')
                    ->label('Municipio'),

                TextColumn::make('jurisdiccion')
                    ->label('Jurisdicción')
                    ->state(function (Voter $record) use ($activeCampaign, $territoryScope): string {
                        if (! $activeCampaign) {
                            return 'N/A';
                        }

                        if (! $territoryScope->isTerritoryDefined($activeCampaign)) {
                            return 'N/A';
                        }

                        return $territoryScope->isWithinCampaignScope($record, $activeCampaign) ? 'Dentro' : 'Fuera';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Dentro' => 'success',
                        'Fuera' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(fn () => (new JurisdictionExport($activeCampaign))->download('informe-jurisdiccion.xlsx')),
            ])
            ->recordUrl(fn (Voter $record) => VoterResource::getUrl('view', ['record' => $record]));
    }
}
