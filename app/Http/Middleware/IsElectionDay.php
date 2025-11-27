<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Campaign;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class IsElectionDay
{
    /**
     * Handle an incoming request.
     *
     * Verifica que hoy sea el día de elecciones según la campaña activa.
     * Si no es el día de elecciones, redirige con mensaje de error.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $campaign = Campaign::where('status', 'active')->first();

        if (! $campaign) {
            return redirect()->route('home')->with('error', 'No hay una campaña activa en este momento.');
        }

        if (! $campaign->election_date) {
            return redirect()->route('home')->with('error', 'La campaña activa no tiene fecha de elecciones configurada.');
        }

        $today = Carbon::today();
        $electionDate = Carbon::parse($campaign->election_date);

        if (! $today->isSameDay($electionDate)) {
            $message = $today->isBefore($electionDate)
                ? "El día de elecciones es el {$electionDate->format('d/m/Y')}. Aún no es el día de votación."
                : "El día de elecciones fue el {$electionDate->format('d/m/Y')}. El periodo de votación ha finalizado.";

            return redirect()->route('home')->with('warning', $message);
        }

        return $next($request);
    }
}
