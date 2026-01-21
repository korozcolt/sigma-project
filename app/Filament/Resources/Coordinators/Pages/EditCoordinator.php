<?php

namespace App\Filament\Resources\Coordinators\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\CoordinatorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoordinator extends EditRecord
{
    protected static string $resource = CoordinatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record->hasRole(UserRole::COORDINATOR->value)) {
            $this->record->assignRole(UserRole::COORDINATOR->value);
        }
    }
}

