<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectionEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ElectionEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre del Evento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'simulation' => 'Simulacro',
                        'real' => 'Día D Real',
                        default => $state,
                    })
                    ->colors([
                        'simulation' => Color::Blue,
                        'real' => Color::Red,
                    ]),

                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn ($record) => $record->isToday() ? 'HOY' : null),

                TextColumn::make('start_time')
                    ->label('Inicio')
                    ->time('H:i')
                    ->placeholder('Sin restricción')
                    ->toggleable(),

                TextColumn::make('end_time')
                    ->label('Fin')
                    ->time('H:i')
                    ->placeholder('Sin restricción')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueColor(Color::Green)
                    ->falseColor(Color::Gray),

                BadgeColumn::make('status_badge')
                    ->label('Estado')
                    ->colors([
                        'Activo' => Color::Green,
                        'Programado' => Color::Blue,
                        'Realizado' => Color::Gray,
                    ]),

                TextColumn::make('simulation_number')
                    ->label('Nro. Simulacro')
                    ->numeric()
                    ->sortable()
                    ->placeholder('N/A')
                    ->toggleable(),

                TextColumn::make('voteRecords_count')
                    ->label('Votos Registrados')
                    ->counts('voteRecords')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'simulation' => 'Simulacro',
                        'real' => 'Día D Real',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        '1' => 'Activo',
                        '0' => 'Inactivo',
                    ]),

                SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
