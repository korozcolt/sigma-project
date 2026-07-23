<?php

namespace App\Filament\Resources\Subcategorias\Pages;

use App\Filament\Resources\Subcategorias\SubcategoriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubcategorias extends ListRecords
{
    protected static string $resource = SubcategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
