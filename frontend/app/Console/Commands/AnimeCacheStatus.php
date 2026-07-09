<?php

namespace App\Console\Commands;

use App\Models\AnimeCache;
use App\Services\AnimeCacheStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Inspect what anime-sama data is currently stored in the database.
 *
 *   php artisan anime:cache-status                  # summary by type
 *   php artisan anime:cache-status --type=search    # list keys of a type
 *   php artisan anime:cache-status --key=all-catalogues        # inspect one
 *   php artisan anime:cache-status --key=all-catalogues --json # full payload
 */
class AnimeCacheStatus extends Command
{
    protected $signature = 'anime:cache-status
        {--type= : Only list rows of this type}
        {--key= : Inspect the stored payload for a single cache key}
        {--limit=30 : Max rows to list}
        {--json : With --key, print the full payload as JSON}';

    protected $description = 'Inspect the anime data currently stored in the database.';

    public function handle(AnimeCacheStore $store): int
    {
        if ($key = $this->option('key')) {
            return $this->showKey($key, $store);
        }

        if ($type = $this->option('type')) {
            return $this->listType($type);
        }

        return $this->summary($store);
    }

    private function summary(AnimeCacheStore $store): int
    {
        $rows = AnimeCache::query()
            ->selectRaw('type, count(*) as rows, sum(length(payload)) as bytes, max(fetched_at) as newest')
            ->groupBy('type')
            ->orderBy('type')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No anime data stored yet. Run `php artisan anime:ingest` to fill the cache.');

            return self::SUCCESS;
        }

        $table = $rows->map(function ($r) use ($store) {
            $ttl = $store->ttlFor($r->type);
            $newest = $r->newest ? Carbon::parse($r->newest) : null;
            $fresh = ($newest && $ttl > 0) ? $newest->gt(now()->subSeconds($ttl)) : null;

            return [
                $r->type,
                $r->rows,
                $this->humanBytes((int) $r->bytes),
                $newest ? $newest->diffForHumans() : '—',
                $fresh === null ? '—' : ($fresh ? 'fresh' : 'stale'),
            ];
        })->all();

        $this->table(['Type', 'Rows', 'Size', 'Last fetched', 'State'], $table);
        $this->line('Total rows: '.AnimeCache::count());
        $this->newLine();
        $this->line('Inspect further: --type=<type> to list keys, --key=<key> to view one.');

        return self::SUCCESS;
    }

    private function listType(string $type): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $rows = AnimeCache::query()
            ->where('type', $type)
            ->selectRaw('cache_key, length(payload) as bytes, fetched_at')
            ->orderByDesc('fetched_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("No rows of type '{$type}'.");

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Size', 'Fetched'],
            $rows->map(fn ($r) => [
                Str::limit($r->cache_key, 60),
                $this->humanBytes((int) $r->bytes),
                Carbon::parse($r->fetched_at)->diffForHumans(),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function showKey(string $key, AnimeCacheStore $store): int
    {
        $row = AnimeCache::query()->where('cache_key', $key)->first();

        if (! $row) {
            $this->error("No stored data for key: {$key}");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $row->payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));

            return self::SUCCESS;
        }

        $ttl = $store->ttlFor($row->type);
        $fresh = $ttl > 0 ? $row->fetched_at->gt(now()->subSeconds($ttl)) : null;

        $this->line("Key:     {$row->cache_key}");
        $this->line("Type:    {$row->type}");
        $this->line("Fetched: {$row->fetched_at} ({$row->fetched_at->diffForHumans()})");
        $this->line('State:   '.($fresh === null ? '—' : ($fresh ? 'fresh' : 'stale')));

        $payload = $row->payload;
        if (is_array($payload)) {
            if (array_is_list($payload)) {
                $this->line('Items:   '.count($payload));
                foreach (array_slice($payload, 0, 8) as $item) {
                    $label = is_array($item)
                        ? ($item['name'] ?? $item['serie_name'] ?? json_encode($item))
                        : $item;
                    $this->line('  - '.Str::limit(trim((string) $label), 70));
                }
            } else {
                $this->line('Keys:    '.implode(', ', array_keys($payload)));
            }
        }

        $this->newLine();
        $this->line('Use --json to print the full payload.');

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, ($n < 10 && $i > 0) ? 1 : 0).' '.$units[$i];
    }
}
