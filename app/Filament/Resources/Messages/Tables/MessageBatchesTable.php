<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class MessageBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template.name')
                    ->label('Plantilla')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'sms' => 'info',
                        'email' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        'email' => 'Email',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('total_recipients')
                    ->label('Destinatarios')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progreso')
                    ->getStateUsing(fn ($record) => $record->getProgressPercentage().'%')
                    ->badge()
                    ->color(fn ($record) => $record->getProgressPercentage() >= 100 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('sent_count')
                    ->label('Enviados')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('failed_count')
                    ->label('Fallidos')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivered_count')
                    ->label('Entregados')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Tasa de Éxito')
                    ->getStateUsing(fn ($record) => $record->getSuccessRate().'%')
                    ->badge()
                    ->color(fn ($record) => $record->getSuccessRate() >= 80 ? 'success' : ($record->getSuccessRate() >= 50 ? 'warning' : 'danger'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scheduled_for')
                    ->label('Programado Para')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        'email' => 'Email',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
                DeleteAction::make()
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'failed'])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
