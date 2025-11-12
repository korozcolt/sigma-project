<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Enums\VoterStatus;
use App\Exports\VotersExport;
use App\Filament\Resources\Voters\VoterResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListVoters extends ListRecords
{
    protected static string $resource = VoterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('exportCurrent')
                ->label('Exportar vista actual')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function (array $data, $livewire) {
                    $query = null;

                    if (method_exists($livewire, 'getFilteredSortedTableQuery')) {
                        $query = $livewire->getFilteredSortedTableQuery();
                    } elseif (method_exists($livewire, 'getFilteredTableQuery')) {
                        $query = $livewire->getFilteredTableQuery();
                        if (method_exists($livewire, 'applySortingToTableQuery')) {
                            $livewire->applySortingToTableQuery($query);
                        }
                    }

                    $export = new VotersExport(
                        queryBuilder: $query,
                    );

                    return $export->download('votantes.xlsx');
                })
                ->tooltip('Exporta exactamente lo que ves en la tabla'),

            Action::make('export')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalHeading('Exportar Votantes')
                ->modalSubmitActionLabel('Descargar')
                ->form([
                    Select::make('campaign_id')
                        ->label('Campaña')
                        ->options(fn () => \App\Models\Campaign::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->multiple(),

                    Select::make('municipality_id')
                        ->label('Municipio')
                        ->options(fn () => \App\Models\Municipality::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->multiple(),

                    Select::make('neighborhood_id')
                        ->label('Barrio')
                        ->options(fn () => \App\Models\Neighborhood::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->multiple(),

                    Select::make('status')
                        ->label('Estado')
                        ->options(VoterStatus::class)
                        ->searchable()
                        ->multiple(),

                    Select::make('registered_by')
                        ->label('Registrado por')
                        ->options(fn () => \App\Models\User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->multiple(),

                    DatePicker::make('created_from')
                        ->label('Creado desde')
                        ->closeOnDateSelection(),

                    DatePicker::make('created_until')
                        ->label('Creado hasta')
                        ->closeOnDateSelection(),
                ])
                ->action(function (array $data) {
                    $statuses = $data['status'] ?? null;
                    if (is_array($statuses)) {
                        $statuses = array_map(function ($s) {
                            return $s instanceof BackedEnum ? $s->value : $s;
                        }, $statuses);
                    } elseif ($statuses instanceof BackedEnum) {
                        $statuses = $statuses->value;
                    }

                    $export = new VotersExport(
                        $data['campaign_id'] ?? null,
                        $data['municipality_id'] ?? null,
                        $data['neighborhood_id'] ?? null,
                        $statuses,
                        $data['registered_by'] ?? null,
                        queryBuilder: null,
                        createdFrom: $data['created_from'] ?? null,
                        createdUntil: $data['created_until'] ?? null,
                    );

                    return $export->download('votantes.xlsx');
                }),
        ];
    }
}
