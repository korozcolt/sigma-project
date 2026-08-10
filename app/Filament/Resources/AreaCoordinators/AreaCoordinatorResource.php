<?php

namespace App\Filament\Resources\AreaCoordinators;

use App\Filament\Resources\AreaCoordinators\Pages\CreateAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\EditAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\ListAreaCoordinators;
use App\Filament\Resources\AreaCoordinators\Schemas\AreaCoordinatorForm;
use App\Filament\Resources\AreaCoordinators\Tables\AreaCoordinatorsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AreaCoordinatorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Articuladores';

    protected static UnitEnum|string|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'Articulador';

    protected static ?string $pluralModelLabel = 'Articuladores';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('area_coordinator');
    }

    public static function form(Schema $schema): Schema
    {
        return AreaCoordinatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AreaCoordinatorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreaCoordinators::route('/'),
            'create' => CreateAreaCoordinator::route('/create'),
            'edit' => EditAreaCoordinator::route('/{record}/edit'),
        ];
    }
}
