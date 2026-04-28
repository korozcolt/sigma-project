<?php

use App\Models\Voter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/cumpleanos', function () {
    $today = Carbon::now('America/Bogota');

    $names = Voter::query()
        ->whereMonth('birth_date', $today->month)
        ->whereDay('birth_date', $today->day)
        ->get()
        ->map(fn (Voter $voter): string => trim("{$voter->first_name} {$voter->last_name}"))
        ->values()
        ->all();

    return response()->json($names);
});
