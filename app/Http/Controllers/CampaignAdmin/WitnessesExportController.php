<?php

namespace App\Http\Controllers\CampaignAdmin;

use App\Exports\WitnessesExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class WitnessesExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = User::query()
            ->when($request->query('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->withCount(['registeredVoters as voters_count'])
            ->with(['municipality', 'neighborhood'])
            ->where('is_witness', true);

        $export = new WitnessesExport(queryBuilder: $query);

        return $export->download('testigos.xlsx');
    }
}
