<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A catalogue entry (one anime), stored in its own table.
 */
class Anime extends Model
{
    protected $table = 'animes';

    protected $fillable = [
        'url', 'slug', 'name', 'alternative_names', 'genres', 'categories',
        'languages', 'image_url', 'trailer_url', 'synopsis', 'is_mature', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'alternative_names' => 'array',
            'genres' => 'array',
            'categories' => 'array',
            'languages' => 'array',
            'is_mature' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /** All episodes across every season (used for counting). */
    public function episodes(): HasManyThrough
    {
        return $this->hasManyThrough(Episode::class, Season::class);
    }

    /** Manual team task status for this anime. */
    public function task(): HasOne
    {
        return $this->hasOne(AnimeTask::class);
    }
}
