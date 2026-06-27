<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'series_id', 'season', 'episode_number', 'title', 'stream_url', 'duration_minutes',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }
}
