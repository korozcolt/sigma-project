<?php

namespace App\Filament\Resources\Coordinators\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\CoordinatorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoordinator extends CreateRecord
{
    protected static string $resource = CoordinatorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole(UserRole::COORDINATOR->value);

        if (! empty($this->data['also_leader'])) {
            $this->record->assignRole(UserRole::LEADER->value);
            $this->record->update(['coordinator_user_id' => $this->record->id]);
        }
    }
}

