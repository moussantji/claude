<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual team task status for one anime (download/upload tracking).
 */
class AnimeTask extends Model
{
    protected $table = 'anime_tasks';

    protected $fillable = [
        'anime_id', 'status', 'is_modified', 'is_deferred',
        'assigned_to', 'note', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_modified' => 'boolean',
            'is_deferred' => 'boolean',
        ];
    }

    public function anime(): BelongsTo
    {
        return $this->belongsTo(Anime::class);
    }
}
