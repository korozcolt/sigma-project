<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Exports\TopCoordinatorsExport;
use App\Models\User;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopCoordinatorsTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Cobertura y Ranking de Coordinadores';

    protected static ?string $description = 'Líderes asignados y apoyos válidos del equipo por coordinador';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(fn (): Builder => User::query()
                ->role(UserRole::COORDINATOR->value)
                ->when($activeCampaign, function (Builder $query) use ($activeCampaign) {
                    $query->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $activeCampaign->id))
                        ->withCount(['leaders as leaders_count'])
                        ->withCount(['leaders as apoyos_equipo_count' => function (Builder $q) use ($activeCampaign) {
                            $q->join('voters', 'voters.registered_by', '=', $q->qualifyColumn('id'))
                                ->where('voters.campaign_id', $activeCampaign->id)
                                ->where('voters.status', '!=', VoterStatus::DUPLICATE->value);
                        }])
                        ->orderByDesc('apoyos_equipo_count');
                }, fn (Builder $query) => $query->whereRaw('1 = 0')))
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
                    ->label('Coordinador')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->toggleable(),

                TextColumn::make('leaders_count')
                    ->label('Líderes Asignados')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('apoyos_equipo_count')
                    ->label('Apoyos del Equipo (Válidos)')
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
                    ->action(fn () => (new TopCoordinatorsExport($activeCampaign?->id))->download('ranking-coordinadores.xlsx')),
            ]);
    }
}
