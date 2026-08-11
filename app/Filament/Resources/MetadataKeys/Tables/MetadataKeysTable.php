<?php

namespace App\Filament\Resources\MetadataKeys\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MetadataKeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Nombre visible')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label('Llave')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => [
                        'numeric' => 'Numérico',
                        'text' => 'Texto',
                        'date' => 'Fecha',
                        'select' => 'Selección',
                    ][$state] ?? $state),
                TextColumn::make('values_count')
                    ->label('Asignaciones')
                    ->counts('values')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activa')
                    ->placeholder('Todas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('label');
    }
}
