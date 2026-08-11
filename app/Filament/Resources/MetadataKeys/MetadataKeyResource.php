<?php

namespace App\Filament\Resources\MetadataKeys;

use App\Filament\Resources\MetadataKeys\Pages\CreateMetadataKey;
use App\Filament\Resources\MetadataKeys\Pages\EditMetadataKey;
use App\Filament\Resources\MetadataKeys\Pages\ListMetadataKeys;
use App\Filament\Resources\MetadataKeys\Schemas\MetadataKeyForm;
use App\Filament\Resources\MetadataKeys\Tables\MetadataKeysTable;
use App\Models\MetadataKey;
use App\Services\CampaignContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MetadataKeyResource extends Resource
{
    protected static ?string $model = MetadataKey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Metadata';

    protected static UnitEnum|string|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'Llave de metadata';

    protected static ?string $pluralModelLabel = 'Llaves de metadata';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return MetadataKeyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetadataKeysTable::configure($table);
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
            'index' => ListMetadataKeys::route('/'),
            'create' => CreateMetadataKey::route('/create'),
            'edit' => EditMetadataKey::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        // META-01: solo Super Admin administra el catálogo de metadata (mismo gate que AuditLogResource).
        return CampaignContext::isSuperAdmin();
    }
}
