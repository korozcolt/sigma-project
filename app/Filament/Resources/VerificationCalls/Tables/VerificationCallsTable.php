<?php

namespace App\Filament\Resources\VerificationCalls\Tables;

use App\Enums\CallResult;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VerificationCallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('voter.full_name')
                    ->label('Votante')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('caller.name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('call_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('attempt_number')
                    ->label('Intento #')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('call_duration')
                    ->label('Duración')
                    ->formatStateUsing(fn ($state) => gmdate('i:s', $state))
                    ->suffix(' min')
                    ->sortable(),

                TextColumn::make('call_result')
                    ->label('Resultado')
                    ->badge()
                    ->sortable(),

                IconColumn::make('survey_completed')
                    ->label('Encuesta')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('next_attempt_at')
                    ->label('Próximo Intento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('No programado')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('call_result')
                    ->label('Resultado')
                    ->options(CallResult::class)
                    ->multiple(),

                SelectFilter::make('caller_id')
                    ->label('Agente')
                    ->relationship('caller', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('survey_completed')
                    ->label('Encuesta Completada')
                    ->options([
                        true => 'Sí',
                        false => 'No',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('call_date', 'desc');
    }
}
