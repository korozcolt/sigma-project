<?php

namespace App\Filament\Resources\Voters;

use App\Filament\Resources\Voters\Pages\CreateVoter;
use App\Filament\Resources\Voters\Pages\EditVoter;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Filament\Resources\Voters\Pages\ViewVoter;
use App\Filament\Resources\Voters\Schemas\VoterForm;
use App\Filament\Resources\Voters\Tables\VotersTable;
use App\Models\Voter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VoterResource extends Resource
{
    protected static ?string $model = Voter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Votantes';

    protected static UnitEnum|string|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'Votante';

    protected static ?string $pluralModelLabel = 'Votantes';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return VoterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VotersTable::configure($table);
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
            'index' => ListVoters::route('/'),
            'create' => CreateVoter::route('/create'),
            'view' => ViewVoter::route('/{record}'),
            'edit' => EditVoter::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
