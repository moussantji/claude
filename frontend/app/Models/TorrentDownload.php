<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "torrent" remote-upload batch submitted by an admin.
 *
 * The admin provides direct download links (one per episode for a season pack).
 * Each link is stored as a TorrentFile and sent to the target player host,
 * which fetches it itself (server-to-server remote upload).
 *
 * Batch lifecycle: pending → processing → done / failed
 */
class TorrentDownload extends Model
{
    protected $table = 'torrent_downloads';

    protected $fillable = [
        'season_id',
        'episode_id',
        'language',
        'target_platform',
        'status',
        'files_total',
        'files_done',
        'files_failed',
        'title',
        'attempts',
        'error',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'files_total' => 'integer',
            'files_done' => 'integer',
            'files_failed' => 'integer',
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

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
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
        return in_array($this->status, ['pending', 'processing'], true);
    }

    /* ---- Actions ---- */

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing', 'error' => null]);
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
        $this->update(['status' => 'pending', 'error' => null]);
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
