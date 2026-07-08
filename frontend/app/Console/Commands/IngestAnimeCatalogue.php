<?php

namespace App\Console\Commands;

use App\Models\AnimeCache;
use App\Services\AnimeCacheStore;
use App\Services\AnimeSamaClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-shot ingest of the anime-sama catalogue into the database.
 *
 * Default (no --full): light. Warms the homepage feed, planning and the full
 * catalogue metadata (~1k+ entries in a single listing crawl).
 *
 * With --full: also walks every catalogue's seasons and episodes, storing the
 * player/"vid" links for each episode. This is a long crawl (potentially
 * hours over the whole catalogue), so it is:
 *   - throttled (--sleep ms between upstream calls),
 *   - resumable: entries already stored and still fresh are skipped, so you can
 *     stop and re-run the command to continue where it left off (use --force to
 *     re-fetch everything regardless).
 */
class IngestAnimeCatalogue extends Command
{
    protected $signature = 'anime:ingest
        {--full : Also crawl seasons and episodes (player/vid links) for each catalogue}
        {--limit=0 : With --full, max catalogues to deep-crawl (0 = all)}
        {--sleep=250 : With --full, pause in ms between upstream calls}
        {--force : Re-fetch entries even if they are already stored and fresh}';

    protected $description = 'Fetch the whole anime-sama catalogue (and optionally every episode player link) into the DB.';

    public function handle(AnimeSamaClient $client, AnimeCacheStore $store): int
    {
        $this->step('new-episodes', fn () => $store->refresh(
            'new_episodes',
            'new-episodes',
            fn () => $client->newEpisodes()
        ));

        $this->step('planning', fn () => $store->refresh(
            'planning',
            'planning',
            fn () => $client->planning()
        ));

        $catalogues = [];
        $this->step('all-catalogues', function () use ($client, $store, &$catalogues) {
            $catalogues = $store->refresh(
                'catalogues',
                'all-catalogues',
                fn () => $client->allCatalogues()
            );
        });
        $this->info(count($catalogues).' catalogues stored.');

        if ($this->option('full') && $catalogues) {
            $this->deepCrawl($client, $store, $catalogues);
        } else {
            $this->line('Run with --full to also store every episode\'s player/vid links.');
        }

        $this->info('Ingest complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogues
     */
    private function deepCrawl(AnimeSamaClient $client, AnimeCacheStore $store, array $catalogues): void
    {
        $limit = max(0, (int) $this->option('limit'));
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;
        $force = (bool) $this->option('force');
        $subset = $limit > 0 ? array_slice($catalogues, 0, $limit) : $catalogues;

        // Fresh entries are skipped (resumable) unless --force is given.
        $fetch = function (string $type, string $key, callable $cb) use ($store, $force) {
            return $force
                ? $store->refresh($type, $key, $cb)
                : $store->remember($type, $key, null, $cb);
        };

        $this->warn(sprintf(
            'Deep-crawling seasons/episodes for %d catalogue(s). This is slow and hits anime-sama repeatedly; keep --sleep reasonable.',
            count($subset)
        ));

        $bar = $this->output->createProgressBar(count($subset));
        $bar->start();

        foreach ($subset as $catalogue) {
            $url = $catalogue['url'] ?? null;
            if (is_string($url) && $url !== '') {
                try {
                    $detail = $fetch('catalogue', 'catalogue:'.$url, fn () => $client->catalogue($url));

                    foreach ((array) ($detail['seasons'] ?? []) as $season) {
                        $seasonUrl = $season['url'] ?? null;
                        if (is_string($seasonUrl) && $seasonUrl !== '') {
                            try {
                                $fetch(
                                    'episodes',
                                    'episodes:'.$seasonUrl.':',
                                    fn () => $client->episodes($seasonUrl)
                                );
                            } catch (Throwable) {
                                // Skip a failing season; keep crawling.
                            }
                            if ($sleepUs > 0) {
                                usleep($sleepUs);
                            }
                        }
                    }
                } catch (Throwable) {
                    // Skip a failing catalogue; keep crawling.
                }
            }

            $bar->advance();
            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info(sprintf(
            'Stored %d catalogue detail(s) and %d episode set(s) with player links.',
            AnimeCache::query()->where('type', 'catalogue')->count(),
            AnimeCache::query()->where('type', 'episodes')->count(),
        ));
    }

    private function step(string $label, callable $do): void
    {
        try {
            $do();
            $this->info("Refreshed {$label}");
        } catch (Throwable $e) {
            $this->warn("Skipped {$label}: {$e->getMessage()}");
        }
    }
}
