<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "torrent" remote-upload batch submitted by an admin.
 *
 * The admin submits a magnet/.torrent (resolved via Real-Debrid into direct
 * HTTP links) or pastes direct links. Each resolved link is stored as a
 * TorrentFile and sent to the target player host, which fetches it itself
 * (server-to-server remote upload).
 *
 * Batch lifecycle: pending → resolving (Real-Debrid) → processing → done / failed
 */
class TorrentDownload extends Model
{
    protected $table = 'torrent_downloads';

    protected $fillable = [
        'season_id',
        'episode_id',
        'language',
        'target_platform',
        'torrent_url',
        'debrid_id',
        'debrid_progress',
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
            'debrid_progress' => 'integer',
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

    public function isResolving(): bool
    {
        return $this->status === 'resolving';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /** Whether this batch must go through Real-Debrid (magnet/.torrent source). */
    public function usesDebrid(): bool
    {
        return ! empty($this->torrent_url);
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
        return in_array($this->status, ['pending', 'resolving', 'processing'], true);
    }

    /* ---- Actions ---- */

    /** Move to the Real-Debrid resolution stage. */
    public function markResolving(?string $debridId = null): void
    {
        $this->update(array_filter([
            'status' => 'resolving',
            'debrid_id' => $debridId,
            'error' => null,
        ], fn ($v, $k) => $k === 'status' || $k === 'error' || $v !== null, ARRAY_FILTER_USE_BOTH));
    }

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
        // Re-resolve from scratch for magnet/.torrent batches; otherwise just
        // re-send the pasted links.
        $this->update([
            'status' => 'pending',
            'error' => null,
            'debrid_id' => null,
            'debrid_progress' => 0,
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
