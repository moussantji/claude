<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A persisted anime-sama API response.
 *
 * Rows are keyed by the sha256 of a logical cache key (e.g. "episodes:<url>:VF")
 * and store the full JSON payload — including episode player/mirror links — so
 * the app can serve catalogue data straight from the database and only hit the
 * upstream anime-sama site when a row is missing or stale.
 */
class AnimeCache extends Model
{
    protected $table = 'anime_caches';

    protected $fillable = [
        'key_hash',
        'type',
        'cache_key',
        'payload',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
