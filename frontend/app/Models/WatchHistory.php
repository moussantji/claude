<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchHistory extends Model
{
    /** @use HasFactory<\Database\Factories\WatchHistoryFactory> */
    use HasFactory;

    protected $table = 'watch_history';

    protected $fillable = [
        'user_id',
        'serie_name',
        'season_url',
        'season_name',
        'episode_index',
        'episode_name',
        'player_url',
        'language',
        'position_seconds',
        'image_url',
        'catalogue_url',
    ];

    protected function casts(): array
    {
        return [
            'episode_index' => 'integer',
            'position_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
