<?php

namespace App\Filament\Resources\AreaCoordinators\Pages;

use App\Filament\Resources\AreaCoordinators\AreaCoordinatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAreaCoordinators extends ListRecords
{
    protected static string $resource = AreaCoordinatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
