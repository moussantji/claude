<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\EpisodeLink;
use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Transfer episodes from one hosting account to another (same platform).
 *
 * Examples:
 *   # Transfer all Naruto VF episodes from vidmoly to a new vidmoly account
 *   php artisan episodes:transfer --anime="Naruto" --lang=VF --host=vidmoly
 *
 *   # Transfer a specific season
 *   php artisan episodes:transfer --anime="One Piece" --season=1 --lang=VOSTFR --host=vidmoly
 *
 *   # Transfer ALL episodes of a host (e.g. migrate your entire vidmoly library)
 *   php artisan episodes:transfer --host=vidmoly
 *
 *   # Dry run (preview what would be transferred)
 *   php artisan episodes:transfer --anime="Naruto" --host=vidmoly --dry-run
 *
 * Target credentials are configured in .env (see below) or passed via options.
 *
 * ENV variables per platform:
 *   TRANSFER_VIDMOLY_API_KEY=your_target_api_key
 *   TRANSFER_VIDMOLY_API_BASE=https://api.vidmoly.to
 *   TRANSFER_VIDMOLY_EMBED_BASE=https://vidmoly.to
 *
 *   TRANSFER_SIBNET_LOGIN=your_target_login
 *   TRANSFER_SIBNET_PASSWORD=your_target_password
 *
 *   TRANSFER_SENDVID_API_KEY=your_target_api_key
 */
class EpisodesTransfer extends Command
{
    protected $signature = 'episodes:transfer
        {--anime= : Filter by anime name (partial match)}
        {--season= : Filter by season position (1-based)}
        {--lang= : Filter by language (e.g. VF, VOSTFR)}
        {--host= : Only transfer links from this host (e.g. vidmoly, sibnet, sendvid)}
        {--limit=0 : Maximum number of links to transfer (0 = all)}
        {--dry-run : Preview without actually transferring}
        {--update-db : Update episode_links in the DB with new URLs after transfer}
        {--concurrency=1 : Number of parallel transfers (sequential by default)}
        {--target-api-key= : Override target API key}
        {--target-login= : Override target login (sibnet)}
        {--target-password= : Override target password (sibnet)}';

    protected $description = 'Transfer episode videos between hosting accounts (same platform: vidmoly→vidmoly, sibnet→sibnet, etc.)';

