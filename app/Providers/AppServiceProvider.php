<?php

namespace App\Providers;

use App\Filament\Widgets\RevalidationProgressWidget;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\InfovotantesService;
use App\Services\PollingPlaceResolver;
use App\Services\RegistraduriaService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PollingPlaceResolver::class, fn ($app) => new PollingPlaceResolver(
            liveAdapters: [
                $app->make(InfovotantesService::class),
                $app->make(RegistraduriaService::class),
            ],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        // RevalidationProgressWidget is only attached via ListVoters::getHeaderWidgets()
        // (page-scoped), not the panel's global ->widgets([...]) array. Livewire's default
        // alias<->class resolution only auto-registers classes under config('livewire.class_namespace')
        // (App\Livewire); classes elsewhere (like App\Filament\Widgets) resolve fine on first
        // render but throw ComponentNotFoundException on the wire:poll follow-up request unless
        // explicitly registered here.
        Livewire::component(
            'app.filament.widgets.revalidation-progress-widget',
            RevalidationProgressWidget::class,
        );

        // Force HTTPS if APP_URL uses https, environment is production, or behind a proxy
        if (str_starts_with(config('app.url'), 'https://') ||
            config('app.env') === 'production' ||
            request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
