<?php

namespace App\Providers;

use App\Services\AnimeSama\Scraper;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound per-resolution (not singleton) so config changes and the
        // runtime-updated site URL are always picked up.
        $this->app->bind(Scraper::class, function ($app) {
            return new Scraper(
                $app->make(Factory::class),
                config('services.anime_sama'),
            );
        });

        $this->app->bind(TmdbClient::class, function ($app) {
            return new TmdbClient(
                $app->make(Factory::class),
                (string) config('services.tmdb.api_key', ''),
                (string) config('services.tmdb.language', 'fr-FR'),
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
