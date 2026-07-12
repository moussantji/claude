<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A torrent download job submitted by an admin.
 *
 * A torrent can be a SEASON PACK with many episodes. The downloaded video
 * files are tracked individually in `torrent_files` (one row per episode).
 *
 * Lifecycle:
 *   pending → downloading (aria2c) → uploading (per-file) → done
 *   Any step can fail → status = 'failed' with an error message.
 */
class TorrentDownload extends Model
{
    protected $table = 'torrent_downloads';

    protected $fillable = [
        'torrent_url',
        'season_id',
        'episode_id',
        'language',
        'target_platform',
        'status',
        'aria2_gid',
        'progress',
        'files_total',
        'files_done',
        'files_failed',
        'title',
        'total_size',
        'attempts',
        'error',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'files_total' => 'integer',
            'files_done' => 'integer',
            'files_failed' => 'integer',
            'total_size' => 'integer',
            'attempts' => 'integer',
        ];
    }

    /* ---- Relationships ---- */

    public function files(): HasMany
    {
        return $this->hasMany(TorrentFile::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /* ---- Status helpers ---- */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDownloading(): bool
    {
        return $this->status === 'downloading';
    }

    public function isUploading(): bool
    {
        return $this->status === 'uploading';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessable(): bool
    {
        return in_array($this->status, ['pending', 'downloading', 'uploading'], true);
    }

    /* ---- Actions ---- */

    public function markDownloading(string $gid): void
    {
        $this->update([
            'status' => 'downloading',
            'aria2_gid' => $gid,
            'error' => null,
        ]);
    }

    /**
     * Move to the uploading stage once files have been scanned/created.
     */
    public function markUploading(): void
    {
        $this->update([
            'status' => 'uploading',
            'progress' => 100,
            'error' => null,
        ]);
    }

    public function markDone(): void
    {
        $this->update(['status' => 'done', 'error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function retry(): void
    {
        $this->update([
            'status' => 'pending',
            'error' => null,
            'aria2_gid' => null,
            'progress' => 0,
        ]);
    }

    /**
     * Recompute file counters from the related torrent_files rows.
     */
    public function refreshCounters(): void
    {
        $this->update([
            'files_total' => $this->files()->count(),
            'files_done' => $this->files()->where('status', 'done')->count(),
            'files_failed' => $this->files()->where('status', 'failed')->count(),
        ]);
    }
}
