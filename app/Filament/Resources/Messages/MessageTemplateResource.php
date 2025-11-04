<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\MessageTemplateResource\Pages;
use App\Filament\Resources\Messages\Schemas\MessageTemplateForm;
use App\Filament\Resources\Messages\Tables\MessageTemplatesTable;
use App\Models\MessageTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Mensajería';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Plantillas';

    public static function form(Schema $schema): Schema
    {
        return MessageTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageTemplatesTable::configure($table);
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
            'index' => Pages\ListMessageTemplates::route('/'),
            'create' => Pages\CreateMessageTemplate::route('/create'),
            'edit' => Pages\EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
