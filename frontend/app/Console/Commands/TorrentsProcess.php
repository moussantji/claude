<?php

namespace App\Console\Commands;

use App\Models\TorrentDownload;
use App\Services\TorrentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Process the torrent download queue.
 *
 * A torrent can be a SEASON PACK (many episodes). Each invocation:
 *   1. Starts pending downloads (aria2c)
 *   2. Polls active downloads; when complete, scans files & matches episodes
 *   3. Uploads a batch of the torrent's video files to the target platform
 *
 * Designed to run frequently via the scheduler (every 2 minutes).
 *
 * Examples:
 *   php artisan torrents:process               # process the queue
 *   php artisan torrents:process --limit=10    # more torrents per run
 *   php artisan torrents:process --status      # queue report only
 *   php artisan torrents:process --retry-failed
 *   php artisan torrents:process --max-minutes=5
 */
class TorrentsProcess extends Command
{
    protected $signature = 'torrents:process
        {--limit=5 : Max torrents to advance per invocation}
        {--max-minutes=0 : Stop cleanly after N minutes (0 = no limit)}
        {--status : Show queue status only (no processing)}
        {--retry-failed : Reset failed torrents AND failed files to pending}
        {--max-attempts=3 : Skip torrents that have failed more than N times}';

    protected $description = 'Process torrent queue: download via aria2c, then upload each episode file to the target player platform.';

