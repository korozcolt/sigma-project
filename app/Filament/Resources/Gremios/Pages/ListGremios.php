<?php

namespace App\Filament\Resources\Gremios\Pages;

use App\Filament\Resources\Gremios\GremioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGremios extends ListRecords
{
    protected static string $resource = GremioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
