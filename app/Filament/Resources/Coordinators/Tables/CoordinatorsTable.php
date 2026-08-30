<?php

namespace App\Filament\Resources\Coordinators\Tables;

use App\Filament\Schemas\MetadataAssignment;
use App\Filament\Schemas\MetadataTableColumns;
use App\Filament\Schemas\MetadataTableFilter;
use App\Services\MetadataAssignmentService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class CoordinatorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => app(MetadataAssignmentService::class)
                ->withCurrentValueSelects($query)
                ->with('areaCoordinator'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->copyMessageDuration(1500)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('areaCoordinator.name')
                    ->label('Articulador')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('leaders_count')
                    ->counts('leaders')
                    ->label('Líderes')
                    ->sortable()
                    ->visible(fn (): bool => Schema::hasColumn('users', 'coordinator_user_id'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ...MetadataTableColumns::make(),
            ])
            ->filters([
                MetadataTableFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MetadataAssignment::bulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
