<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks transfer progress per episode link.
 * Used by episodes:daily-transfer to resume and enforce daily quotas.
 */
class TransferProgress extends Model
{
    protected $table = 'transfer_progress';

    protected $fillable = [
        'episode_link_id',
        'anime_id',
        'host',
        'status',
        'attempts',
        'new_url',
        'new_file_code',
        'error',
        'transferred_at',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'date',
            'last_attempt_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function episodeLink(): BelongsTo
    {
        return $this->belongsTo(EpisodeLink::class, 'episode_link_id');
    }

    public function anime(): BelongsTo
    {
        return $this->belongsTo(Anime::class);
    }
}
