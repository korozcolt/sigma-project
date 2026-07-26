<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\InfovotantesService;
use App\Services\PollingPlaceResolver;
use App\Services\RegistraduriaService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        // Force HTTPS if APP_URL uses https, environment is production, or behind a proxy
        if (str_starts_with(config('app.url'), 'https://') ||
            config('app.env') === 'production' ||
            request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
