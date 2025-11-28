<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectionEvents;

use App\Filament\Resources\ElectionEvents\Pages\CreateElectionEvent;
use App\Filament\Resources\ElectionEvents\Pages\EditElectionEvent;
use App\Filament\Resources\ElectionEvents\Pages\ListElectionEvents;
use App\Filament\Resources\ElectionEvents\Schemas\ElectionEventForm;
use App\Filament\Resources\ElectionEvents\Tables\ElectionEventsTable;
use App\Models\ElectionEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ElectionEventResource extends Resource
{
    protected static ?string $model = ElectionEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Eventos (Avanzado)';

    protected static ?string $modelLabel = 'Evento Electoral';

    protected static ?string $pluralModelLabel = 'Eventos Electorales';

    protected static string|\UnitEnum|null $navigationGroup = 'Jornada Electoral';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ElectionEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ElectionEventsTable::configure($table);
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
            'index' => ListElectionEvents::route('/'),
            'create' => CreateElectionEvent::route('/create'),
            'edit' => EditElectionEvent::route('/{record}/edit'),
        ];
    }
}
