<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\EnsureFilamentAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectBasedOnRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear roles necesarios
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

// ============ Tests para EnsureUserHasRole ============

test('EnsureUserHasRole allows access when user has required role', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserHasRole;

    $response = $middleware->handle($request, fn () => new Response('success'), 'super_admin');

    expect($response->getContent())->toBe('success');
});

test('EnsureUserHasRole allows access when user has one of multiple required roles', function () {
    $user = User::factory()->create();
    $user->assignRole('coordinator');

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserHasRole;

    $response = $middleware->handle($request, fn () => new Response('success'), 'super_admin', 'coordinator', 'leader');

    expect($response->getContent())->toBe('success');
});

test('EnsureUserHasRole denies access when user does not have required role', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserHasRole;

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    $middleware->handle($request, fn () => new Response('success'), 'super_admin');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

test('EnsureUserHasRole returns 401 when user is not authenticated', function () {
    $request = Request::create('/test', 'GET');
    $middleware = new EnsureUserHasRole;

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    $middleware->handle($request, fn () => new Response('success'), 'super_admin');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

test('EnsureUserHasRole allows access when no roles are specified', function () {
    $user = User::factory()->create();

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserHasRole;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response->getContent())->toBe('success');
});

// ============ Tests para EnsureFilamentAccess ============

test('EnsureFilamentAccess allows super admin to access filament', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureFilamentAccess;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response->getContent())->toBe('success');
});

test('EnsureFilamentAccess allows admin campaign to access filament', function () {
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureFilamentAccess;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response->getContent())->toBe('success');
});

test('EnsureFilamentAccess allows reviewer to access filament', function () {
    $user = User::factory()->create();
    $user->assignRole('reviewer');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureFilamentAccess;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response->getContent())->toBe('success');
});

test('EnsureFilamentAccess redirects coordinator to their dashboard', function () {
    Route::get('/coordinator/dashboard', fn () => 'coordinator')->name('coordinator.dashboard');

    $user = User::factory()->create();
    $user->assignRole('coordinator');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureFilamentAccess;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('coordinator/dashboard');
});

test('EnsureFilamentAccess redirects leader to their dashboard', function () {
    Route::get('/leader/dashboard', fn () => 'leader')->name('leader.dashboard');

    $user = User::factory()->create();
    $user->assignRole('leader');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureFilamentAccess;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('leader/dashboard');
});

test('EnsureFilamentAccess redirects unauthenticated users to login', function () {
    Route::get('/login', fn () => 'login')->name('login');

    $request = Request::create('/admin', 'GET');
    $middleware = new EnsureFilamentAccess;

    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('login');
});

// ============ Tests para RedirectBasedOnRole ============

test('RedirectBasedOnRole redirects super admin to filament admin', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/dashboard', fn () => '');
        $route->name('dashboard');

        return $route;
    });

    $middleware = new RedirectBasedOnRole;
    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('/admin');
});

test('RedirectBasedOnRole redirects coordinator to coordinator dashboard', function () {
    Route::get('/coordinator/dashboard', fn () => 'coordinator')->name('coordinator.dashboard');

    $user = User::factory()->create();
    $user->assignRole('coordinator');

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/dashboard', fn () => '');
        $route->name('dashboard');

        return $route;
    });

    $middleware = new RedirectBasedOnRole;
    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('coordinator/dashboard');
});

test('RedirectBasedOnRole redirects leader to leader dashboard', function () {
    Route::get('/leader/dashboard', fn () => 'leader')->name('leader.dashboard');

    $user = User::factory()->create();
    $user->assignRole('leader');

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/dashboard', fn () => '');
        $route->name('dashboard');

        return $route;
    });

    $middleware = new RedirectBasedOnRole;
    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('leader/dashboard');
});

test('RedirectBasedOnRole does not redirect when user is already in correct dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $this->actingAs($user);

    $request = Request::create('/leader/dashboard', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/leader/dashboard', fn () => '');
        $route->name('leader.dashboard');

        return $route;
    });

    $middleware = new RedirectBasedOnRole;
    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response->getContent())->toBe('success');
});

test('RedirectBasedOnRole does nothing when user is not authenticated', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/dashboard', fn () => '');
        $route->name('dashboard');

        return $route;
    });

    $middleware = new RedirectBasedOnRole;
    $response = $middleware->handle($request, fn () => new Response('success'));

    expect($response->getContent())->toBe('success');
});
