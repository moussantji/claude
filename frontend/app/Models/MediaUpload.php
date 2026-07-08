<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A media file the user owns, uploaded to their own host (e.g. vidmoly),
 * with the resulting embed link stored for reuse.
 */
class MediaUpload extends Model
{
    protected $table = 'media_uploads';

    protected $fillable = [
        'path_hash',
        'source_path',
        'title',
        'provider',
        'file_code',
        'embed_url',
        'status',
        'error',
        'size_bytes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }
}
