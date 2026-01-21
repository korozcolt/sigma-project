<?php

namespace App\Filament\Resources\Leaders;

use App\Filament\Resources\Leaders\Pages\CreateLeader;
use App\Filament\Resources\Leaders\Pages\EditLeader;
use App\Filament\Resources\Leaders\Pages\ListLeaders;
use App\Filament\Resources\Leaders\Schemas\LeaderForm;
use App\Filament\Resources\Leaders\Tables\LeadersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LeaderResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Líderes';

    protected static UnitEnum|string|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'Líder';

    protected static ?string $pluralModelLabel = 'Líderes';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('leader');
    }

    public static function form(Schema $schema): Schema
    {
        return LeaderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaders::route('/'),
            'create' => CreateLeader::route('/create'),
            'edit' => EditLeader::route('/{record}/edit'),
        ];
    }
}
