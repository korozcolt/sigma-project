<?php

namespace App\Filament\Resources\Leaders\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Models\User;
use App\Services\CampaignContext;
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

        // Defensive fallback: if the coordinator itself has no campaign
        // attachment (e.g. stale data from before the
        // coordinator-campaign-not-attached fix), fall back to the currently
        // active campaign so the leader isn't left orphaned too.
        if (empty($campaignIds) && $activeCampaignId = CampaignContext::resolveUnambiguousCampaignId()) {
            $campaignIds = [$activeCampaignId];
        }

        $this->record->campaigns()->sync($campaignIds);
    }
}
