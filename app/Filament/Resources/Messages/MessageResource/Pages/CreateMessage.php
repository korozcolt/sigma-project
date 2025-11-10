<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageResource\Pages;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMessage extends CreateRecord
{
    protected static string $resource = MessageResource::class;

    protected static ?string $title = 'Crear Mensaje';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
