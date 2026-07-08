<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
