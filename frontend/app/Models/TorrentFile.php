<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single download link inside a "torrent" batch → one episode.
 *
 * The link (source_url) is sent to the target player host which fetches it
 * itself (remote upload). We never download it on our server.
 *
 * Lifecycle: pending → queued (host is fetching) → done / failed
 *            skipped = no episode matched yet (needs manual mapping)
 */
class TorrentFile extends Model
{
    protected $table = 'torrent_files';

    protected $fillable = [
        'torrent_download_id',
        'source_url',
        'file_name',
        'detected_episode',
        'episode_id',
        'language',
        'status',
        'remote_job_id',
        'embed_url',
        'file_code',
        'attempts',
        'error',
    ];

    protected function casts(): array
    {
        return [
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

    public function isQueued(): bool
    {
        return $this->status === 'queued';
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

    /** Host accepted the link and is fetching it (async). */
    public function markQueued(?string $remoteJobId): void
    {
        $this->update([
            'status' => 'queued',
            'remote_job_id' => $remoteJobId,
            'error' => null,
        ]);
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
