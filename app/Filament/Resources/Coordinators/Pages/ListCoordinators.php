<?php

namespace App\Filament\Resources\Coordinators\Pages;

use App\Filament\Resources\Coordinators\CoordinatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoordinators extends ListRecords
{
    protected static string $resource = CoordinatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

