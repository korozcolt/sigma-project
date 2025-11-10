<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Enums\CampaignScope;
use App\Enums\CampaignStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('candidate_name')
                    ->label('Candidato')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('scope')
                    ->label('Alcance')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Fecha Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('election_date')
                    ->label('Fecha Elección')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('users_count')
                    ->label('Equipo')
                    ->counts('users')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('neighborhoods_count')
                    ->label('Barrios')
                    ->counts('neighborhoods')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(CampaignStatus::class),
                SelectFilter::make('scope')
                    ->label('Alcance')
                    ->options(CampaignScope::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
