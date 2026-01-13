<?php

namespace App\Http\Controllers\CampaignAdmin;

use App\Exports\CoordinatorsExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CoordinatorsExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $query = User::role('coordinator')
            ->when($request->query('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($request->query('campaign_id'), fn ($q, $id) => $q->whereHas('campaigns', fn ($qq) => $qq->where('campaigns.id', $id)))
            ->withCount(['registeredVoters as voters_count', 'campaigns as campaigns_count']);

        $export = new CoordinatorsExport(queryBuilder: $query);

        return $export->download('coordinadores.xlsx');
    }
}
