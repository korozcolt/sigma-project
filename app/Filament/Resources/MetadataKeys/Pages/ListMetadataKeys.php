<?php

namespace App\Filament\Resources\MetadataKeys\Pages;

use App\Filament\Resources\MetadataKeys\MetadataKeyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetadataKeys extends ListRecords
{
    protected static string $resource = MetadataKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
