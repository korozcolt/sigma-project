<?php

namespace App\Filament\Resources\Coordinators\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\CoordinatorResource;
use App\Services\CampaignContext;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Spatie\Permission\Models\Role;

class CreateCoordinator extends CreateRecord
{
    protected static string $resource = CoordinatorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! CampaignContext::resolveUnambiguousCampaignId()) {
            Notification::make()
                ->danger()
                ->title('Selecciona una campaña activa')
                ->body('Debes seleccionar una campaña específica en el selector superior antes de crear un coordinador.')
                ->send();

            throw new Halt;
        }

        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole(UserRole::COORDINATOR->value);

        $this->attachActiveCampaign();

        if (! empty($this->data['also_leader'])) {
            $this->record->assignRole(UserRole::LEADER->value);
            $this->record->update(['coordinator_user_id' => $this->record->id]);
        }
    }

    private function attachActiveCampaign(): void
    {
        $campaignId = CampaignContext::resolveUnambiguousCampaignId();

        if (! $campaignId) {
            return;
        }

        $this->record->campaigns()->syncWithoutDetaching([
            $campaignId => [
                'role_id' => Role::findByName(UserRole::COORDINATOR->value)->id,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ],
        ]);
    }
}
