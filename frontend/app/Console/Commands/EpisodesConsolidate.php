<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\EpisodeLink;
use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Consolidate videos from UNRELIABLE / non-monetizing hosts onto a RELIABLE
 * host that pays (monetizes). Cross-platform transfer:
 *   sendvid  → doodstream
 *   sibnet   → doodstream
 *   myvi     → doodstream
 *
 * The source video is extracted, downloaded, then uploaded to the target
 * monetizing host. The DB link can be updated (or a new link added).
 *
 * Monetizing hosts (pay-per-view): doodstream, vidmoly, streamtape, filemoon.
 *
 * Examples:
 *   # Move all sendvid/sibnet/myvi videos to doodstream (the paying host)
 *   php artisan episodes:consolidate --to=doodstream --update-db
 *
 *   # Only a specific anime
 *   php artisan episodes:consolidate --to=doodstream --anime="Naruto"
 *
 *   # Choose which source hosts to consolidate
 *   php artisan episodes:consolidate --to=vidmoly --from=sendvid,sibnet
 *
 *   # Preview
 *   php artisan episodes:consolidate --to=doodstream --dry-run
 *
 *   # Add a NEW link instead of replacing (keeps the original)
 *   php artisan episodes:consolidate --to=doodstream --add-link
 */
class EpisodesConsolidate extends Command
{
    /** Hosts considered unreliable / non-monetizing sources by default. */
    private const DEFAULT_UNRELIABLE = ['sendvid', 'sibnet', 'myvi'];

    /** Hosts that monetize (valid targets). */
    private const MONETIZING = ['doodstream', 'vidmoly', 'streamtape', 'filemoon'];

    protected $signature = 'episodes:consolidate
        {--to= : Target monetizing host (doodstream, vidmoly, streamtape, filemoon)}
        {--from= : Comma-separated source hosts (default: sendvid,sibnet,myvi)}
        {--anime= : Only this anime (name or ID)}
        {--lang= : Only this language}
        {--limit=0 : Max links to process (0 = all)}
        {--add-link : Add a NEW link on the target host instead of replacing the source}
        {--update-db : Update/replace the DB link with the new target URL}
        {--dry-run : Preview without transferring}';

    protected $description = 'Move videos from unreliable hosts (sendvid/sibnet/myvi) to a monetizing host (doodstream/vidmoly/...).';

