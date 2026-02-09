<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CampaignMembershipScope;

trait HasCampaignMembershipScope
{
    public static function bootHasCampaignMembershipScope(): void
    {
        static::addGlobalScope(new CampaignMembershipScope());
    }
}
