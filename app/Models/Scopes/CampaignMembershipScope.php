<?php

namespace App\Models\Scopes;

use App\Services\CampaignContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CampaignMembershipScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        $campaignId = CampaignContext::currentCampaignId(Auth::user());

        if (! $campaignId) {
            return;
        }

        $builder->whereHas('campaigns', function (Builder $query) use ($campaignId) {
            $query->where('campaigns.id', $campaignId);
        });
    }
}
