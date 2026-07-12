<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\EpisodeLink;
use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Broadcast (transfer) ALL episodes of an anime across ALL its hosts at once.
 *
 * For each host the anime has links on, it downloads and re-uploads every
 * episode to the corresponding target account configured in .env.
 *
 * Examples:
 *   php artisan episodes:broadcast "Naruto"
 *   php artisan episodes:broadcast "Naruto" --lang=VF
 *   php artisan episodes:broadcast "Naruto" --season=1
 *   php artisan episodes:broadcast "Naruto" --dry-run
 *   php artisan episodes:broadcast "Naruto" --update-db
 */
class EpisodesBroadcast extends Command
{
    protected $signature = 'episodes:broadcast
        {anime : Anime name (partial match)}
        {--season= : Only this season (1-based position)}
        {--lang= : Only this language (e.g. VF, VOSTFR)}
        {--dry-run : Preview what would be transferred}
        {--update-db : Update episode_links with new URLs after transfer}
        {--skip-hosts= : Comma-separated hosts to skip (e.g. dailymotion,myvi)}';

    protected $description = 'Transfer ALL episodes of an anime on ALL its hosts to target accounts.';

    public function handle(PlayerTransferService $service): int
    {
        $animeName = (string) $this->argument('anime');
        $seasonPos = $this->option('season') !== null ? (int) $this->option('season') : null;
        $lang = (string) $this->option('lang');
        $dryRun = (bool) $this->option('dry-run');
        $updateDb = (bool) $this->option('update-db');
        $skipHosts = array_filter(array_map('trim', explode(',', (string) $this->option('skip-hosts'))));

        // 1. Find the anime
        $anime = Anime::query()
            ->where('name', 'like', "%{$animeName}%")
            ->orWhere('slug', 'like', "%{$animeName}%")
            ->first();

        if (! $anime) {
            $this->error("Anime not found: \"{$animeName}\"");
            $this->newLine();
            $this->line('Available animes (sample):');
            Anime::query()->limit(15)->pluck('name')->each(fn ($n) => $this->line("  • {$n}"));

            return self::FAILURE;
        }

        $this->info("Anime: {$anime->name}");
        $this->line("  URL: {$anime->url}");
        $this->line("  Languages: ".implode(', ', $anime->languages ?? []));
        $this->newLine();

        // 2. Get all episode links for this anime
        $query = EpisodeLink::query()
            ->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id))
            ->with('episode.season');

        if ($lang !== '') {
            $query->where('language', $lang);
        }

        if ($seasonPos !== null) {
            $query->whereHas('episode.season', fn ($q) => $q->where('position', $seasonPos - 1));
        }

        $links = $query->orderBy('id')->get();

        if ($links->isEmpty()) {
            $this->warn('No episode links found for this anime with the given filters.');

            return self::SUCCESS;
        }

        // 3. Group by host
        $byHost = $links->groupBy(fn ($l) => $l->host ?? 'unknown');

        // Filter out skipped hosts and hosts without drivers
        $hostsToProcess = [];
        foreach ($byHost as $host => $hostLinks) {
            if (in_array($host, $skipHosts, true)) {
                continue;
            }
            $driver = $service->driverForHost($host);
            if (! $driver) {
                continue;
            }
            $hostsToProcess[$host] = [
                'driver' => $driver,
                'links' => $hostLinks,
            ];
        }

        if (empty($hostsToProcess)) {
            $this->warn('No supported hosts found for this anime.');
            $this->line('Hosts in DB: '.implode(', ', $byHost->keys()->all()));
            $this->line('Supported platforms: '.implode(', ', $service->availablePlatforms()));

            return self::SUCCESS;
        }

        // 4. Display summary
        $this->info("Transfer plan for \"{$anime->name}\":");
        $this->newLine();

        $totalLinks = 0;
        foreach ($hostsToProcess as $host => $data) {
            $count = $data['links']->count();
            $totalLinks += $count;
            $langs = $data['links']->pluck('language')->unique()->sort()->implode(', ');
            $seasons = $data['links']->map(fn ($l) => $l->episode?->season?->position)->unique()->sort()->map(fn ($p) => 'S'.str_pad((string) (($p ?? 0) + 1), 2, '0', STR_PAD_LEFT))->implode(', ');
            $this->line("  <fg=cyan>{$host}</> → {$data['driver']->name()} target account");
            $this->line("    {$count} link(s) | Langs: {$langs} | Seasons: {$seasons}");
        }
        $this->newLine();
        $this->line("Total: <fg=yellow>{$totalLinks}</> link(s) across <fg=yellow>".count($hostsToProcess)."</> host(s)");
        $this->newLine();

        // 5. Dry run: show table and exit
        if ($dryRun) {
            $this->showDryRunTable($hostsToProcess, $anime);

            return self::SUCCESS;
        }

        // 6. Confirm
        if (! $this->confirm("Proceed with broadcasting {$totalLinks} episode(s)?", true)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        // 7. Process each host
        $totalTransferred = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($hostsToProcess as $host => $data) {
            $driver = $data['driver'];
            $hostLinks = $data['links'];

            $this->newLine();
            $this->info("━━━ {$host} → {$driver->name()} ({$hostLinks->count()} links) ━━━");

            $credentials = $this->getTargetCredentials($driver->name());
            if (empty($credentials)) {
                $this->warn("  ⊘ Skipping {$host}: no target credentials configured.");
                $this->line("    Set TRANSFER_".strtoupper($driver->name())."_* in your .env");
                $totalSkipped += $hostLinks->count();

                continue;
            }

            $bar = $this->output->createProgressBar($hostLinks->count());
            $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %message%");
            $bar->setMessage('');
            $bar->start();

            foreach ($hostLinks as $link) {
                $season = $link->episode?->season;
                $episode = $link->episode;

                $label = sprintf(
                    'S%02dE%02d [%s]',
                    ($season?->position ?? 0) + 1,
                    $episode?->index ?? 0,
                    $link->language ?? '?',
                );
                $bar->setMessage($label);

                $title = sprintf(
                    '%s - S%02dE%02d %s [%s]',
                    $anime->name,
                    ($season?->position ?? 0) + 1,
                    $episode?->index ?? 0,
                    $episode?->name ?? '',
                    $link->language ?? '',
                );

                try {
                    $result = $service->transfer(
                        $link->url,
                        trim($title),
                        $credentials,
                    );

                    $totalTransferred++;

                    if ($updateDb) {
                        $link->update([
                            'url' => $result['embed_url'],
                            'host' => parse_url($result['embed_url'], PHP_URL_HOST) ?? $link->host,
                        ]);
                    }

                    $bar->advance();
                } catch (Throwable $e) {
                    $totalFailed++;
                    $bar->advance();
                    $this->newLine();
                    $this->error("  ✗ {$label}: {$e->getMessage()}");
                }
            }

            $bar->finish();
            $this->newLine();
        }

        // 8. Final summary
        $this->newLine(2);
        $this->info("═══ BROADCAST COMPLETE: {$anime->name} ═══");
        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['<fg=green>✓ Transferred</>', $totalTransferred],
                ['<fg=red>✗ Failed</>', $totalFailed],
                ['<fg=yellow>⊘ Skipped</>', $totalSkipped],
                ['Total', $totalLinks],
            ]
        );

        if ($updateDb && $totalTransferred > 0) {
            $this->info("✓ Database updated with {$totalTransferred} new URL(s).");
        } elseif ($totalTransferred > 0 && ! $updateDb) {
            $this->newLine();
            $this->line('<fg=yellow>Tip:</> Re-run with --update-db to save new URLs to the database.');
        }

        return $totalFailed > 0 && $totalTransferred === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function showDryRunTable(array $hostsToProcess, Anime $anime): void
    {
        $this->info("DRY RUN — Episodes that would be broadcast for \"{$anime->name}\":");
        $this->newLine();

        foreach ($hostsToProcess as $host => $data) {
            $this->line("  <fg=cyan>[$host]</>");

            $rows = $data['links']->take(20)->map(function ($link) {
                $season = $link->episode?->season;
                $episode = $link->episode;

                return [
                    'S'.str_pad((string) (($season?->position ?? 0) + 1), 2, '0', STR_PAD_LEFT),
                    'E'.str_pad((string) ($episode?->index ?? 0), 2, '0', STR_PAD_LEFT),
                    $link->language ?? '?',
                    strlen($link->url) > 55 ? substr($link->url, 0, 55).'…' : $link->url,
                ];
            });

            $this->table(['Season', 'Episode', 'Lang', 'Source URL'], $rows->all());

            if ($data['links']->count() > 20) {
                $this->line('    ... and '.($data['links']->count() - 20).' more links.');
            }
            $this->newLine();
        }

        $this->line('Run without --dry-run to execute the broadcast.');
    }

    private function getTargetCredentials(string $platform): array
    {
        return match ($platform) {
            'vidmoly' => array_filter([
                'api_key' => env('TRANSFER_VIDMOLY_API_KEY', ''),
                'api_base' => env('TRANSFER_VIDMOLY_API_BASE', 'https://api.vidmoly.to'),
                'embed_base' => env('TRANSFER_VIDMOLY_EMBED_BASE', 'https://vidmoly.to'),
                'timeout' => (int) env('TRANSFER_VIDMOLY_TIMEOUT', 900),
            ]),
            'sibnet' => array_filter([
                'login' => env('TRANSFER_SIBNET_LOGIN', ''),
                'password' => env('TRANSFER_SIBNET_PASSWORD', ''),
            ]),
            'sendvid' => array_filter([
                'api_key' => env('TRANSFER_SENDVID_API_KEY', ''),
                'api_base' => env('TRANSFER_SENDVID_API_BASE', 'https://sendvid.com/api'),
            ]),
            'doodstream' => array_filter([
                'api_key' => env('TRANSFER_DOODSTREAM_API_KEY', ''),
                'api_base' => env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.co/api'),
            ]),
            'streamtape' => array_filter([
                'login' => env('TRANSFER_STREAMTAPE_LOGIN', ''),
                'api_key' => env('TRANSFER_STREAMTAPE_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'),
            ]),
            'mp4upload' => array_filter([
                'api_key' => env('TRANSFER_MP4UPLOAD_API_KEY', ''),
                'api_base' => env('TRANSFER_MP4UPLOAD_API_BASE', 'https://www.mp4upload.com/api'),
            ]),
            'myvi' => array_filter([
                'login' => env('TRANSFER_MYVI_LOGIN', ''),
                'password' => env('TRANSFER_MYVI_PASSWORD', ''),
                'api_base' => env('TRANSFER_MYVI_API_BASE', 'https://myvi.tv'),
            ]),
            'dailymotion' => array_filter([
                'api_key' => env('TRANSFER_DAILYMOTION_API_KEY', ''),
                'api_secret' => env('TRANSFER_DAILYMOTION_API_SECRET', ''),
                'username' => env('TRANSFER_DAILYMOTION_USERNAME', ''),
                'password' => env('TRANSFER_DAILYMOTION_PASSWORD', ''),
            ]),
            default => [],
        };
    }
}
