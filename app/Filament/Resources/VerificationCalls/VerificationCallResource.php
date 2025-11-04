<?php

namespace App\Filament\Resources\VerificationCalls;

use App\Filament\Resources\VerificationCalls\Pages\CreateVerificationCall;
use App\Filament\Resources\VerificationCalls\Pages\EditVerificationCall;
use App\Filament\Resources\VerificationCalls\Pages\ListVerificationCalls;
use App\Filament\Resources\VerificationCalls\Schemas\VerificationCallForm;
use App\Filament\Resources\VerificationCalls\Tables\VerificationCallsTable;
use App\Models\VerificationCall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VerificationCallResource extends Resource
{
    protected static ?string $model = VerificationCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Llamadas de Verificación';

    protected static ?string $modelLabel = 'Llamada';

    protected static ?string $pluralModelLabel = 'Llamadas de Verificación';

    protected static string|UnitEnum|null $navigationGroup = 'Call Center';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return VerificationCallForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerificationCallsTable::configure($table);
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
            'index' => ListVerificationCalls::route('/'),
            'create' => CreateVerificationCall::route('/create'),
            'edit' => EditVerificationCall::route('/{record}/edit'),
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
