<?php

use App\Exceptions\OperationalDenialException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectBasedOnRole;
use App\Http\Middleware\RequireInvitationForRegistration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'redirect.role' => RedirectBasedOnRole::class,
            'invitation.required' => RequireInvitationForRegistration::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
        });

        $exceptions->render(function (OperationalDenialException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->view('errors.operational-denial', ['message' => $e->getMessage()], 422);
        });
    })->create();
