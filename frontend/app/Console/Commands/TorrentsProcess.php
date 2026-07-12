<?php

namespace App\Console\Commands;

use App\Models\TorrentDownload;
use App\Services\TorrentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Process pending/in-progress torrent download jobs.
 *
 * This command is designed to be run frequently (e.g. every 2 minutes via the
 * scheduler). Each invocation:
 *   1. Picks up pending jobs → starts aria2c downloads
 *   2. Polls downloading jobs → checks if aria2c finished
 *   3. Processes uploading jobs → sends to target platform
 *
 * Examples:
 *   # Process all pending torrents (default: max 5 concurrent)
 *   php artisan torrents:process
 *
 *   # Increase concurrency
 *   php artisan torrents:process --limit=10
 *
 *   # Show status only (no processing)
 *   php artisan torrents:process --status
 *
 *   # Retry all failed jobs
 *   php artisan torrents:process --retry-failed
 *
 *   # Time-limited run (for shared hosting / cron)
 *   php artisan torrents:process --max-minutes=5
 *
 * Schedule (runs every 2 minutes):
 *   $schedule->command('torrents:process --limit=5')->everyTwoMinutes()->withoutOverlapping();
 */
class TorrentsProcess extends Command
{
    protected $signature = 'torrents:process
        {--limit=5 : Max jobs to process per invocation}
        {--max-minutes=0 : Stop cleanly after N minutes (0 = no limit)}
        {--status : Show queue status only (no processing)}
        {--retry-failed : Reset all failed jobs to pending}
        {--max-attempts=3 : Skip jobs that have failed more than N times}';

    protected $description = 'Process torrent download queue: download via aria2c, then upload to target player platforms.';

