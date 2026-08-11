<?php

namespace App\Filament\Resources\MetadataKeys\Pages;

use App\Filament\Resources\MetadataKeys\MetadataKeyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMetadataKey extends EditRecord
{
    protected static string $resource = MetadataKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
