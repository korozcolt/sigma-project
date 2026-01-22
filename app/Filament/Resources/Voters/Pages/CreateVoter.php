<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Voters\VoterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVoter extends CreateRecord
{
    protected static string $resource = VoterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['registered_by'])) {
            $user = auth()->user();

            if ($user?->hasRole(UserRole::LEADER->value)) {
                $data['registered_by'] = $user->id;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
