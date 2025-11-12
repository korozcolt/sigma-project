<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        // Si el usuario ya está en su dashboard correspondiente, continuar
        $currentRoute = $request->route()->getName();
        if ($this->isCorrectDashboard($request->user(), $currentRoute)) {
            return $next($request);
        }

        // Redirigir al dashboard correspondiente según el rol
        if ($request->user()->hasRole('super_admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        if ($request->user()->hasRole('admin_campaign')) {
            return redirect()->route('campaign-admin.dashboard');
        }

        if ($request->user()->hasRole('coordinator')) {
            return redirect()->route('coordinator.dashboard');
        }

        if ($request->user()->hasRole('leader')) {
            return redirect()->route('leader.dashboard');
        }

        // Si no tiene un rol específico, continuar al dashboard general
        return $next($request);
    }

    /**
     * Verificar si el usuario está en su dashboard correcto
     */
    private function isCorrectDashboard($user, ?string $currentRoute): bool
    {
        $dashboardMap = [
            'super_admin' => ['filament.admin.pages.dashboard', 'filament.admin.*'],
            'admin_campaign' => ['campaign-admin.dashboard', 'campaign-admin.*'],
            'coordinator' => ['coordinator.dashboard', 'coordinator.*'],
            'leader' => ['leader.dashboard', 'leader.*'],
        ];

        foreach ($dashboardMap as $role => $routes) {
            if ($user->hasRole($role)) {
                foreach ($routes as $route) {
                    if (str_contains($route, '*')) {
                        $pattern = str_replace('*', '', $route);
                        if (str_starts_with($currentRoute, $pattern)) {
                            return true;
                        }
                    } elseif ($currentRoute === $route) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
