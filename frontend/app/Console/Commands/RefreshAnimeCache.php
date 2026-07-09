<?php

namespace App\Console\Commands;

use App\Services\AnimeCacheStore;
use App\Services\AnimeSamaClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Warm the DB cache so user traffic is served from the database and the
 * upstream anime-sama site is hit on a schedule instead of per-request.
 *
 * Example (schedule it in routes/console.php or a cron):
 *   php artisan anime:cache-refresh "one piece" "jujutsu kaisen"
 */
class RefreshAnimeCache extends Command
{
    protected $signature = 'anime:cache-refresh {queries?* : Optional search terms to pre-warm}';

    protected $description = 'Refresh the homepage feed (and optional searches) into the DB cache.';

    public function handle(AnimeSamaClient $client, AnimeCacheStore $store): int
    {
        $this->refresh('new-episodes', fn () => $store->refresh(
            'new_episodes',
            'new-episodes',
            fn () => $client->newEpisodes()
        ));

        foreach ((array) $this->argument('queries') as $query) {
            $query = trim((string) $query);
            if ($query === '') {
                continue;
            }

            $this->refresh("search: {$query}", fn () => $store->refresh(
                'search',
                'search:'.mb_strtolower($query),
                fn () => $client->search($query)
            ));
        }

        return self::SUCCESS;
    }

    private function refresh(string $label, callable $do): void
    {
        try {
            $do();
            $this->info("Refreshed {$label}");
        } catch (Throwable $e) {
            $this->warn("Failed to refresh {$label}: {$e->getMessage()}");
        }
    }
}
