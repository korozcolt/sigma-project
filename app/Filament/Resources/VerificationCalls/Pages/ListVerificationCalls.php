<?php

namespace App\Filament\Resources\VerificationCalls\Pages;

use App\Filament\Resources\VerificationCalls\VerificationCallResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVerificationCalls extends ListRecords
{
    protected static string $resource = VerificationCallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
