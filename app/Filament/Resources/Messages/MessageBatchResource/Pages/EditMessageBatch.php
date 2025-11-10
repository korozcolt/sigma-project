<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageBatchResource\Pages;

use App\Filament\Resources\Messages\MessageBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessageBatch extends EditRecord
{
    protected static string $resource = MessageBatchResource::class;

    protected static ?string $title = 'Editar Envío Masivo';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar'),
        ];
    }
}
