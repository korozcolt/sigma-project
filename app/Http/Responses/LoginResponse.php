<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $intendedUrl = $request->session()->pull('url.intended');

        if (is_string($intendedUrl) && $this->isAllowedIntendedUrl($request, $intendedUrl)) {
            return redirect()->to($intendedUrl);
        }

        return redirect()->to($this->defaultRedirectUrl($user));
    }

    private function isAllowedIntendedUrl(Request $request, string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host && $host !== $request->getHost()) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $firstSegment = Str::of($path)->ltrim('/')->before('/')->toString();

        if (! in_array($firstSegment, ['admin', 'coordinator', 'leader'], true)) {
            return true;
        }

        try {
            $panel = Filament::getPanel($firstSegment);
        } catch (\Throwable) {
            return false;
        }

        return (bool) $request->user()?->canAccessPanel($panel);
    }

    private function defaultRedirectUrl($user): string
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN->value) || $user->hasRole(UserRole::REVIEWER->value)) {
            return route('filament.admin.pages.dashboard', absolute: false);
        }

        if ($user->hasRole(UserRole::ADMIN_CAMPAIGN->value)) {
            return route('campaign-admin.dashboard', absolute: false);
        }

        if ($user->hasRole(UserRole::COORDINATOR->value)) {
            return route('coordinator.dashboard', absolute: false);
        }

        if ($user->hasRole(UserRole::LEADER->value)) {
            return route('leader.dashboard', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
