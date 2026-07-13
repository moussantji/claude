<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "torrent" remote-upload batch submitted by an admin.
 *
 * The admin submits a magnet/.torrent or pastes direct links. Depending on the
 * provider, the torrent is fetched by our own server (aria2, free) or by
 * Real-Debrid (paid). Each resulting file is stored as a TorrentFile and made
 * live on the target player host, then attached to the episode.
 *
 * Batch lifecycle:
 *   pending → downloading (aria2) | resolving (Real-Debrid) → processing → done/failed
 */
class TorrentDownload extends Model
{
    protected $table = 'torrent_downloads';

    protected $fillable = [
        'season_id',
        'episode_id',
        'language',
        'target_platform',
        'provider',
        'torrent_url',
        'aria2_gid',
        'aria2_files',
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
            'aria2_files' => 'array',
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

    public function isResolving(): bool
    {
        return $this->status === 'resolving';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /** Whether this batch has a magnet/.torrent source to fetch. */
    public function usesTorrent(): bool
    {
        return ! empty($this->torrent_url);
    }

    /** Free self-hosted provider (aria2c downloads on our server). */
    public function usesAria2(): bool
    {
        return $this->provider === 'aria2';
    }

    /** Paid provider (Real-Debrid resolves the torrent into direct links). */
    public function usesDebrid(): bool
    {
        return $this->provider === 'realdebrid';
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
        return in_array($this->status, ['pending', 'downloading', 'resolving', 'processing'], true);
    }

    /* ---- Actions ---- */

    /** Move to the aria2 download stage (self-hosted provider). */
    public function markDownloading(?string $gid = null): void
    {
        $this->update([
            'status' => 'downloading',
            'aria2_gid' => $gid ?? $this->aria2_gid,
            'error' => null,
        ]);
    }

    /** Move to the Real-Debrid resolution stage. */
    public function markResolving(?string $debridId = null): void
    {
        $this->update([
            'status' => 'resolving',
            'debrid_id' => $debridId ?? $this->debrid_id,
            'error' => null,
        ]);
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
        // Re-fetch from scratch for magnet/.torrent batches; otherwise just
        // re-send the pasted links.
        $this->update([
            'status' => 'pending',
            'error' => null,
            'aria2_gid' => null,
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
