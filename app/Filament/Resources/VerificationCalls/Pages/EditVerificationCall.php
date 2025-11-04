<?php

namespace App\Filament\Resources\VerificationCalls\Pages;

use App\Filament\Resources\VerificationCalls\VerificationCallResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditVerificationCall extends EditRecord
{
    protected static string $resource = VerificationCallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
