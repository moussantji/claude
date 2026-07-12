<?php

namespace App\Console\Commands;

use App\Models\Anime;
use App\Models\EpisodeLink;
use App\Services\PlayerTransferService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Test a single driver end-to-end (extract → download → upload) before
 * running mass transfers. Validates credentials and the full pipeline.
 *
 * Examples:
 *   # Test a host using the first link found for that host in the DB
 *   php artisan episodes:test-driver vidmoly
 *
 *   # Test with a specific source URL
 *   php artisan episodes:test-driver vidmoly --url=https://vidmoly.to/embed-xxxx.html
 *
 *   # Test only extraction+download (skip upload)
 *   php artisan episodes:test-driver doodstream --no-upload
 *
 *   # Test a host for a specific anime
 *   php artisan episodes:test-driver streamtape --anime="Naruto"
 */
class EpisodesTestDriver extends Command
{
    protected $signature = 'episodes:test-driver
        {host : Platform to test (vidmoly, sibnet, sendvid, doodstream, streamtape, mp4upload, myvi, dailymotion)}
        {--url= : Specific source embed URL to test (otherwise picks one from the DB)}
        {--anime= : Pick a test link from this anime}
        {--no-upload : Only test extraction + download, skip the upload step}
        {--keep : Keep the downloaded temp file for inspection}';

    protected $description = 'Test a single player driver end-to-end (extract, download, upload) to validate it before mass transfer.';

    public function handle(PlayerTransferService $service): int
    {
        $host = strtolower((string) $this->argument('host'));
        $customUrl = (string) $this->option('url');
        $animeName = (string) $this->option('anime');
        $noUpload = (bool) $this->option('no-upload');
        $keep = (bool) $this->option('keep');

        // 1. Resolve the driver
        $driver = $service->driverByName($host);
        if (! $driver) {
            $this->error("Unknown host: {$host}");
            $this->line('Available: '.implode(', ', $service->availablePlatforms()));

            return self::FAILURE;
        }

        $this->info("═══ Testing driver: {$driver->name()} ═══");
        $this->newLine();

        // 2. Resolve a source URL to test
        $sourceUrl = $customUrl;
        if ($sourceUrl === '') {
            $sourceUrl = $this->pickSourceUrl($host, $animeName);
            if ($sourceUrl === null) {
                $this->error("No {$host} link found in the DB. Use --url= to pass one manually.");

                return self::FAILURE;
            }
        }

        $this->line("Source URL: <fg=cyan>{$sourceUrl}</>");
        $this->newLine();

        // 3. Check credentials (unless --no-upload)
        $credentials = $this->getTargetCredentials($driver->name());
        if (! $noUpload) {
            if (empty($credentials)) {
                $this->error("✗ No target credentials configured for {$driver->name()}.");
                $this->line("  Set TRANSFER_".strtoupper($driver->name())."_* in your .env");
                $this->line("  Or run with --no-upload to test extraction only.");

                return self::FAILURE;
            }
            $this->line("✓ Credentials found: ".implode(', ', array_keys($credentials)));
        }
        $this->newLine();

        // ═══ STEP 1: EXTRACT ═══
        $this->line("<fg=yellow>[1/3]</> Extracting direct video URL...");
        $directUrl = null;
        try {
            $start = microtime(true);
            $directUrl = $driver->extractDirectUrl($sourceUrl);
            $elapsed = round(microtime(true) - $start, 2);

            if (! $directUrl) {
                $this->error("  ✗ Extraction FAILED — could not find a direct video URL.");
                $this->line("    The page structure may have changed, or the video is offline.");

                return self::FAILURE;
            }
            $this->info("  ✓ Extracted in {$elapsed}s");
            $this->line("    → ".(strlen($directUrl) > 90 ? substr($directUrl, 0, 90).'…' : $directUrl));
        } catch (Throwable $e) {
            $this->error("  ✗ Extraction threw: {$e->getMessage()}");

            return self::FAILURE;
        }
        $this->newLine();

        // ═══ STEP 2: DOWNLOAD ═══
        $this->line("<fg=yellow>[2/3]</> Downloading video...");
        $tempFile = storage_path('app/temp-transfers/test_'.$host.'_'.time().'.mp4');
        @mkdir(dirname($tempFile), 0755, true);

        try {
            $start = microtime(true);
            $ok = $driver->download($directUrl, $tempFile);
            $elapsed = round(microtime(true) - $start, 2);

            if (! $ok || ! file_exists($tempFile)) {
                $this->error("  ✗ Download FAILED.");
                @unlink($tempFile);

                return self::FAILURE;
            }

            $size = filesize($tempFile);
            $sizeMb = round($size / 1048576, 2);
            $this->info("  ✓ Downloaded {$sizeMb} MB in {$elapsed}s");
            $this->line("    → {$tempFile}");

            if ($size < 100000) {
                $this->warn("  ⚠ File is suspiciously small ({$size} bytes) — may be an error page, not a video.");
            }
        } catch (Throwable $e) {
            $this->error("  ✗ Download threw: {$e->getMessage()}");
            @unlink($tempFile);

            return self::FAILURE;
        }
        $this->newLine();

        // ═══ STEP 3: UPLOAD ═══
        if ($noUpload) {
            $this->line("<fg=gray>[3/3]</> Upload skipped (--no-upload).");
            if (! $keep) {
                @unlink($tempFile);
            } else {
                $this->line("  Temp file kept: {$tempFile}");
            }
            $this->newLine();
            $this->info("═══ EXTRACTION + DOWNLOAD OK for {$driver->name()} ═══");

            return self::SUCCESS;
        }

        $this->line("<fg=yellow>[3/3]</> Uploading to target account...");
        try {
            $start = microtime(true);
            $result = $driver->upload($tempFile, "TEST - {$driver->name()} - ".date('Y-m-d H:i'), $credentials);
            $elapsed = round(microtime(true) - $start, 2);

            $this->info("  ✓ Uploaded in {$elapsed}s");
            $this->line("    File code: ".($result['file_code'] ?? 'n/a'));
            $this->line("    New embed: <fg=green>{$result['embed_url']}</>");
        } catch (Throwable $e) {
            $this->error("  ✗ Upload FAILED: {$e->getMessage()}");
            if (! $keep) {
                @unlink($tempFile);
            }

            return self::FAILURE;
        }

        // Cleanup
        if (! $keep) {
            @unlink($tempFile);
        } else {
            $this->line("  Temp file kept: {$tempFile}");
        }

        $this->newLine();
        $this->info("═══ ✓ FULL PIPELINE OK for {$driver->name()} ═══");
        $this->line("You can now safely run mass transfers with this host.");

        return self::SUCCESS;
    }

    /**
     * Pick a source URL from the DB for the given host.
     */
    private function pickSourceUrl(string $host, string $animeName): ?string
    {
        $query = EpisodeLink::query()->where('host', 'like', "%{$host}%");

        if ($animeName !== '') {
            $anime = Anime::query()
                ->where('name', 'like', "%{$animeName}%")
                ->orWhere('slug', 'like', "%{$animeName}%")
                ->first();
            if ($anime) {
                $query->whereHas('episode.season', fn ($q) => $q->where('anime_id', $anime->id));
            }
        }

        return $query->value('url');
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
