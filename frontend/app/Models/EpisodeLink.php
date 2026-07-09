<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single video/player mirror link for an episode, in its own table.
 */
class EpisodeLink extends Model
{
    protected $table = 'episode_links';

    protected $fillable = [
        'episode_id', 'language', 'position', 'url', 'host',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
