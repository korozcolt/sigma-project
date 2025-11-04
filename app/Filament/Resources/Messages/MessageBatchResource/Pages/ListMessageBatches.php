<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageBatchResource\Pages;

use App\Filament\Resources\Messages\MessageBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageBatches extends ListRecords
{
    protected static string $resource = MessageBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
