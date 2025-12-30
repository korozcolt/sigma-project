<?php

namespace App\Http\Controllers\Coordinator;

use App\Exports\LeadersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class LeadersExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $campaignIds = $user->campaigns()->pluck('campaigns.id');

        $query = User::role('leader')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->when($request->query('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")) )
            ->when($user->municipality_id, fn ($q) => $q->where('municipality_id', $user->municipality_id))
            ->withCount(['registeredVoters as voters_count']);

        $export = new LeadersExport(queryBuilder: $query);

        return $export->download('lideres.xlsx');
    }
}
