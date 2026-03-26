<?php

namespace App\Providers\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\DiaD;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Filament\Widgets\TerritorialDistributionChart;
use App\Filament\Widgets\TopLeadersTable;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CoordinatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('coordinator')
            ->path('coordinator')
            ->viteTheme('resources/css/filament/theme.css')
            ->brandLogo(asset('images/logo-sigma_small.webp'))
            ->brandLogoHeight('2.5rem')
            ->font('Manrope')
            ->colors([
                'primary' => [
                    50  => '#fff7ed',
                    100 => '#ffedd5',
                    200 => '#fed7aa',
                    300 => '#fdba74',
                    400 => '#fb923c',
                    500 => '#f97316',
                    600 => '#ea6c0a',
                    700 => '#c2570e',
                    800 => '#9a3412',
                    900 => '#7c2d12',
                    950 => '#431407',
                ],
                'gray' => Color::Zinc,
            ])
            ->pages([
                Dashboard::class,
                DiaD::class,
            ])
            ->widgets([
                CampaignStatsOverview::class,
                TerritorialDistributionChart::class,
                TopLeadersTable::class,
            ])
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.components.motion-init'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureUserHasRole::class.':'.UserRole::COORDINATOR->value,
            ]);
    }
}