    public function handle(PlayerTransferService $service): int
    {
        $animeName = (string) $this->option('anime');
        $seasonPos = $this->option('season') !== null ? (int) $this->option('season') : null;
        $lang = (string) $this->option('lang');
        $hostFilter = (string) $this->option('host');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $updateDb = (bool) $this->option('update-db');

        // Build the query
        $query = EpisodeLink::query()
            ->with('episode.season.anime');

        if ($lang !== '') {
            $query->where('language', $lang);
        }

        if ($hostFilter !== '') {
            $query->where('host', 'like', "%{$hostFilter}%");
        }

        if ($animeName !== '') {
            $query->whereHas('episode.season.anime', function ($q) use ($animeName) {
                $q->where('name', 'like', "%{$animeName}%")
                  ->orWhere('slug', 'like', "%{$animeName}%");
            });
        }

        if ($seasonPos !== null) {
            $query->whereHas('episode.season', function ($q) use ($seasonPos) {
                $q->where('position', $seasonPos - 1); // 0-indexed in DB
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $query->orderBy('id');

        $links = $query->get();

        if ($links->isEmpty()) {
            $this->warn('No episode links found matching your filters.');
            $this->newLine();
            $this->line('Available platforms: '.implode(', ', $service->availablePlatforms()));
            $this->showFilterHelp();

            return self::SUCCESS;
        }

        // Group by host for clear display
        $byHost = $links->groupBy('host');

        $this->info("Found {$links->count()} link(s) to transfer:");
        foreach ($byHost as $host => $group) {
            $this->line("  • {$host}: {$group->count()} link(s)");
        }
        $this->newLine();

        if ($dryRun) {
            $this->showDryRun($links);

            return self::SUCCESS;
        }

        // Confirm
        if (! $this->confirm('Proceed with transfer?', true)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        // Process transfers
        $transferred = 0;
        $failed = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($links->count());
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% — %message%");
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($links as $link) {
            $anime = $link->episode?->season?->anime;
            $season = $link->episode?->season;
            $episode = $link->episode;

            $label = sprintf(
                '%s S%02d E%02d [%s] (%s)',
                $anime?->name ?? '?',
                ($season?->position ?? 0) + 1,
                $episode?->index ?? 0,
                $link->language ?? '?',
                $link->host ?? '?',
            );

            $bar->setMessage($label);

            $driver = $service->driverForHost($link->host ?? '');
            if (! $driver) {
                $this->newLine();
                $this->warn("  ⊘ Skip (no driver): {$label}");
                $skipped++;
                $bar->advance();

                continue;
            }

            $credentials = $this->getTargetCredentials($driver->name());
            if (empty($credentials)) {
                $this->newLine();
                $this->warn("  ⊘ Skip (no target credentials for {$driver->name()}): {$label}");
                $skipped++;
                $bar->advance();

                continue;
            }

            $title = sprintf(
                '%s - S%02d E%02d %s [%s]',
                $anime?->name ?? 'Episode',
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

                $transferred++;

                if ($updateDb) {
                    $link->update([
                        'url' => $result['embed_url'],
                        'host' => parse_url($result['embed_url'], PHP_URL_HOST) ?? $link->host,
                    ]);
                }

                $this->newLine();
                $this->info("  ✓ {$label}");
                $this->line("    → {$result['embed_url']}");
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  ✗ {$label}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Count'],
            [
                ['✓ Transferred', $transferred],
                ['✗ Failed', $failed],
                ['⊘ Skipped', $skipped],
                ['Total', $links->count()],
            ]
        );

        if ($updateDb && $transferred > 0) {
            $this->info("Database updated with {$transferred} new URL(s).");
        } elseif ($transferred > 0 && ! $updateDb) {
            $this->line('Tip: Use --update-db to save new URLs to the database.');
        }

        return $failed > 0 && $transferred === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get target account credentials for a given platform.
     */
    private function getTargetCredentials(string $platform): array
    {
        return match ($platform) {
            'vidmoly' => array_filter([
                'api_key' => $this->option('target-api-key') ?: env('TRANSFER_VIDMOLY_API_KEY', ''),
                'api_base' => env('TRANSFER_VIDMOLY_API_BASE', 'https://api.vidmoly.to'),
                'embed_base' => env('TRANSFER_VIDMOLY_EMBED_BASE', 'https://vidmoly.to'),
                'timeout' => (int) env('TRANSFER_VIDMOLY_TIMEOUT', 900),
            ]),
            'sibnet' => array_filter([
                'login' => $this->option('target-login') ?: env('TRANSFER_SIBNET_LOGIN', ''),
                'password' => $this->option('target-password') ?: env('TRANSFER_SIBNET_PASSWORD', ''),
            ]),
            'sendvid' => array_filter([
                'api_key' => $this->option('target-api-key') ?: env('TRANSFER_SENDVID_API_KEY', ''),
                'api_base' => env('TRANSFER_SENDVID_API_BASE', 'https://sendvid.com/api'),
            ]),
            'doodstream' => array_filter([
                'api_key' => $this->option('target-api-key') ?: env('TRANSFER_DOODSTREAM_API_KEY', ''),
                'api_base' => env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.co/api'),
            ]),
            'streamtape' => array_filter([
                'login' => $this->option('target-login') ?: env('TRANSFER_STREAMTAPE_LOGIN', ''),
                'api_key' => $this->option('target-api-key') ?: env('TRANSFER_STREAMTAPE_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'),
            ]),
            'mixdrop' => array_filter([
                'email' => env('TRANSFER_MIXDROP_EMAIL', ''),
                'api_key' => $this->option('target-api-key') ?: env('TRANSFER_MIXDROP_API_KEY', ''),
                'api_base' => env('TRANSFER_MIXDROP_API_BASE', 'https://api.mixdrop.ag'),
            ]),
            'myvi' => array_filter([
                'login' => $this->option('target-login') ?: env('TRANSFER_MYVI_LOGIN', ''),
                'password' => $this->option('target-password') ?: env('TRANSFER_MYVI_PASSWORD', ''),
                'api_base' => env('TRANSFER_MYVI_API_BASE', 'https://myvi.tv'),
            ]),
            'dailymotion' => array_filter([
                'api_key' => $this->option('target-api-key') ?: env('TRANSFER_DAILYMOTION_API_KEY', ''),
                'api_secret' => env('TRANSFER_DAILYMOTION_API_SECRET', ''),
                'username' => $this->option('target-login') ?: env('TRANSFER_DAILYMOTION_USERNAME', ''),
                'password' => $this->option('target-password') ?: env('TRANSFER_DAILYMOTION_PASSWORD', ''),
            ]),
            default => [],
        };
    }

    private function showDryRun($links): void
    {
        $this->info('DRY RUN — These episodes would be transferred:');
        $this->newLine();

        $rows = $links->take(50)->map(function ($link) {
            $anime = $link->episode?->season?->anime;
            $season = $link->episode?->season;
            $episode = $link->episode;

            return [
                $anime?->name ?? '?',
                'S'.str_pad((string) (($season?->position ?? 0) + 1), 2, '0', STR_PAD_LEFT),
                'E'.str_pad((string) ($episode?->index ?? 0), 2, '0', STR_PAD_LEFT),
                $link->language ?? '?',
                $link->host ?? '?',
                strlen($link->url) > 50 ? substr($link->url, 0, 50).'…' : $link->url,
            ];
        });

        $this->table(['Anime', 'Season', 'Episode', 'Lang', 'Host', 'Source URL'], $rows->all());

        if ($links->count() > 50) {
            $this->line('... and '.($links->count() - 50).' more.');
        }

        $this->newLine();
        $this->line('Run without --dry-run to execute the transfer.');
    }

    private function showFilterHelp(): void
    {
        $this->newLine();
        $this->line('Usage examples:');
        $this->line('  php artisan episodes:transfer --anime="Naruto" --lang=VF --host=vidmoly');
        $this->line('  php artisan episodes:transfer --anime="One Piece" --season=2 --host=sibnet');
        $this->line('  php artisan episodes:transfer --host=vidmoly --dry-run');
    }
}
