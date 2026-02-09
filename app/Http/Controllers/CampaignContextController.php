<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return back();
        }

        if (! CampaignContext::isSuperAdmin($user)) {
            CampaignContext::setCampaignId(CampaignContext::currentCampaignId($user));

            return back();
        }

        $campaignId = $request->string('campaign_id')->toString();

        if ($campaignId === 'all') {
            CampaignContext::setCampaignId(null);

            return back();
        }

        $campaign = Campaign::query()->whereKey($campaignId)->first();

        if (! $campaign) {
            return back()->with('error', 'La campaña seleccionada no existe.');
        }

        CampaignContext::setCampaignId($campaign->id);

        return back();
    }
}
