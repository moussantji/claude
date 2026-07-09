<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user request for content not yet available on the platform.
 */
class ContentRequest extends Model
{
    protected $table = 'content_requests';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'status', // pending, approved, rejected
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
