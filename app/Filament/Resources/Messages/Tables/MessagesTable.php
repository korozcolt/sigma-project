<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('voter.full_name')
                    ->label('Votante')
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

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'scheduled' => 'info',
                        'sent' => 'warning',
                        'delivered' => 'success',
                        'read' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'scheduled' => 'Programado',
                        'sent' => 'Enviado',
                        'delivered' => 'Entregado',
                        'read' => 'Leído',
                        'failed' => 'Fallido',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('scheduled_for')
                    ->label('Programado Para')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Entregado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'scheduled' => 'Programado',
                        'sent' => 'Enviado',
                        'delivered' => 'Entregado',
                        'read' => 'Leído',
                        'failed' => 'Fallido',
                    ])
                    ->multiple(),

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

                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Desde'),
                        DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('resend')
                    ->label('Reenviar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->status, ['failed', 'sent']))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'pending',
                            'error_message' => null,
                        ]);
                    }),

                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markAsPending')
                        ->label('Marcar como Pendiente')
                        ->icon('heroicon-o-clock')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['status' => 'pending']);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
