<?php

namespace App\Filament\Resources\Gremios\Pages;

use App\Filament\Resources\Gremios\GremioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGremio extends EditRecord
{
    protected static string $resource = GremioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
