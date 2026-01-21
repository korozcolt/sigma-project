<?php

namespace App\Filament\Resources\Leaders\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateLeader extends CreateRecord
{
    protected static string $resource = LeaderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $coordinatorMunicipalityId = User::query()
            ->whereKey($data['coordinator_user_id'])
            ->value('municipality_id');

        $data['municipality_id'] = $coordinatorMunicipalityId;
        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole(UserRole::LEADER->value);

        $campaignIds = User::query()
            ->whereKey($this->record->coordinator_user_id)
            ->first()
            ?->campaigns()
            ->pluck('campaigns.id')
            ->all() ?? [];

        $this->record->campaigns()->sync($campaignIds);
    }
}

