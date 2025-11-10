<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_photo_path')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF')
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_vote_recorder')
                    ->label('Anotador')
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil-square')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_witness')
                    ->label('Testigo')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_special_coordinator')
                    ->label('Coord. Especial')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('campaigns_count')
                    ->label('Campañas')
                    ->counts('campaigns')
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-m-flag'),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('neighborhood.name')
                    ->label('Barrio')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registeredVoters_count')
                    ->label('Votantes')
                    ->counts('registeredVoters')
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-m-user-group'),

                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('campaigns')
                    ->label('Campaña')
                    ->relationship('campaigns', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('municipality_id')
                    ->label('Municipio')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('neighborhood_id')
                    ->label('Barrio')
                    ->relationship('neighborhood', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_vote_recorder')
                    ->label('Anotador')
                    ->placeholder('Todos')
                    ->trueLabel('Solo anotadores')
                    ->falseLabel('No anotadores')
                    ->queries(
                        true: fn ($query) => $query->where('is_vote_recorder', true),
                        false: fn ($query) => $query->where('is_vote_recorder', false),
                        blank: fn ($query) => $query,
                    ),

                TernaryFilter::make('is_witness')
                    ->label('Testigo Electoral')
                    ->placeholder('Todos')
                    ->trueLabel('Solo testigos')
                    ->falseLabel('No testigos')
                    ->queries(
                        true: fn ($query) => $query->where('is_witness', true),
                        false: fn ($query) => $query->where('is_witness', false),
                        blank: fn ($query) => $query,
                    ),

                TernaryFilter::make('is_special_coordinator')
                    ->label('Coordinador Especial')
                    ->placeholder('Todos')
                    ->trueLabel('Solo coordinadores especiales')
                    ->falseLabel('No coordinadores especiales')
                    ->queries(
                        true: fn ($query) => $query->where('is_special_coordinator', true),
                        false: fn ($query) => $query->where('is_special_coordinator', false),
                        blank: fn ($query) => $query,
                    ),
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
