<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Exports\ApoyosLideresCoordinadoresExport;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ApoyosLideresCoordinadoresTable extends TableWidget
{
    protected static ?int $sort = 9;

    protected static ?string $heading = 'Apoyos + Líderes + Coordinadores (Exportación Plana)';

    protected static ?string $description = 'Vista previa del CSV plano combinado: una fila por apoyo con los datos de su líder y coordinador';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(fn (): Builder => Voter::query()
                ->when(
                    $activeCampaign,
                    fn (Builder $query) => $query->where('campaign_id', $activeCampaign->id),
                    fn (Builder $query) => $query->whereRaw('1 = 0')
                )
                ->with(['registeredBy.coordinator', 'municipality', 'gremio', 'subcategoria']))
            ->columns([
                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Apoyo'),

                TextColumn::make('registeredBy.name')
                    ->label('Líder/Registrador'),

                TextColumn::make('coordinador')
                    ->label('Coordinador')
                    ->state(function (Voter $record): string {
                        $registrador = $record->registeredBy;

                        if ($registrador?->hasRole(UserRole::COORDINATOR->value)) {
                            return $registrador->name;
                        }

                        return $registrador?->coordinator?->name ?? 'N/A';
                    }),

                TextColumn::make('municipality.name')
                    ->label('Municipio'),

                TextColumn::make('gremio.name')
                    ->label('Gremio')
                    ->toggleable(),

                TextColumn::make('subcategoria.name')
                    ->label('Subcategoría')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('gremio_id')
                    ->relationship('gremio', 'name')
                    ->label('Gremio'),

                SelectFilter::make('subcategoria_id')
                    ->relationship('subcategoria', 'name')
                    ->label('Subcategoría'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar CSV plano')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(fn () => (new ApoyosLideresCoordinadoresExport($activeCampaign?->id))->download('apoyos-lideres-coordinadores.xlsx')),
            ])
            ->recordUrl(fn (Voter $record) => VoterResource::getUrl('view', ['record' => $record]));
    }
}
