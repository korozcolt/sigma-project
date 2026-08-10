<?php

namespace App\Filament\Resources\AreaCoordinators\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\AreaCoordinatorResource;
use App\Services\CampaignContext;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditAreaCoordinator extends EditRecord
{
    protected static string $resource = AreaCoordinatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record->hasRole(UserRole::AREA_COORDINATOR->value)) {
            $this->record->assignRole(UserRole::AREA_COORDINATOR->value);
        }

        $this->attachActiveCampaignIfMissing();
    }

    /**
     * Self-heals area coordinators that were created without a campaign attachment,
     * mirroring EditCoordinator::attachActiveCampaignIfMissing(). Never detaches
     * existing attachments — only fills the gap.
     */
    private function attachActiveCampaignIfMissing(): void
    {
        $campaignId = CampaignContext::resolveUnambiguousCampaignId();

        if (! $campaignId) {
            return;
        }

        if ($this->record->campaigns()->whereKey($campaignId)->exists()) {
            return;
        }

        $this->record->campaigns()->attach($campaignId, [
            'role_id' => Role::findByName(UserRole::AREA_COORDINATOR->value)->id,
            'assigned_at' => now(),
            'assigned_by' => auth()->id(),
        ]);
    }
}
