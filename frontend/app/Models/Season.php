<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A season of an anime.
 */
class Season extends Model
{
    protected $table = 'seasons';

    protected $fillable = [
        'anime_id', 'url', 'name', 'position', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function anime(): BelongsTo
    {
        return $this->belongsTo(Anime::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }
}
