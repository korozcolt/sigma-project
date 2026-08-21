<?php

namespace App\Providers\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\Voters\VoterResource;
use App\Filament\Widgets\ApoyosLideresCoordinadoresTable;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Filament\Widgets\CampaignVotersSparklineWidget;
use App\Filament\Widgets\DiaDStatsOverview;
use App\Filament\Widgets\DiaDTerritorialProgressTable;
use App\Filament\Widgets\DuplicatesReportTable;
use App\Filament\Widgets\FallbackSourceOverview;
use App\Filament\Widgets\FollowUpBacklogOverview;
use App\Filament\Widgets\JurisdictionReportTable;
use App\Filament\Widgets\JurisdictionSummaryOverview;
use App\Filament\Widgets\RejectionsCountersOverview;
use App\Filament\Widgets\RejectionsReportTable;
use App\Filament\Widgets\SurveyResponsesSparklineWidget;
use App\Filament\Widgets\SurveyStatsOverview;
use App\Filament\Widgets\TerritorialDistributionChart;
use App\Filament\Widgets\TerritorialOwnershipTable;
use App\Filament\Widgets\TopCoordinatorsTable;
use App\Filament\Widgets\TopLeadersTable;
use App\Filament\Widgets\TopPollingPlacesTable;
use App\Filament\Widgets\ValidationProgressChart;
use App\Http\Middleware\EnsureUserHasRole;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ReportsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('reports')
            ->path('reports')
            ->viteTheme('resources/css/filament/theme.css')
            ->brandLogo(asset('images/logo-sigma_small.webp'))
            ->brandLogoHeight('2.5rem')
            ->font('Manrope')
            ->colors([
                'primary' => [
                    50 => '#fff7ed',
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
            ->resources([
                VoterResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                CampaignStatsOverview::class,
                CampaignVotersSparklineWidget::class,
                FollowUpBacklogOverview::class,
                FallbackSourceOverview::class,
                ValidationProgressChart::class,
                TerritorialDistributionChart::class,
                TopLeadersTable::class,
                TopCoordinatorsTable::class,
                TerritorialOwnershipTable::class,
                TopPollingPlacesTable::class,
                RejectionsCountersOverview::class,
                RejectionsReportTable::class,
                DuplicatesReportTable::class,
                JurisdictionSummaryOverview::class,
                JurisdictionReportTable::class,
                ApoyosLideresCoordinadoresTable::class,
                SurveyStatsOverview::class,
                SurveyResponsesSparklineWidget::class,
                DiaDStatsOverview::class,
                DiaDTerritorialProgressTable::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => Vite::withEntryPoints(['resources/js/charts/main.jsx'])->toHtml(),
            )
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
                EnsureUserHasRole::class.':'.UserRole::REPORTS_VIEWER->value,
            ]);
    }
}
