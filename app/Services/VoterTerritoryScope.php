<?php

namespace App\Services;

use App\Enums\CampaignScope;
use App\Models\Campaign;
use App\Models\Voter;

class VoterTerritoryScope
{
    /**
     * Whether the campaign has enough territorial reference (municipality or department)
     * to compare voters against. Byte-identical condition to the applicability check
     * already used by JurisdictionReportTable::canView() / JurisdictionSummaryOverview::canView().
     */
    public function isTerritoryDefined(Campaign $campaign): bool
    {
        if ($campaign->scope === CampaignScope::Nacional) {
            return false;
        }

        return ! (is_null($campaign->municipality_id) && is_null($campaign->department_id));
    }

    /**
     * Whether a voter's municipality falls within the campaign's defined territory.
     * Returns true (not-applicable) when the campaign has no territorial reference at all —
     * callers that need to distinguish "inside" from "not-applicable" should guard with
     * isTerritoryDefined() first.
     */
    public function isWithinCampaignScope(Voter $voter, Campaign $campaign): bool
    {
        if ($campaign->municipality_id) {
            return $voter->municipality_id === $campaign->municipality_id;
        }

        if ($campaign->department_id) {
            return $voter->municipality?->department_id === $campaign->department_id;
        }

        return true;
    }
}
