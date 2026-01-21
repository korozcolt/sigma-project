<?php

namespace App\Filament\Resources\Invitations\Tables;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('leader.name')
                    ->label('Líder')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('coordinator.name')
                    ->label('Coordinador')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('leader.coordinator.name')
                    ->label('Coordinador (del líder)')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('registration_url')
                    ->label('Enlace')
                    ->state(fn ($record) => $record->getRegistrationUrl())
                    ->copyable()
                    ->copyMessage('Enlace copiado')
                    ->copyMessageDuration(1500)
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Activa',
                        'accepted' => 'Usada',
                        'expired' => 'Expirada',
                        'cancelled' => 'Desactivada',
                        default => $state,
                    }),
                
                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'default'),

                TextColumn::make('invitedBy.name')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Activa',
                        'accepted' => 'Usada',
                        'expired' => 'Expirada',
                        'cancelled' => 'Desactivada',
                    ]),
                
                Tables\Filters\SelectFilter::make('campaign')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\Filter::make('expired')
                    ->label('Expiradas')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now())),
                
                Tables\Filters\Filter::make('pending')
                    ->label('Activas')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'pending')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
