<?php

namespace App\Filament\Resources\Subcategorias\Pages;

use App\Filament\Resources\Subcategorias\SubcategoriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubcategoria extends EditRecord
{
    protected static string $resource = SubcategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
