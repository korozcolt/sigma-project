<?php

namespace App\Filament\Resources\Municipalities;

use App\Filament\Resources\Municipalities\Pages\CreateMunicipality;
use App\Filament\Resources\Municipalities\Pages\EditMunicipality;
use App\Filament\Resources\Municipalities\Pages\ListMunicipalities;
use App\Filament\Resources\Municipalities\Schemas\MunicipalityForm;
use App\Filament\Resources\Municipalities\Tables\MunicipalitiesTable;
use App\Models\Municipality;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MunicipalityResource extends Resource
{
    protected static ?string $model = Municipality::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Municipios';

    protected static UnitEnum|string|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'Municipio';

    protected static ?string $pluralModelLabel = 'Municipios';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MunicipalityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MunicipalitiesTable::configure($table);
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
            'index' => ListMunicipalities::route('/'),
            'create' => CreateMunicipality::route('/create'),
            'edit' => EditMunicipality::route('/{record}/edit'),
        ];
    }
}
