<?php

namespace App\Filament\Resources\Leaders\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeader extends EditRecord
{
    protected static string $resource = LeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['coordinator_user_id'])) {
            $data['municipality_id'] = User::query()
                ->whereKey($data['coordinator_user_id'])
                ->value('municipality_id');
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->record->hasRole(UserRole::LEADER->value)) {
            $this->record->assignRole(UserRole::LEADER->value);
        }

        $campaignIds = User::query()
            ->whereKey($this->record->coordinator_user_id)
            ->first()
            ?->campaigns()
            ->pluck('campaigns.id')
            ->all() ?? [];

        $this->record->campaigns()->sync($campaignIds);
    }
}

