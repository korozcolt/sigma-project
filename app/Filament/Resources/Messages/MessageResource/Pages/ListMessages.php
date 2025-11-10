<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageResource\Pages;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    protected static ?string $title = 'Mensajes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Mensaje'),
        ];
    }
}
