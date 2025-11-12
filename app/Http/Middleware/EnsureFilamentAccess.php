<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Roles permitidos en el panel de Filament
        $allowedRoles = ['super_admin', 'admin_campaign', 'reviewer'];

        // Verificar si el usuario tiene alguno de los roles permitidos
        if (! $request->user()->hasAnyRole($allowedRoles)) {
            // Redirigir según el rol del usuario
            if ($request->user()->hasRole('coordinator')) {
                return redirect()->route('coordinator.dashboard');
            }

            if ($request->user()->hasRole('leader')) {
                return redirect()->route('leader.dashboard');
            }

            // Si no tiene ningún rol conocido, redirigir al dashboard general
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
