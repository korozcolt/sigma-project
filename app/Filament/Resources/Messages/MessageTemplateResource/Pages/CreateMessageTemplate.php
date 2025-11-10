<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageTemplateResource\Pages;

use App\Filament\Resources\Messages\MessageTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMessageTemplate extends CreateRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected static ?string $title = 'Crear Plantilla de Mensaje';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
