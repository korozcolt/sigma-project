<?php

namespace App\Http\Middleware;

use App\Services\InvitationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInvitationForRegistration
{
    public function __construct(private InvitationService $invitationService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route('token')) {
            $invitation = $this->invitationService->validateInvitation($request->route('token'));
            
            if (!$invitation) {
                return redirect()
                    ->route('home')
                    ->with('error', 'El enlace de registro no es válido o ya expiró.');
            }

            $request->merge(['validated_invitation' => $invitation]);
            
            return $next($request);
        }

        return redirect()
            ->route('home')
            ->with('error', 'Acceso no permitido.');
    }
}
