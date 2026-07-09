<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A planning entry: which anime airs on which day/time.
 */
class Planning extends Model
{
    protected $table = 'planning';

    protected $fillable = [
        'anime_id',
        'day_of_week',  // 0=Lundi, 1=Mardi, ... 6=Dimanche
        'time',         // HH:MM format (nullable)
        'note',         // e.g. "Nouvel épisode chaque lundi"
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function anime(): BelongsTo
    {
        return $this->belongsTo(Anime::class);
    }
}
