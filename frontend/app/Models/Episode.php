<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single episode of a season.
 */
class Episode extends Model
{
    protected $table = 'episodes';

    protected $fillable = [
        'season_id', 'index', 'name', 'long_name', 'short_name', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'index' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(EpisodeLink::class);
    }
}
