<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Exports\TopPollingPlacesExport;
use App\Models\PollingPlace;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopPollingPlacesTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Ranking de Puestos de Votación';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(fn (): Builder => PollingPlace::query()
                ->when($activeCampaign, function (Builder $query) use ($activeCampaign) {
                    $query->whereHas('voters', fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                        ->where('status', '!=', VoterStatus::DUPLICATE->value))
                        ->withCount(['voters as apoyos_count' => fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                            ->where('status', '!=', VoterStatus::DUPLICATE->value)])
                        ->orderByDesc('apoyos_count');
                }, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->with('municipality')
                ->limit(10))
            ->columns([
                TextColumn::make('ranking')
                    ->label('#')
                    ->state(fn ($rowLoop) => $rowLoop->iteration)
                    ->badge()
                    ->color(fn ($rowLoop) => match ($rowLoop->iteration) {
                        1 => 'warning',
                        2 => 'gray',
                        3 => 'orange',
                        default => 'primary',
                    })
                    ->icon(fn ($rowLoop) => match ($rowLoop->iteration) {
                        1 => 'heroicon-m-trophy',
                        2 => 'heroicon-m-star',
                        3 => 'heroicon-m-star',
                        default => null,
                    }),

                TextColumn::make('name')
                    ->label('Puesto de Votación')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('municipality.name')
                    ->label('Municipio'),

                TextColumn::make('apoyos_count')
                    ->label('Apoyos Válidos')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'info',
                        $state >= 25 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(fn () => (new TopPollingPlacesExport($activeCampaign?->id))->download('ranking-puestos-votacion.xlsx')),
            ]);
    }

    protected function getTableDescription(): ?string
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return null;
        }

        $sinPuesto = Voter::query()
            ->where('campaign_id', $activeCampaign->id)
            ->where('status', '!=', VoterStatus::DUPLICATE->value)
            ->whereNull('polling_place_id')
            ->count();

        return "{$sinPuesto} apoyos válidos sin puesto de votación asignado";
    }
}
