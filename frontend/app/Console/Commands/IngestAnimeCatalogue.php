<?php

namespace App\Console\Commands;

use App\Services\AnimeCacheStore;
use App\Services\AnimeSamaClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-shot ingest of the anime-sama catalogue into the database.
 *
 * By default this is light: it warms the homepage feed, planning and the full
 * catalogue metadata (~1k+ entries in a single listing crawl). With --full it
 * additionally walks each catalogue's seasons and episodes (player/"vid" links)
 * — that is a heavy crawl, so it is throttled (--sleep) and capped (--limit).
 */
class IngestAnimeCatalogue extends Command
{
    protected $signature = 'anime:ingest
        {--full : Also crawl seasons and episodes (player links) for each catalogue}
        {--limit=100 : With --full, max catalogues to deep-crawl (0 = all)}
        {--sleep=250 : With --full, pause in ms between upstream calls}';

    protected $description = 'Fetch the whole anime-sama catalogue (and optionally episodes) into the DB in one pass.';

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
        $subset = $limit > 0 ? array_slice($catalogues, 0, $limit) : $catalogues;

        $this->warn(sprintf(
            'Deep-crawling seasons/episodes for %d catalogue(s). This hits anime-sama repeatedly; keep --sleep reasonable.',
            count($subset)
        ));

        $bar = $this->output->createProgressBar(count($subset));
        $bar->start();

        foreach ($subset as $catalogue) {
            $url = $catalogue['url'] ?? null;
            if (is_string($url) && $url !== '') {
                try {
                    $detail = $store->refresh('catalogue', 'catalogue:'.$url, fn () => $client->catalogue($url));

                    foreach ((array) ($detail['seasons'] ?? []) as $season) {
                        $seasonUrl = $season['url'] ?? null;
                        if (is_string($seasonUrl) && $seasonUrl !== '') {
                            try {
                                $store->refresh(
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
