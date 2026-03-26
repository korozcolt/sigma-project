<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Filament\Widgets\ValidationProgressChart;
use App\Filament\Widgets\TerritorialDistributionChart;
use App\Filament\Widgets\TopLeadersTable;
use App\Filament\Widgets\SurveyStatsOverview;
use App\Filament\Widgets\BirthdayWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/theme.css')
            ->brandLogo(asset('images/logo-sigma_small.webp'))
            ->brandLogoHeight('2.5rem')
            ->sidebarCollapsibleOnDesktop()
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
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                CampaignStatsOverview::class,    // KPIs principales — fila 1
                ValidationProgressChart::class,  // Tendencia 30d — fila 2 izq
                TerritorialDistributionChart::class, // Mapa — fila 2 der
                TopLeadersTable::class,          // Ranking — fila 3 completa
                SurveyStatsOverview::class,      // Encuestas — fila 4 izq
                BirthdayWidget::class,           // Cumpleaños — fila 5 completa
            ])
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.components.campaign-context-switcher'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.components.motion-init'))
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Gestión')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Call Center')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Mensajería')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Jornada Electoral')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Configuración')
                    ->collapsed(false),
            ])
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
            ]);
    }
}
