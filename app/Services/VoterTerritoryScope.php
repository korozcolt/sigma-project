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
     *
     * When the voter's polling place has been resolved (via the Registraduría/census
     * reconciliation cascade), it is the confirmed ground truth for "where this voter is
     * really registered to vote" and takes precedence over the voter's own municipality_id —
     * which is only a proxy, defaulted at creation time to the campaign's own municipality
     * and never updated afterward. Fall back to the voter's own municipality_id/department
     * only when no polling place is resolved yet.
     */
    public function isWithinCampaignScope(Voter $voter, Campaign $campaign): bool
    {
        $groundTruthMunicipality = $voter->pollingPlace?->municipality ?? $voter->municipality;
        $groundTruthMunicipalityId = $voter->pollingPlace?->municipality_id ?? $voter->municipality_id;

        if ($campaign->municipality_id) {
            return $groundTruthMunicipalityId === $campaign->municipality_id;
        }

        if ($campaign->department_id) {
            return $groundTruthMunicipality?->department_id === $campaign->department_id;
        }

        return true;
    }
}
