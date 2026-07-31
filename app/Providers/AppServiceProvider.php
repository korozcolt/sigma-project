<?php

namespace App\Providers;

use App\Filament\Widgets\CallCenterStatsOverview;
use App\Filament\Widgets\CallHistoryTable;
use App\Filament\Widgets\CallQueueTable;
use App\Filament\Widgets\DiaDStatsOverview;
use App\Filament\Widgets\DiaDTerritorialProgressTable;
use App\Filament\Widgets\RevalidationProgressWidget;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Observers\AuditObserver;
use App\Observers\UserObserver;
use App\Services\ConsultaCensoService;
use App\Services\InfovotantesService;
use App\Services\PollingPlaceResolver;
use App\Services\RegistraduriaService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Filament widgets attached via a Page's getHeaderWidgets()/getFooterWidgets() (page-scoped),
     * never the panel's global ->widgets([...]) array. Livewire's default alias<->class resolution
     * only auto-registers classes under config('livewire.class_namespace') (App\Livewire); classes
     * elsewhere (like App\Filament\Widgets) resolve fine on first render but throw
     * ComponentNotFoundException on the wire:poll follow-up request unless explicitly registered
     * in boot(). See boot() for the full explanation.
     *
     * @var array<class-string>
     */
    private const PAGE_SCOPED_WIDGETS = [
        RevalidationProgressWidget::class,
        CallCenterStatsOverview::class,
        CallQueueTable::class,
        CallHistoryTable::class,
        DiaDStatsOverview::class,
        DiaDTerritorialProgressTable::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PollingPlaceResolver::class, fn ($app) => new PollingPlaceResolver(
            liveAdapters: [
                $app->make(InfovotantesService::class),
                $app->make(RegistraduriaService::class),
                $app->make(ConsultaCensoService::class),
            ],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        User::observe(AuditObserver::class);
        Campaign::observe(AuditObserver::class);
        Voter::observe(AuditObserver::class);

        // Page-scoped widgets never get Livewire's automatic alias<->class resolution the way
        // panel-globally-declared widgets do. Livewire's resolution is asymmetric for classes outside
        // config('livewire.class_namespace') (App\Livewire): forward (class->alias) strips the
        // namespace only if it matches, but the reverse (alias->class) fallback unconditionally
        // prepends it, producing a nonexistent class — this only surfaces on the wire:poll follow-up
        // request, never the initial render. Explicitly registering each widget's deterministically-
        // computed alias avoids Livewire's buggy reverse fallback. First fixed for
        // RevalidationProgressWidget alone (commit 236ca78); consolidated here for every page-scoped
        // widget across Call Center + Día D.
        $componentRegistry = app(ComponentRegistry::class);

        foreach (self::PAGE_SCOPED_WIDGETS as $widgetClass) {
            Livewire::component($componentRegistry->getName($widgetClass), $widgetClass);
        }

        // Force HTTPS if APP_URL uses https, environment is production, or behind a proxy
        if (str_starts_with(config('app.url'), 'https://') ||
            config('app.env') === 'production' ||
            request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
