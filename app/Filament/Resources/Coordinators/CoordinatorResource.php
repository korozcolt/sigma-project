<?php

namespace App\Filament\Resources\Coordinators;

use App\Filament\Resources\Coordinators\Pages\CreateCoordinator;
use App\Filament\Resources\Coordinators\Pages\EditCoordinator;
use App\Filament\Resources\Coordinators\Pages\ListCoordinators;
use App\Filament\Resources\Coordinators\Schemas\CoordinatorForm;
use App\Filament\Resources\Coordinators\Tables\CoordinatorsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CoordinatorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Coordinadores';

    protected static UnitEnum|string|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'Coordinador';

    protected static ?string $pluralModelLabel = 'Coordinadores';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('coordinator');
    }

    public static function form(Schema $schema): Schema
    {
        return CoordinatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoordinatorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoordinators::route('/'),
            'create' => CreateCoordinator::route('/create'),
            'edit' => EditCoordinator::route('/{record}/edit'),
        ];
    }
}
