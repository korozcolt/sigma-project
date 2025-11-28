<?php

namespace App\Filament\Resources\ElectionEvents\Pages;

use App\Filament\Resources\ElectionEvents\ElectionEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListElectionEvents extends ListRecords
{
    protected static string $resource = ElectionEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
