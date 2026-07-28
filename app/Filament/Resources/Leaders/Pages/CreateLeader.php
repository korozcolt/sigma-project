<?php

namespace App\Filament\Resources\Leaders\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Models\User;
use App\Services\CampaignContext;
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
