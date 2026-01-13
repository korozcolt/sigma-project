<?php

namespace App\Filament\Resources\Voters\Tables;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Voter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VotersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre Completo')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight('bold')
                    ->description(function (Voter $record): ?string {
                        if ($record->isSystemUser()) {
                            $roles = $record->user->roles->pluck('name')->map(function ($roleName) {
                                $userRole = UserRole::tryFrom($roleName);
                                if (! $userRole) {
                                    return $roleName;
                                }

                                $emoji = match ($userRole) {
                                    UserRole::SUPER_ADMIN => '👑',
                                    UserRole::ADMIN_CAMPAIGN => '🎯',
                                    UserRole::COORDINATOR => '📍',
                                    UserRole::LEADER => '⭐',
                                    UserRole::REVIEWER => '📞',
                                };

                                return $emoji.' '.$userRole->getLabel();
                            })->join(', ');

                            return $roles;
                        }

                        return null;
                    }),

                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Documento copiado'),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (VoterStatus $state): string => match ($state) {
                        VoterStatus::PENDING_REVIEW => 'gray',
                        VoterStatus::REJECTED_CENSUS => 'danger',
                        VoterStatus::VERIFIED_CENSUS => 'info',
                        VoterStatus::CORRECTION_REQUIRED => 'warning',
                        VoterStatus::VERIFIED_CALL => 'success',
                        VoterStatus::CONFIRMED => 'success',
                        VoterStatus::VOTED => 'primary',
                        VoterStatus::DID_NOT_VOTE => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('neighborhood.name')
                    ->label('Barrio')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registeredBy.name')
                    ->label('Registrado por')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('census_validated_at')
                    ->label('Validado Censo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('call_verified_at')
                    ->label('Verificado Llamada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('confirmed_at')
                    ->label('Confirmado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('voted_at')
                    ->label('Votó')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(VoterStatus::class)
                    ->multiple()
                    ->preload(),

                SelectFilter::make('municipality_id')
                    ->label('Municipio')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('neighborhood_id')
                    ->label('Barrio')
                    ->relationship('neighborhood', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('registered_by')
                    ->label('Registrado por')
                    ->relationship('registeredBy', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession();
    }
}
