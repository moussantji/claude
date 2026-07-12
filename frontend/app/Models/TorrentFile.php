<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single video file inside a downloaded torrent.
 *
 * One torrent (TorrentDownload) can contain many episodes, so each video file
 * gets its own row here — with its own upload status, matched episode and
 * resulting embed URL.
 *
 * Per-file lifecycle: pending → uploading → done / failed
 *                     skipped = no episode could be matched (needs manual map)
 */
class TorrentFile extends Model
{
    protected $table = 'torrent_files';

    protected $fillable = [
        'torrent_download_id',
        'file_name',
        'local_path',
        'file_size',
        'detected_episode',
        'episode_id',
        'language',
        'status',
        'embed_url',
        'file_code',
        'attempts',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'detected_episode' => 'integer',
            'attempts' => 'integer',
        ];
    }

    /* ---- Relationships ---- */

    public function torrent(): BelongsTo
    {
        return $this->belongsTo(TorrentDownload::class, 'torrent_download_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /* ---- Status helpers ---- */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    /* ---- Actions ---- */

    public function markUploading(): void
    {
        $this->update(['status' => 'uploading', 'error' => null]);
    }

    public function markDone(string $embedUrl, ?string $fileCode = null): void
    {
        $this->update([
            'status' => 'done',
            'embed_url' => $embedUrl,
            'file_code' => $fileCode,
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markSkipped(string $reason): void
    {
        $this->update(['status' => 'skipped', 'error' => $reason]);
    }
}
