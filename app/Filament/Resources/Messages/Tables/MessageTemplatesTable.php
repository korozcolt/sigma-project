<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class MessageTemplatesTable
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

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'birthday' => 'success',
                        'reminder' => 'warning',
                        'campaign' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'birthday' => 'Cumpleaños',
                        'reminder' => 'Recordatorio',
                        'campaign' => 'Campaña',
                        'custom' => 'Personalizado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'sms' => 'info',
                        'email' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'whatsapp' => 'heroicon-o-chat-bubble-bottom-center-text',
                        'sms' => 'heroicon-o-device-phone-mobile',
                        'email' => 'heroicon-o-envelope',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        'email' => 'Email',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_per_voter_per_day')
                    ->label('Límite Diario')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->suffix(' msg/votante'),

                Tables\Columns\TextColumn::make('max_per_campaign_per_hour')
                    ->label('Límite Horario')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->suffix(' msg/hora'),

                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Mensajes')
                    ->counts('messages')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'birthday' => 'Cumpleaños',
                        'reminder' => 'Recordatorio',
                        'campaign' => 'Campaña',
                        'custom' => 'Personalizado',
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

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->native(false),

                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('preview')
                    ->label('Vista Previa')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Vista Previa de la Plantilla')
                    ->modalContent(fn ($record) => view('filament.modals.template-preview', [
                        'template' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $newTemplate = $record->replicate();
                        $newTemplate->name = $record->name.' (Copia)';
                        $newTemplate->is_active = false;
                        $newTemplate->save();
                    }),

                Action::make('toggle_status')
                    ->label(fn ($record) => $record->is_active ? 'Desactivar' : 'Activar')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => ! $record->is_active]);
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                        }),
                    BulkAction::make('deactivate')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
