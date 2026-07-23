<?php

namespace App\Filament\Imports;

use App\Enums\VoterStatus;
use App\Models\Municipality;
use App\Models\Voter;
use App\Rules\DocumentNotBelongsToLeaderOrCoordinator;
use App\Services\CampaignContext;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Support\Number;

class ApoyoImporter extends Importer
{
    protected static ?string $model = Voter::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('document_number')
                ->label('Cédula')
                ->requiredMapping()
                ->guess(['cedula'])
                ->rules(['required', 'string', 'max:255', new DocumentNotBelongsToLeaderOrCoordinator]),
            ImportColumn::make('first_name')
                ->label('Nombre 1')
                ->requiredMapping()
                ->guess(['nombre1'])
                ->rules(['required', 'max:255']),
            ImportColumn::make('last_name')
                ->label('Apellido 1')
                ->requiredMapping()
                ->guess(['apellido1'])
                ->rules(['required', 'max:255']),
            ImportColumn::make('birth_date')
                ->label('Fecha de Nacimiento')
                ->guess(['fecha_nacimiento'])
                ->rules(['nullable', 'date']),
            ImportColumn::make('phone')
                ->label('Teléfono')
                ->requiredMapping()
                ->guess(['telefono'])
                ->rules(['required', 'max:255']),
            ImportColumn::make('neighborhood')
                ->label('Barrio')
                ->guess(['barrio'])
                ->relationship(resolveUsing: 'name'),
            ImportColumn::make('address')
                ->label('Dirección')
                ->guess(['direccion'])
                ->rules(['nullable', 'max:500']),
            ImportColumn::make('lugar_expedicion_cedula')
                ->label('Lugar de Expedición de Cédula')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('subcategoria')
                ->label('Subcategoría')
                ->guess(['subcategoria'])
                ->relationship(resolveUsing: 'name'),
            ImportColumn::make('gremio')
                ->label('Gremio')
                ->guess(['gremio'])
                ->relationship(resolveUsing: 'name'),
            ImportColumn::make('placa')
                ->label('Placa')
                ->rules(['nullable', 'max:20']),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('municipality_id')
                ->label('Municipio')
                ->options(fn () => Municipality::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->helperText('Todos los Apoyos de este archivo se asignarán a este municipio.'),
        ];
    }

    public function resolveRecord(): Voter
    {
        return new Voter;
    }

    protected function beforeCreate(): void
    {
        $this->record->campaign_id = CampaignContext::currentCampaignId();
        $this->record->municipality_id = $this->options['municipality_id'];
        $this->record->registered_by = auth()->id();
        $this->record->status = VoterStatus::PENDING_REVIEW;
        // duplicate_sequence + status(DUPLICATE) are assigned automatically by Voter's `creating` model hook (plan 02.1-03) - no manual call needed here.
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Tu importación de Apoyos ha finalizado y '.Number::format($import->successful_rows).' '.str('fila')->plural($import->successful_rows).' se importaron correctamente.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('fila')->plural($failedRowsCount).' fallaron al importar.';
        }

        return $body;
    }
}
