<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\MessageBatchResource\Pages;
use App\Filament\Resources\Messages\Schemas\MessageBatchForm;
use App\Filament\Resources\Messages\Tables\MessageBatchesTable;
use App\Models\MessageBatch;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageBatchResource extends Resource
{
    protected static ?string $model = MessageBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Mensajería';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Envíos Masivos';

    public static function form(Schema $schema): Schema
    {
        return MessageBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageBatchesTable::configure($table);
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
            'index' => Pages\ListMessageBatches::route('/'),
            'create' => Pages\CreateMessageBatch::route('/create'),
            'edit' => Pages\EditMessageBatch::route('/{record}/edit'),
            'view' => Pages\ViewMessageBatch::route('/{record}'),
        ];
    }
}
