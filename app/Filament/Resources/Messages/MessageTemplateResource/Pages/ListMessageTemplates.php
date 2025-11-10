<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageTemplateResource\Pages;

use App\Filament\Resources\Messages\MessageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMessageTemplates extends ListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected static ?string $title = 'Plantillas de Mensajes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Plantilla'),
        ];
    }
}
