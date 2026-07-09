<?php

namespace App\Providers;

use App\Services\AnimeSamaClient;
use App\Services\VidmolyClient;
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

        $this->app->singleton(VidmolyClient::class, function ($app) {
            return new VidmolyClient(
                config('services.vidmoly.api_key', ''),
                config('services.vidmoly.api_base', 'https://api.vidmoly.to'),
                config('services.vidmoly.embed_base', 'https://vidmoly.to'),
                (int) config('services.vidmoly.timeout', 900),
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
