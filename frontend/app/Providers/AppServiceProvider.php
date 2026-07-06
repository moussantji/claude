<?php

namespace App\Providers;

use App\Services\AnimeSamaClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AnimeSamaClient::class, function ($app) {
            return new AnimeSamaClient(
                config('services.anime_sama.base_url'),
                config('services.anime_sama.api_key'),
                (float) config('services.anime_sama.timeout', 25),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
