<?php

namespace App\Console\Commands;

use App\Services\AnimeCacheStore;
use Illuminate\Console\Command;

/**
 * Housekeeping: drop cache rows that haven't been refreshed in a while.
 */
class PruneAnimeCache extends Command
{
    protected $signature = 'anime:cache-prune {--days=30 : Delete rows last fetched more than this many days ago}';

    protected $description = 'Delete stale anime cache rows from the database.';

    public function handle(AnimeCacheStore $store): int
    {
        $days = max(0, (int) $this->option('days'));
        $deleted = $store->prune($days);

        $this->info("Pruned {$deleted} anime cache row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
