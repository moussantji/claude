<?php

namespace App\Providers;

use App\Services\AnimeSama\Scraper;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
