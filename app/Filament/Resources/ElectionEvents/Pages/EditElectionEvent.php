<?php

namespace App\Filament\Resources\ElectionEvents\Pages;

use App\Filament\Resources\ElectionEvents\ElectionEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditElectionEvent extends EditRecord
{
    protected static string $resource = ElectionEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
