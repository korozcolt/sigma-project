<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageResource\Pages;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessage extends EditRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
