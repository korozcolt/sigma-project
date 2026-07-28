<?php

namespace App\Filament\Resources\Coordinators\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\CoordinatorResource;
use App\Services\CampaignContext;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

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

        $this->attachActiveCampaignIfMissing();
    }

    /**
     * Self-heals coordinators that were created without a campaign attachment
     * (see coordinator-campaign-not-attached bug): if a specific campaign is
     * active — or there's exactly one active campaign system-wide while
     * browsing in "view all" mode — and the record isn't attached to it yet,
     * attach it now. Never detaches existing attachments — only fills the gap.
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
            'role_id' => Role::findByName(UserRole::COORDINATOR->value)->id,
            'assigned_at' => now(),
            'assigned_by' => auth()->id(),
        ]);
    }
}