    public function handle(PlayerTransferService $service): int
    {
        $target = strtolower((string) $this->option('to'));
        $addLink = (bool) $this->option('add-link');
        $updateDb = (bool) $this->option('update-db');
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $lang = (string) $this->option('lang');

        // 1. Validate target
        if ($target === '') {
            $this->error('You must specify a target host with --to=');
            $this->line('Monetizing hosts: '.implode(', ', self::MONETIZING));

            return self::FAILURE;
        }

        if (! $service->driverByName($target)) {
            $this->error("Unknown target host: {$target}");
            $this->line('Available: '.implode(', ', $service->availablePlatforms()));

            return self::FAILURE;
        }

        if (! in_array($target, self::MONETIZING, true)) {
            $this->warn("⚠ '{$target}' is not in the known monetizing list (".implode(', ', self::MONETIZING).").");
            if (! $this->confirm('Continue anyway?', false)) {
                return self::SUCCESS;
            }
        }

        // 2. Determine source hosts
        $sourceHosts = $this->option('from')
            ? array_filter(array_map('trim', explode(',', strtolower((string) $this->option('from')))))
            : self::DEFAULT_UNRELIABLE;

        // 3. Check target credentials
        $credentials = $this->getTargetCredentials($target);
        if (empty($credentials) && ! $dryRun) {
            $this->error("No credentials configured for target '{$target}'.");
            $this->line("Set TRANSFER_".strtoupper($target)."_* in your .env");

            return self::FAILURE;
        }

        // 4. Build the query for source links
        $query = EpisodeLink::query()->with('episode.season.anime');

        $query->where(function ($q) use ($sourceHosts) {
            foreach ($sourceHosts as $h) {
                $q->orWhere('host', 'like', "%{$h}%");
            }
        });

        if ($lang !== '') {
            $query->where('language', $lang);
        }

        if ($animeOpt = $this->option('anime')) {
            $anime = is_numeric($animeOpt)
                ? Anime::find((int) $animeOpt)
                : Anime::where('name', 'like', "%{$animeOpt}%")->orWhere('slug', 'like', "%{$animeOpt}%")->first();

            if (! $anime) {
                $this->error("Anime not found: {$animeOpt}");

                return self::FAILURE;
            }
            $query->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $links = $query->orderBy('id')->get();

        if ($links->isEmpty()) {
            $this->warn('No links found on the unreliable hosts ('.implode(', ', $sourceHosts).').');

            return self::SUCCESS;
        }

        // 5. Summary
        $byHost = $links->groupBy('host');
        $this->info("═══ Consolidate → {$target} ═══");
        $this->line("  Source hosts: ".implode(', ', $sourceHosts));
        $this->line("  Mode: ".($addLink ? 'ADD new link (keep original)' : ($updateDb ? 'REPLACE original link' : 'no DB change')));
        $this->newLine();
        foreach ($byHost as $host => $group) {
            $this->line("  {$host}: {$group->count()} link(s) → {$target}");
        }
        $this->line("  Total: {$links->count()} link(s)");
        $this->newLine();

        if ($dryRun) {
            $rows = $links->take(30)->map(function ($l) use ($target) {
                $a = $l->episode?->season?->anime;

                return [
                    $a?->name ?? '?',
                    'S'.str_pad((string) (($l->episode?->season?->position ?? 0) + 1), 2, '0', STR_PAD_LEFT)
                        .'E'.str_pad((string) ($l->episode?->index ?? 0), 2, '0', STR_PAD_LEFT),
                    $l->language,
                    $l->host,
                    '→ '.$target,
                ];
            });
            $this->table(['Anime', 'Ep', 'Lang', 'From', 'To'], $rows->all());
            if ($links->count() > 30) {
                $this->line('... and '.($links->count() - 30).' more.');
            }
            $this->line('Run without --dry-run to execute.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Consolidate {$links->count()} video(s) onto {$target}?", true)) {
            return self::SUCCESS;
        }

        // 6. Process
        $done = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($links->count());
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %message%");
        $bar->start();

        foreach ($links as $link) {
            $episode = $link->episode;
            $season = $episode?->season;
            $anime = $season?->anime;

            $label = sprintf('%s S%02dE%02d [%s] %s→%s',
                $anime?->name ?? '?',
                ($season?->position ?? 0) + 1,
                $episode?->index ?? 0,
                $link->language ?? '?',
                $link->host ?? '?',
                $target,
            );
            $bar->setMessage($label);

            $title = sprintf('%s - S%02dE%02d %s [%s]',
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
                    null,
                    $target, // cross-host target
                );

                if ($addLink) {
                    // Add a NEW link on the target host, keep the original
                    EpisodeLink::create([
                        'episode_id' => $link->episode_id,
                        'language' => $link->language,
                        'position' => 0, // put the monetizing host first
                        'url' => $result['embed_url'],
                        'host' => $result['host'] ?? $target,
                    ]);
                } elseif ($updateDb) {
                    // Replace the original link
                    $link->update([
                        'url' => $result['embed_url'],
                        'host' => $result['host'] ?? $target,
                    ]);
                }

                $done++;
                $this->newLine();
                $this->line("  <fg=green>✓</> {$label}");
                $this->line("    → {$result['embed_url']}");
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->line("  <fg=red>✗</> {$label}: ".substr($e->getMessage(), 0, 80));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Status', 'Count'], [
            ['✓ Consolidated', $done],
            ['✗ Failed', $failed],
            ['Total', $links->count()],
        ]);

        if ($done > 0) {
            if ($addLink) {
                $this->info("✓ {$done} new {$target} link(s) added (originals kept).");
            } elseif ($updateDb) {
                $this->info("✓ {$done} link(s) replaced with {$target} URLs.");
            } else {
                $this->line("Tip: use --update-db to replace, or --add-link to add alongside originals.");
            }
        }

        return $failed > 0 && $done === 0 ? self::FAILURE : self::SUCCESS;
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
            'doodstream' => array_filter([
                'api_key' => env('TRANSFER_DOODSTREAM_API_KEY', ''),
                'api_base' => env('TRANSFER_DOODSTREAM_API_BASE', 'https://doodapi.co/api'),
            ]),
            'streamtape' => array_filter([
                'login' => env('TRANSFER_STREAMTAPE_LOGIN', ''),
                'api_key' => env('TRANSFER_STREAMTAPE_API_KEY', ''),
                'api_base' => env('TRANSFER_STREAMTAPE_API_BASE', 'https://api.streamtape.com'),
            ]),
            'filemoon' => array_filter([
                'api_key' => env('TRANSFER_FILEMOON_API_KEY', ''),
                'api_base' => env('TRANSFER_FILEMOON_API_BASE', 'https://filemoonapi.com/api'),
                'embed_base' => env('TRANSFER_FILEMOON_EMBED_BASE', 'https://filemoon.sx'),
            ]),
            default => [],
        };
    }
}
