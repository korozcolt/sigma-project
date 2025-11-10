<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageBatchResource\Pages;

use App\Filament\Resources\Messages\MessageBatchResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMessageBatch extends CreateRecord
{
    protected static string $resource = MessageBatchResource::class;

    protected static ?string $title = 'Crear Envío Masivo';

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
