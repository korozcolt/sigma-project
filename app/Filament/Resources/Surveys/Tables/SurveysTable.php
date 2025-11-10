<?php

namespace App\Filament\Resources\Surveys\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SurveysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description),

                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('version')
                    ->label('Versión')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('questions_count')
                    ->label('Preguntas')
                    ->counts('questions')
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        1 => 'Activas',
                        0 => 'Inactivas',
                    ]),

                TrashedFilter::make()
                    ->label('Eliminadas'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                    RestoreBulkAction::make()
                        ->label('Restaurar seleccionadas'),
                    ForceDeleteBulkAction::make()
                        ->label('Eliminar permanentemente'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
