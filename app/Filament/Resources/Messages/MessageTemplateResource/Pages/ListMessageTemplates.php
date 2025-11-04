<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageTemplateResource\Pages;

use App\Filament\Resources\Messages\MessageTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageTemplates extends ListRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