    public function handle(TorrentService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxMinutes = max(0, (int) $this->option('max-minutes'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $deadline = $maxMinutes > 0 ? microtime(true) + $maxMinutes * 60 : null;

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('retry-failed')) {
            return $this->retryFailed($maxAttempts);
        }

        $this->info('═══ Torrents Process ═══');

        $stats = ['started' => 0, 'downloading' => 0, 'uploading' => 0, 'done' => 0, 'failed' => 0];

        // Advance in-flight torrents first (downloading, then uploading),
        // then start new ones.
        $jobs = TorrentDownload::whereIn('status', ['downloading', 'uploading'])
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $activeCount = $jobs->count();
        $slots = max(0, $limit - $activeCount);
        if ($slots > 0) {
            $pending = TorrentDownload::where('status', 'pending')
                ->where('attempts', '<', $maxAttempts)
                ->orderBy('created_at')
                ->limit($slots)
                ->get();
            $jobs = $jobs->concat($pending);
        }

        foreach ($jobs as $job) {
            if ($this->pastDeadline($deadline)) {
                $this->warn('⏱ Deadline atteinte — arrêt propre.');
                break;
            }
            $this->processOne($job, $service, $stats);
        }

        $this->newLine();
        $this->table(['Action', 'Count'], [
            ['⬇ Downloads started', $stats['started']],
            ['↻ Downloading', $stats['downloading']],
            ['⬆ Uploading (files)', $stats['uploading']],
            ['✓ Torrents done', $stats['done']],
            ['✗ Failed', $stats['failed']],
        ]);

        $remaining = TorrentDownload::whereIn('status', ['pending', 'downloading', 'uploading'])->count();
        if ($remaining > 0) {
            $this->line("📍 {$remaining} torrent(s) encore en file.");
        } else {
            $this->info('✓ File vide — tout est traité.');
        }

        return self::SUCCESS;
    }

    private function processOne(TorrentDownload $job, TorrentService $service, array &$stats): void
    {
        $label = $this->jobLabel($job);
        $before = $job->status;

        try {
            $service->processJob($job);
            $job->refresh();

            switch ($job->status) {
                case 'downloading':
                    if ($before === 'pending') {
                        $stats['started']++;
                        $this->line("  <fg=cyan>⬇</> {$label} — téléchargement lancé");
                    } else {
                        $stats['downloading']++;
                        $this->line("  <fg=blue>↻</> {$label} — {$job->progress}%");
                    }
                    break;

                case 'uploading':
                    $stats['uploading']++;
                    $this->line("  <fg=magenta>⬆</> {$label} — fichiers {$job->files_done}/{$job->files_total} (✗{$job->files_failed})");
                    $this->renderFileLines($job);
                    break;

                case 'done':
                    $stats['done']++;
                    $skipped = $job->files()->where('status', 'skipped')->count();
                    $suffix = $skipped > 0 ? " (⚠ {$skipped} non mappé(s))" : '';
                    $this->line("  <fg=green>✓</> {$label} — {$job->files_done}/{$job->files_total} épisode(s) uploadé(s){$suffix}");
                    break;

                case 'failed':
                    $stats['failed']++;
                    $this->line("  <fg=red>✗</> {$label} — " . mb_substr($job->error ?? '?', 0, 80));
                    break;

                default:
                    $this->line("  <fg=gray>…</> {$label} [{$job->status}]");
            }
        } catch (Throwable $e) {
            $stats['failed']++;
            $job->markFailed(mb_substr($e->getMessage(), 0, 500));
            $this->line("  <fg=red>✗</> {$label} — " . mb_substr($e->getMessage(), 0, 80));
        }
    }

    /**
     * Show recently-resolved files under an uploading torrent.
     */
    private function renderFileLines(TorrentDownload $job): void
    {
        $files = $job->files()
            ->whereIn('status', ['done', 'failed', 'skipped'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        foreach ($files as $file) {
            $ep = $file->detected_episode ? 'E' . str_pad((string) $file->detected_episode, 2, '0', STR_PAD_LEFT) : '?';
            $icon = match ($file->status) {
                'done' => '<fg=green>✓</>',
                'failed' => '<fg=red>✗</>',
                'skipped' => '<fg=yellow>⊘</>',
                default => '·',
            };
            $note = in_array($file->status, ['failed', 'skipped'], true)
                ? ' — ' . mb_substr($file->error ?? '', 0, 50)
                : '';
            $this->line("      {$icon} {$ep} {$file->file_name}{$note}");
        }
    }

    private function retryFailed(int $maxAttempts): int
    {
        // Re-arm failed torrents (download-stage failures)
        $torrents = TorrentDownload::where('status', 'failed')
            ->where('attempts', '<', $maxAttempts)
            ->update(['status' => 'pending', 'error' => null, 'progress' => 0]);

        // Re-arm failed individual files (upload-stage failures) and put their
        // torrent back into the uploading stage so they get retried.
        $failedFileTorrentIds = \App\Models\TorrentFile::whereIn('status', ['failed', 'skipped'])
            ->pluck('torrent_download_id')
            ->unique();

        $files = \App\Models\TorrentFile::whereIn('status', ['failed'])
            ->update(['status' => 'pending', 'error' => null]);

        if ($failedFileTorrentIds->isNotEmpty()) {
            TorrentDownload::whereIn('id', $failedFileTorrentIds)
                ->where('status', 'done')
                ->update(['status' => 'uploading']);
        }

        $this->info("{$torrents} torrent(s) et {$files} fichier(s) réarmé(s).");

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $counts = TorrentDownload::query()
            ->selectRaw('status, COUNT(*) as cnt')
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

        $active = TorrentDownload::whereIn('status', ['downloading', 'uploading'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        if ($active->isNotEmpty()) {
            $this->newLine();
            $this->info('Torrents actifs:');
            foreach ($active as $job) {
                $detail = $job->status === 'uploading'
                    ? "fichiers {$job->files_done}/{$job->files_total}"
                    : "{$job->progress}%";
                $this->line("  [{$job->status}] {$this->jobLabel($job)} — {$detail}");
            }
        }

        $failures = TorrentDownload::where('status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        if ($failures->isNotEmpty()) {
            $this->newLine();
            $this->warn('Échecs récents:');
            foreach ($failures as $job) {
                $this->line("  #{$job->id} {$this->jobLabel($job)} — " . mb_substr($job->error ?? '?', 0, 60));
            }
        }

        return self::SUCCESS;
    }

    private function jobLabel(TorrentDownload $job): string
    {
        $title = $job->title ?: ('Torrent #' . $job->id);

        return "{$title} → {$job->target_platform}";
    }

    private function pastDeadline(?float $deadline): bool
    {
        return $deadline !== null && microtime(true) >= $deadline;
    }
}
