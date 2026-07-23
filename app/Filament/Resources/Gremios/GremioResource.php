<?php

namespace App\Filament\Resources\Gremios;

use App\Filament\Resources\Gremios\Pages\CreateGremio;
use App\Filament\Resources\Gremios\Pages\EditGremio;
use App\Filament\Resources\Gremios\Pages\ListGremios;
use App\Filament\Resources\Gremios\Schemas\GremioForm;
use App\Filament\Resources\Gremios\Tables\GremiosTable;
use App\Models\Gremio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GremioResource extends Resource
{
    protected static ?string $model = Gremio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Gremios';

    protected static UnitEnum|string|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'Gremio';

    protected static ?string $pluralModelLabel = 'Gremios';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return GremioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GremiosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGremios::route('/'),
            'create' => CreateGremio::route('/create'),
            'edit' => EditGremio::route('/{record}/edit'),
        ];
    }
}
