<?php

namespace App\Console\Commands;

use App\Models\TorrentDownload;
use App\Models\TorrentFile;
use App\Services\TorrentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Process the "torrent" remote-upload queue.
 *
 * The admin submits direct download links; each is sent to the target player
 * host which fetches it itself. This command advances each batch: it submits
 * pending links and polls the ones the host is still fetching.
 *
 * Designed to run frequently via the scheduler (every 2 minutes).
 *
 * Examples:
 *   php artisan torrents:process               # advance the queue
 *   php artisan torrents:process --limit=10    # more batches per run
 *   php artisan torrents:process --status      # report only
 *   php artisan torrents:process --retry-failed
 *   php artisan torrents:process --max-minutes=5
 */
class TorrentsProcess extends Command
{
    protected $signature = 'torrents:process
        {--limit=5 : Max batches to advance per invocation}
        {--max-minutes=0 : Stop cleanly after N minutes (0 = no limit)}
        {--status : Show queue status only (no processing)}
        {--retry-failed : Reset failed batches AND failed links to pending}
        {--max-attempts=3 : Skip batches that have failed more than N times}';

    protected $description = 'Advance torrent remote-upload batches: send links to the target player host and poll until each episode is live.';

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

        $stats = ['downloading' => 0, 'resolving' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0];

        // In-flight batches first (aria2 download / RD resolution / upload),
        // then new ones.
        $jobs = TorrentDownload::whereIn('status', ['downloading', 'resolving', 'processing'])
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $slots = max(0, $limit - $jobs->count());
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
            ['⬇ Téléchargement (aria2)', $stats['downloading']],
            ['⇣ Real-Debrid', $stats['resolving']],
            ['↻ Envoi lecteur', $stats['processing']],
            ['✓ Terminés', $stats['done']],
            ['✗ Échecs', $stats['failed']],
        ]);

        $remaining = TorrentDownload::whereIn('status', ['pending', 'downloading', 'resolving', 'processing'])->count();
        if ($remaining > 0) {
            $this->line("📍 {$remaining} batch(es) encore en file.");
        } else {
            $this->info('✓ File vide — tout est traité.');
        }

        return self::SUCCESS;
    }

    private function processOne(TorrentDownload $job, TorrentService $service, array &$stats): void
    {
        $label = $this->jobLabel($job);

        try {
            $service->processJob($job);
            $job->refresh();

            switch ($job->status) {
                case 'downloading':
                    $stats['downloading']++;
                    $this->line("  <fg=cyan>⬇</> {$label} — aria2 {$job->debrid_progress}%");
                    break;

                case 'resolving':
                    $stats['resolving']++;
                    $this->line("  <fg=cyan>⇣</> {$label} — Real-Debrid {$job->debrid_progress}%");
                    break;

                case 'processing':
                    $stats['processing']++;
                    // Per-player upload counts (multi-target).
                    $tDone = \App\Models\TorrentFileTarget::whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))->where('status', 'done')->count();
                    $tPend = \App\Models\TorrentFileTarget::whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))->whereIn('status', ['pending', 'queued'])->count();
                    $tFail = \App\Models\TorrentFileTarget::whereHas('file', fn ($q) => $q->where('torrent_download_id', $job->id))->where('status', 'failed')->count();
                    $players = implode('+', $job->platforms());
                    $this->line("  <fg=magenta>↻</> {$label} — épisodes {$job->files_done}/{$job->files_total} · uploads [{$players}] ✓{$tDone} ⏳{$tPend} ✗{$tFail}");
                    $this->renderFileLines($job);
                    break;

                case 'done':
                    $stats['done']++;
                    $skipped = $job->files()->where('status', 'skipped')->count();
                    $suffix = $skipped > 0 ? " (⚠ {$skipped} non mappé(s))" : '';
                    $this->line("  <fg=green>✓</> {$label} — {$job->files_done}/{$job->files_total} épisode(s) en ligne{$suffix}");
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

    private function renderFileLines(TorrentDownload $job): void
    {
        $files = $job->files()
            ->with('targets')
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
            // Per-player result for this episode (✓doodstream ✗vidmoly ...).
            $players = '';
            foreach ($file->targets as $t) {
                $mark = match ($t->status) {
                    'done' => '✓', 'failed' => '✗', 'queued' => '⏳', default => '·',
                };
                $players .= " {$mark}{$t->platform}";
            }
            $note = in_array($file->status, ['failed', 'skipped'], true)
                ? ' — ' . mb_substr($file->error ?? '', 0, 40)
                : '';
            $this->line("      {$icon} {$ep} " . mb_substr($file->file_name ?? $file->source_url ?? '?', 0, 44) . $players . $note);
        }
    }

    private function retryFailed(int $maxAttempts): int
    {
        $batches = TorrentDownload::where('status', 'failed')
            ->where('attempts', '<', $maxAttempts)
            ->update(['status' => 'pending', 'error' => null]);

        // Re-arm failed per-player uploads + put their files/batches back.
        $failedTargets = \App\Models\TorrentFileTarget::where('status', 'failed')->get(['id', 'torrent_file_id']);
        if ($failedTargets->isNotEmpty()) {
            \App\Models\TorrentFileTarget::whereIn('id', $failedTargets->pluck('id'))
                ->update(['status' => 'pending', 'error' => null, 'remote_job_id' => null]);
            $fileIds = $failedTargets->pluck('torrent_file_id')->unique();
            TorrentFile::whereIn('id', $fileIds)->update(['status' => 'pending']);
            $batchIds = TorrentFile::whereIn('id', $fileIds)->pluck('torrent_download_id')->unique();
            TorrentDownload::whereIn('id', $batchIds)->where('status', 'done')->update(['status' => 'processing']);
        }

        $affected = TorrentFile::where('status', 'failed')
            ->pluck('torrent_download_id')->unique();

        $files = TorrentFile::where('status', 'failed')
            ->update(['status' => 'pending', 'error' => null, 'remote_job_id' => null]);

        if ($affected->isNotEmpty()) {
            TorrentDownload::whereIn('id', $affected)
                ->where('status', 'done')
                ->update(['status' => 'processing']);
        }

        $this->info("{$batches} batch(es) et {$files} lien(s) réarmé(s).");

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
            ['Downloading (aria2)', $counts['downloading'] ?? 0],
            ['Resolving (RD)', $counts['resolving'] ?? 0],
            ['Processing', $counts['processing'] ?? 0],
            ['Done', $counts['done'] ?? 0],
            ['Failed', $counts['failed'] ?? 0],
        ]);

        $active = TorrentDownload::whereIn('status', ['downloading', 'resolving', 'processing'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        if ($active->isNotEmpty()) {
            $this->newLine();
            $this->info('Batches actifs:');
            foreach ($active as $job) {
                $detail = match ($job->status) {
                    'downloading' => "aria2 {$job->debrid_progress}%",
                    'resolving' => "Real-Debrid {$job->debrid_progress}%",
                    default => "{$job->files_done}/{$job->files_total} live (✗{$job->files_failed})",
                };
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
        $title = $job->title ?: ('Batch #' . $job->id);

        return "{$title} → {$job->target_platform}";
    }

    private function pastDeadline(?float $deadline): bool
    {
        return $deadline !== null && microtime(true) >= $deadline;
    }
}
