<?php

namespace App\Http\Controllers;

use App\Models\PollingPlace;
use Illuminate\Http\Request;

class PublicPollingPlaceOptionsController extends Controller
{
    public function __invoke(Request $request)
    {
        $municipalityId = (int) $request->query('municipality_id');

        if (! $municipalityId) {
            return response()->json([]);
        }

        return response()->json(
            PollingPlace::query()
                ->where('municipality_id', $municipalityId)
                ->orderBy('name')
                ->get(['id', 'name', 'max_tables'])
        );
    }
}