    public function handle(TorrentService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxMinutes = max(0, (int) $this->option('max-minutes'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $deadline = $maxMinutes > 0 ? microtime(true) + $maxMinutes * 60 : null;

        // --- Status report ---
        if ($this->option('status')) {
            return $this->showStatus();
        }

        // --- Retry failed ---
        if ($this->option('retry-failed')) {
            $count = TorrentDownload::where('status', 'failed')
                ->where('attempts', '<', $maxAttempts)
                ->update(['status' => 'pending', 'error' => null, 'progress' => 0]);

            $this->info("{$count} job(s) réarmé(s).");

            $skipped = TorrentDownload::where('status', 'failed')
                ->where('attempts', '>=', $maxAttempts)
                ->count();
            if ($skipped > 0) {
                $this->warn("{$skipped} job(s) ignoré(s) (>= {$maxAttempts} tentatives). Utilisez --max-attempts pour forcer.");
            }

            return self::SUCCESS;
        }

        // --- Process queue ---
        $this->info('═══ Torrents Process ═══');

        $stats = ['started' => 0, 'progressed' => 0, 'uploaded' => 0, 'failed' => 0, 'skipped' => 0];

        // 1. Poll active downloads (downloading status)
        $downloading = TorrentDownload::where('status', 'downloading')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($downloading as $job) {
            if ($this->pastDeadline($deadline)) {
                break;
            }
            $this->processOne($job, $service, $stats);
        }

        // 2. Process uploads (uploading status)
        $uploading = TorrentDownload::where('status', 'uploading')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($uploading as $job) {
            if ($this->pastDeadline($deadline)) {
                break;
            }
            $this->processOne($job, $service, $stats);
        }

        // 3. Start new downloads (pending status)
        $activeCount = TorrentDownload::whereIn('status', ['downloading', 'uploading'])->count();
        $slotsAvailable = max(0, $limit - $activeCount);

        if ($slotsAvailable > 0) {
            $pending = TorrentDownload::where('status', 'pending')
                ->where('attempts', '<', $maxAttempts)
                ->orderBy('created_at')
                ->limit($slotsAvailable)
                ->get();

            foreach ($pending as $job) {
                if ($this->pastDeadline($deadline)) {
                    break;
                }
                $this->processOne($job, $service, $stats);
            }
        }

        // --- Summary ---
        $this->newLine();
        $this->table(['Action', 'Count'], [
            ['Downloads started', $stats['started']],
            ['Downloads progressed', $stats['progressed']],
            ['Uploads completed', $stats['uploaded']],
            ['Failed', $stats['failed']],
            ['Skipped (deadline)', $stats['skipped']],
        ]);

        $remaining = TorrentDownload::whereIn('status', ['pending', 'downloading', 'uploading'])->count();
        if ($remaining > 0) {
            $this->line("📍 {$remaining} job(s) restant(s) dans la file.");
        } else {
            $this->info('✓ File vide — tout est traité.');
        }

        return self::SUCCESS;
    }

    /**
     * Process a single torrent job through its current stage.
     */
    private function processOne(TorrentDownload $job, TorrentService $service, array &$stats): void
    {
        $label = $this->jobLabel($job);

        try {
            $previousStatus = $job->status;
            $service->processJob($job);
            $job->refresh();

            match (true) {
                $previousStatus === 'pending' && $job->status === 'downloading' => (function () use (&$stats, $label) {
                    $stats['started']++;
                    $this->line("  <fg=cyan>⬇</> {$label} — téléchargement lancé");
                })(),

                $job->status === 'downloading' => (function () use (&$stats, $label, $job) {
                    $stats['progressed']++;
                    $this->line("  <fg=blue>↻</> {$label} — {$job->progress}%");
                })(),

                $job->status === 'done' => (function () use (&$stats, $label, $job) {
                    $stats['uploaded']++;
                    $this->line("  <fg=green>✓</> {$label} → {$job->embed_url}");
                })(),

                $job->status === 'failed' => (function () use (&$stats, $label, $job) {
                    $stats['failed']++;
                    $this->line("  <fg=red>✗</> {$label} — " . mb_substr($job->error ?? '?', 0, 80));
                })(),

                default => (function () use (&$stats, $label, $job) {
                    $stats['progressed']++;
                    $this->line("  <fg=gray>…</> {$label} [{$job->status}]");
                })(),
            };
        } catch (Throwable $e) {
            $stats['failed']++;
            $job->markFailed(mb_substr($e->getMessage(), 0, 500));
            $this->line("  <fg=red>✗</> {$label} — " . mb_substr($e->getMessage(), 0, 80));
        }
    }

    private function showStatus(): int
    {
        $counts = TorrentDownload::query()
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $this->info('═══ Torrent Queue Status ═══');
        $this->table(['Status', 'Count'], [
            ['Pending', $counts['pending'] ?? 0],
            ['Downloading', $counts['downloading'] ?? 0],
            ['Uploading', $counts['uploading'] ?? 0],
            ['Done', $counts['done'] ?? 0],
            ['Failed', $counts['failed'] ?? 0],
        ]);

        // Show recent activity
        $recent = TorrentDownload::whereIn('status', ['downloading', 'uploading'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        if ($recent->isNotEmpty()) {
            $this->newLine();
            $this->info('Active jobs:');
            foreach ($recent as $job) {
                $this->line("  [{$job->status}] {$this->jobLabel($job)} — {$job->progress}%");
            }
        }

        // Show recent failures
        $failures = TorrentDownload::where('status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        if ($failures->isNotEmpty()) {
            $this->newLine();
            $this->warn('Recent failures:');
            foreach ($failures as $job) {
                $this->line("  #{$job->id} {$job->title} — " . mb_substr($job->error ?? '?', 0, 60));
            }
        }

        return self::SUCCESS;
    }

    private function jobLabel(TorrentDownload $job): string
    {
        $title = $job->title ?: ('Torrent #' . $job->id);
        $platform = $job->target_platform;

        return "{$title} → {$platform}";
    }

    private function pastDeadline(?float $deadline): bool
    {
        return $deadline !== null && microtime(true) >= $deadline;
    }
}
