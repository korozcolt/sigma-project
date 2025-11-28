<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ElectionEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class IsElectionDay
{
    /**
     * Handle an incoming request.
     *
     * Verifica que exista un evento electoral activo (simulacro o día D real).
     * Si no hay evento activo o no es el día correcto, redirige con mensaje.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeEvent = ElectionEvent::where('is_active', true)->first();

        if (! $activeEvent) {
            return redirect()->route('home')->with('error', 'No hay ningún evento electoral activo en este momento.');
        }

        $today = Carbon::today();
        $eventDate = Carbon::parse($activeEvent->date);

        if (! $today->isSameDay($eventDate)) {
            $eventType = $activeEvent->isSimulation() ? 'simulacro' : 'día de elecciones';
            $message = $today->isBefore($eventDate)
                ? "El {$eventType} está programado para el {$eventDate->format('d/m/Y')}. Aún no es la fecha correcta."
                : "El {$eventType} fue el {$eventDate->format('d/m/Y')}. El evento ha finalizado.";

            return redirect()->route('home')->with('warning', $message);
        }

        // Verificar si el evento tiene restricción de horario
        if (! $activeEvent->isWithinTimeRange()) {
            return redirect()->route('home')->with('warning', 'El evento no está dentro del horario permitido.');
        }

        return $next($request);
    }
}
