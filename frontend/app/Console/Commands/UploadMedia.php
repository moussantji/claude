<?php

namespace App\Console\Commands;

use App\Models\MediaUpload;
use App\Services\VidmolyClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Upload YOUR OWN video files to your vidmoly account and store the links.
 *
 *   php artisan media:upload /path/to/video.mp4
 *   php artisan media:upload /path/to/folder --recursive
 *
 * Only use this with content you created or hold the rights to.
 */
class UploadMedia extends Command
{
    protected $signature = 'media:upload
        {path : A file, or a directory to scan}
        {--title= : Title for a single file (defaults to the filename)}
        {--ext=mp4,mkv,avi,mov,webm,m4v : Extensions to include when scanning a directory}
        {--recursive : Recurse into subdirectories}
        {--force : Re-upload files already marked as uploaded}';

    protected $description = 'Upload your own media files to vidmoly and store the resulting links.';

    public function handle(VidmolyClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('VIDMOLY_API_KEY is not set. Add it to your .env first.');

            return self::FAILURE;
        }

        $files = $this->resolveFiles((string) $this->argument('path'));
        if ($files === []) {
            $this->warn('No matching files found.');

            return self::SUCCESS;
        }

        $this->info(count($files).' file(s) to process.');
        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            $record = MediaUpload::firstOrNew(['path_hash' => hash('sha256', $file)]);

            if ($record->exists && $record->status === 'uploaded' && ! $this->option('force')) {
                $this->line("• skip (already uploaded): {$file}");
                $skipped++;

                continue;
            }

            $record->fill([
                'source_path' => $file,
                'title' => $this->titleFor($file, count($files)),
                'provider' => 'vidmoly',
                'size_bytes' => @filesize($file) ?: null,
                'status' => 'pending',
                'error' => null,
            ])->save();

            try {
                $result = $client->upload($file, $record->title);

                $record->fill([
                    'file_code' => $result['file_code'],
                    'embed_url' => $result['embed_url'],
                    'status' => 'uploaded',
                    'uploaded_at' => now(),
                    'error' => null,
                ])->save();

                $this->info("✓ {$file}");
                $this->line('  '.$result['embed_url']);
                $uploaded++;
            } catch (Throwable $e) {
                $record->fill(['status' => 'failed', 'error' => $e->getMessage()])->save();
                $this->warn("✗ {$file}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Uploaded: {$uploaded}, skipped: {$skipped}, failed: {$failed}.");
        $this->line('List stored links with: php artisan media:list');

        return $failed > 0 && $uploaded === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveFiles(string $path): array
    {
        if (is_file($path)) {
            return [realpath($path) ?: $path];
        }

        if (! is_dir($path)) {
            $this->error("Path not found: {$path}");

            return [];
        }

        $exts = array_filter(array_map(
            fn ($e) => strtolower(trim($e, ". \t")),
            explode(',', (string) $this->option('ext'))
        ));

        $iterator = $this->option('recursive')
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS))
            : new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);

        $files = [];
        foreach ($iterator as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isFile() && in_array(strtolower($entry->getExtension()), $exts, true)) {
                $files[] = $entry->getRealPath() ?: $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function titleFor(string $file, int $total): string
    {
        $option = (string) $this->option('title');
        if ($total === 1 && $option !== '') {
            return $option;
        }

        return pathinfo($file, PATHINFO_FILENAME);
    }
}
