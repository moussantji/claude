<?php

namespace App\Console\Commands;

use App\Models\MediaUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * List your uploaded media and their stored links.
 *
 *   php artisan media:list
 *   php artisan media:list --status=uploaded
 *   php artisan media:list --json
 */
class ListMedia extends Command
{
    protected $signature = 'media:list
        {--status= : Filter by status: pending, uploaded, failed}
        {--json : Output as JSON}';

    protected $description = 'List your uploaded media files and their links.';

    public function handle(): int
    {
        $query = MediaUpload::query()->orderByDesc('updated_at');
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->warn('No uploads recorded yet. Use: php artisan media:upload <path>');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $rows->map(fn ($r) => [
                    'title' => $r->title,
                    'status' => $r->status,
                    'provider' => $r->provider,
                    'embed_url' => $r->embed_url,
                    'source_path' => $r->source_path,
                    'uploaded_at' => optional($r->uploaded_at)->toIso8601String(),
                ])->all(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Title', 'Status', 'Provider', 'Link'],
            $rows->map(fn ($r) => [
                Str::limit((string) $r->title, 40),
                $r->status,
                $r->provider,
                $r->embed_url ?: '—',
            ])->all()
        );
        $this->line($rows->count().' upload(s).');

        return self::SUCCESS;
    }
}
