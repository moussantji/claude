<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One upload of a single episode file to ONE player host.
 *
 * A torrent_file (an episode) can be pushed to SEVERAL players at once — each
 * player gets its own target row with its own status, quota, embed URL and
 * retry, so "same episode to every player" is tracked independently.
 *
 * Lifecycle: pending → (queued: host is fetching, remote upload) → done / failed
 */
class TorrentFileTarget extends Model
{
    protected $table = 'torrent_file_targets';

    protected $fillable = [
        'torrent_file_id',
        'platform',
        'source_url',
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
            'attempts' => 'integer',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(TorrentFile::class, 'torrent_file_id');
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

    /* ---- Actions ---- */

    public function markQueued(?string $remoteJobId): void
    {
        $this->update(['status' => 'queued', 'remote_job_id' => $remoteJobId, 'error' => null]);
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
}
