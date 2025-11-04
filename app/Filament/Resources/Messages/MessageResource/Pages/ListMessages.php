<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageResource\Pages;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
