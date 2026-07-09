<?php

namespace App\Console\Commands;

use App\Services\AnimeSamaClient;
use App\Services\LibraryStore;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ingest anime-sama data into the normalized tables
 * (animes / seasons / episodes / episode_links).
 *
 *   php artisan library:ingest            # animes only (fast)
 *   php artisan library:ingest --full     # + seasons, episodes and video links
 */
class IngestLibrary extends Command
{
    protected $signature = 'library:ingest
        {--full : Also import seasons, episodes and video links}
        {--limit=0 : With --full, max animes to deep-crawl (0 = all)}
        {--sleep=250 : With --full, pause in ms between upstream calls}';

    protected $description = 'Ingest anime-sama into normalized tables (animes, seasons, episodes, episode_links).';

    public function handle(AnimeSamaClient $client, LibraryStore $store): int
    {
        $this->info('Fetching catalogue…');
        try {
            $catalogues = $client->allCatalogues();
        } catch (Throwable $e) {
            $this->error('Could not fetch the catalogue: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($catalogues as $summary) {
            $store->upsertAnimeFromSummary($summary);
        }
        $this->info(count($catalogues).' anime(s) upserted into `animes`.');

        if (! $this->option('full')) {
            $this->line('Run with --full to also import seasons, episodes and video links.');

            return self::SUCCESS;
        }

        $limit = max(0, (int) $this->option('limit'));
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;
        $subset = $limit > 0 ? array_slice($catalogues, 0, $limit) : $catalogues;

        $this->warn(sprintf('Deep-crawling %d anime(s) (seasons + episodes + links). Slow; keep --sleep reasonable.', count($subset)));

        $bar = $this->output->createProgressBar(count($subset));
        $bar->start();
        $totalEpisodes = 0;
        $totalLinks = 0;

        foreach ($subset as $summary) {
            $url = $summary['url'] ?? null;
            if (is_string($url) && $url !== '') {
                try {
                    $detail = $client->catalogue($url);
                    $anime = $store->upsertAnimeFromDetail($url, $detail);

                    foreach ($anime->seasons()->get() as $season) {
                        try {
                            $episodes = $client->episodes($season->url);
                            $result = $store->syncEpisodes($season, $episodes);
                            $totalEpisodes += $result['episodes'];
                            $totalLinks += $result['links'];
                        } catch (Throwable) {
                            // Skip a failing season.
                        }
                        if ($sleepUs > 0) {
                            usleep($sleepUs);
                        }
                    }
                } catch (Throwable) {
                    // Skip a failing anime.
                }
            }

            $bar->advance();
            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Imported {$totalEpisodes} episode(s) and {$totalLinks} video link(s).");

        return self::SUCCESS;
    }
}
