<?php

namespace App\Models\Scopes;

use App\Services\CampaignContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CampaignContextScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $campaignId = CampaignContext::currentCampaignId();

        if (! $campaignId) {
            return;
        }

        if (method_exists($model, 'shouldIncludeGlobalRecords') && $model->shouldIncludeGlobalRecords()) {
            $builder->where(function (Builder $query) use ($campaignId, $model) {
                $query->where($model->getTable() . '.campaign_id', $campaignId)
                    ->orWhere(function (Builder $globalQuery) use ($model) {
                        $globalQuery->where($model->getTable() . '.is_global', true)
                            ->whereNull($model->getTable() . '.campaign_id');
                    });
            });

            return;
        }

        $builder->where($model->getTable() . '.campaign_id', $campaignId);
    }
}
