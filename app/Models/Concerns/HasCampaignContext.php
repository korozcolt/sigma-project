<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CampaignContextScope;
use App\Services\CampaignContext;
use Illuminate\Database\Eloquent\Model;

trait HasCampaignContext
{
    public static function bootHasCampaignContext(): void
    {
        static::addGlobalScope(new CampaignContextScope());

        static::creating(function (Model $model): void {
            CampaignContext::enforceCampaignId($model);
        });

        static::updating(function (Model $model): void {
            CampaignContext::enforceCampaignIdOnUpdate($model);
        });
    }

    public function shouldIncludeGlobalRecords(): bool
    {
        return false;
    }
}
