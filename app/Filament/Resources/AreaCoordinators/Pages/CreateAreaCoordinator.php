<?php

namespace App\Filament\Resources\AreaCoordinators\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\AreaCoordinatorResource;
use App\Services\CampaignContext;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Spatie\Permission\Models\Role;

class CreateAreaCoordinator extends CreateRecord
{
    protected static string $resource = AreaCoordinatorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! CampaignContext::resolveUnambiguousCampaignId()) {
            Notification::make()
                ->danger()
                ->title('Selecciona una campaña activa')
                ->body('Debes seleccionar una campaña específica en el selector superior antes de crear un articulador.')
                ->send();

            throw new Halt;
        }

        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole(UserRole::AREA_COORDINATOR->value);

        $this->attachActiveCampaign();
    }

    private function attachActiveCampaign(): void
    {
        $campaignId = CampaignContext::resolveUnambiguousCampaignId();

        if (! $campaignId) {
            return;
        }

        $this->record->campaigns()->syncWithoutDetaching([
            $campaignId => [
                'role_id' => Role::findByName(UserRole::AREA_COORDINATOR->value)->id,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ],
        ]);
    }
}
