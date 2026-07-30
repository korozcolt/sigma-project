<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\User;
use App\Services\CampaignContext;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TerritorialOwnershipTable extends TableWidget
{
    protected static ?string $heading = 'Propiedad Territorial y de Equipos';

    protected static ?string $description = 'Quién es responsable de cada líder, territorio y cola de seguimiento';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(
                fn (): Builder => User::query()
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                        UserRole::COORDINATOR->value,
                        UserRole::LEADER->value,
                    ]))
                    ->when($activeCampaign, fn (Builder $q) => $q->whereHas(
                        'campaigns',
                        fn ($qq) => $qq->where('campaigns.id', $activeCampaign->id)
                    ))
                    ->with(['coordinator', 'municipality'])
                    ->withCount([
                        'leaders',
                        'registeredVoters' => fn ($q) => $activeCampaign
                            ? $q->where('campaign_id', $activeCampaign->id)
                            : $q,
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Usuario')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state),

                TextColumn::make('coordinator.name')
                    ->label('Coordinador Asignado')
                    ->placeholder('— (coordinador raíz)'),

                TextColumn::make('municipality.name')
                    ->label('Territorio (Municipio)')
                    ->placeholder('Sin municipio asignado'),

                TextColumn::make('leaders_count')
                    ->label('Líderes a Cargo')
                    ->badge(),

                TextColumn::make('registered_voters_count')
                    ->label('Apoyos Registrados')
                    ->badge()
                    ->color('success'),
            ])
            ->recordUrl(function (User $record): string {
                if ($record->hasRole(UserRole::COORDINATOR->value)) {
                    return VoterResource::getUrl('index', [
                        'tableFilters' => [
                            'registered_by' => ['values' => $record->leaders()->pluck('id')->push($record->id)->all()],
                        ],
                    ]);
                }

                return VoterResource::getUrl('index', [
                    'tableFilters' => [
                        'registered_by' => ['values' => [$record->id]],
                    ],
                ]);
            })
            ->defaultSort('leaders_count', 'desc')
            ->paginated([10, 25, 50]);
    }
}
