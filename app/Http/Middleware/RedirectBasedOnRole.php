<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
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
        if ($request->user()->hasRole(UserRole::SUPER_ADMIN->value)) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        if ($request->user()->hasRole(UserRole::ADMIN_CAMPAIGN->value)) {
            return redirect()->route('campaign-admin.dashboard');
        }

        if ($request->user()->hasRole(UserRole::COORDINATOR->value)) {
            return redirect()->route('coordinator.dashboard');
        }

        if ($request->user()->hasRole(UserRole::LEADER->value)) {
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
            UserRole::SUPER_ADMIN->value => ['filament.admin.pages.dashboard', 'filament.admin.*'],
            UserRole::ADMIN_CAMPAIGN->value => ['campaign-admin.dashboard', 'campaign-admin.*'],
            UserRole::COORDINATOR->value => ['coordinator.dashboard', 'coordinator.*'],
            UserRole::LEADER->value => ['leader.dashboard', 'leader.*'],
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
