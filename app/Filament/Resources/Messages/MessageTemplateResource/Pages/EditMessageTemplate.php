<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageTemplateResource\Pages;

use App\Filament\Resources\Messages\MessageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
