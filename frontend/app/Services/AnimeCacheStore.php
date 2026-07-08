<?php

namespace App\Services;

use App\Models\AnimeCache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Database-backed store for anime-sama API responses.
 *
 * Goals:
 *  - Serve catalogue data (incl. episode player/"vid" links) from MySQL so the
 *    upstream anime-sama site is hit rarely instead of on every request.
 *  - Survive upstream outages: when a refresh fails but we still hold a
 *    previously stored (stale) row, serve that instead of erroring
 *    (stale-while-error).
 *  - Avoid cache stampedes: only one worker refreshes a given key at a time.
 */
class AnimeCacheStore
{
    /**
     * Whether persistence is enabled at all.
     */
    public function enabled(): bool
    {
        return (bool) config('services.anime_sama.store', true);
    }

    /**
     * Freshness window (seconds) for a given logical type.
     */
    public function ttlFor(string $type): int
    {
        $ttls = (array) config('services.anime_sama.ttl', []);

        return (int) ($ttls[$type] ?? $ttls['default'] ?? 0);
    }

    /**
     * Return the persisted payload for $key when fresh; otherwise fetch it via
     * $fetch, persist it and return it. On upstream failure, a previously
     * stored (stale) payload is served when available.
     *
     * @param  int|null  $ttl  Explicit TTL; when null, resolved from the type.
     */
    public function remember(string $type, string $key, ?int $ttl, callable $fetch): mixed
    {
        $ttl ??= $this->ttlFor($type);

        // Persistence off or non-positive TTL: pass straight through.
        if (! $this->enabled() || $ttl <= 0) {
            return $fetch();
        }

        $existing = $this->find($key);
        if ($existing && $this->isFresh($existing, $ttl)) {
            return $existing->payload;
        }

        $lockKey = 'anime-cache-lock:'.$this->hash($key);

        try {
            // Wait up to 20s for the refresh lock (held max 30s to outlast the
            // backend HTTP timeout). This collapses concurrent misses into one
            // upstream call.
            return Cache::lock($lockKey, 30)->block(20, function () use ($type, $key, $ttl, $fetch) {
                // Another worker may have refreshed the row while we waited.
                $row = $this->find($key);
                if ($row && $this->isFresh($row, $ttl)) {
                    return $row->payload;
                }

                return $this->fetchAndStore($type, $key, $fetch, $row);
            });
        } catch (LockTimeoutException) {
            // Couldn't acquire the lock in time: prefer any stored data (even
            // stale) over piling more load onto the upstream site.
            $row = $this->find($key);
            if ($row) {
                return $row->payload;
            }

            return $this->fetchAndStore($type, $key, $fetch, null);
        }
    }

    /**
     * Force a fresh fetch and persist it (used by the warm-up command).
     */
    public function refresh(string $type, string $key, callable $fetch): mixed
    {
        return $this->fetchAndStore($type, $key, $fetch, $this->find($key));
    }

    /**
     * Delete rows last fetched more than $days ago. Returns the count removed.
     */
    public function prune(int $days): int
    {
        return AnimeCache::query()
            ->where('fetched_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Return the stored payload for a key regardless of age, or null when the
     * key has never been persisted. Used for curated snapshots (e.g. the full
     * catalogue) that are refreshed by the ingest command / scheduler.
     */
    public function get(string $key): mixed
    {
        return $this->find($key)?->payload;
    }

    protected function find(string $key): ?AnimeCache
    {
        return AnimeCache::query()->where('key_hash', $this->hash($key))->first();
    }

    protected function fetchAndStore(string $type, string $key, callable $fetch, ?AnimeCache $existing): mixed
    {
        try {
            $data = $fetch();
        } catch (Throwable $e) {
            if ($existing) {
                Log::warning('anime cache: serving stale data after upstream failure', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);

                return $existing->payload;
            }

            throw $e;
        }

        AnimeCache::query()->updateOrCreate(
            ['key_hash' => $this->hash($key)],
            [
                'type' => $type,
                'cache_key' => $key,
                'payload' => $data,
                'fetched_at' => now(),
            ],
        );

        return $data;
    }

    protected function isFresh(AnimeCache $row, int $ttl): bool
    {
        return $row->fetched_at !== null
            && $row->fetched_at->gt(now()->subSeconds($ttl));
    }

    protected function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
