<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Enums\VoterStatus;
use App\Exports\VotersExport;
use App\Filament\Resources\Voters\VoterResource;
use App\Services\CampaignContext;
use App\Services\VoterDuplicateReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

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
                        ->multiple()
                        ->default(fn () => CampaignContext::currentCampaignId() ? [CampaignContext::currentCampaignId()] : [])
                        ->visible(fn (): bool => CampaignContext::isSuperAdmin()),

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

            Action::make('duplicatesReport')
                ->label('Reporte de Duplicados')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('warning')
                ->modalHeading('Reporte de duplicados por cédula')
                ->modalSubmitActionLabel('Generar')
                ->form([
                    Select::make('resultado')
                        ->label('Resultado')
                        ->options([
                            'encontrados' => 'Solo encontrados',
                            'no_encontrados' => 'Solo no encontrados',
                            'todos' => 'Encontrados + no encontrados',
                        ])
                        ->default('encontrados')
                        ->required(),
                    FileUpload::make('cedulas_csv')
                        ->label('CSV de cédulas')
                        ->disk('local')
                        ->directory('reports')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->helperText('CSV con una sola columna: cedula.')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $campaignId = CampaignContext::currentCampaignId();

                    if (! $campaignId) {
                        Notification::make()
                            ->title('Seleccione una campaña activa')
                            ->danger()
                            ->send();

                        return null;
                    }

                    $path = $data['cedulas_csv'] ?? null;
                    if (! $path) {
                        Notification::make()
                            ->title('No se encontró el archivo cargado')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (! Storage::disk('local')->exists($path)) {
                        Notification::make()
                            ->title('El archivo cargado ya no está disponible')
                            ->danger()
                            ->send();

                        return null;
                    }

                    $report = new VoterDuplicateReport();
                    $documentNumbers = $report->parseDocumentNumbers($path, 'local');

                    if (empty($documentNumbers)) {
                        Notification::make()
                            ->title('El archivo no tiene cédulas válidas')
                            ->warning()
                            ->send();

                        return null;
                    }

                    $resultMode = $data['resultado'] ?? 'encontrados';
                    $includeFound = $resultMode === 'encontrados' || $resultMode === 'todos';
                    $includeMissing = $resultMode === 'no_encontrados' || $resultMode === 'todos';

                    $rows = $report->buildRows(
                        $documentNumbers,
                        $campaignId,
                        includeFound: $includeFound,
                        includeMissing: $includeMissing
                    );

                    $filename = 'reporte-duplicados-' . now()->format('Ymd-His') . '.csv';

                    return response()->streamDownload(function () use ($rows) {
                        $handle = fopen('php://output', 'w');
                        if (! $handle) {
                            return;
                        }

                        fwrite($handle, "\xEF\xBB\xBF");
                        fputcsv($handle, ['cedula', 'lider', 'campana']);

                        foreach ($rows as $row) {
                            fputcsv($handle, $row);
                        }

                        fclose($handle);
                    }, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
        ];
    }
}
