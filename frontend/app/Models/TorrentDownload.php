<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A torrent download job submitted by an admin.
 *
 * Lifecycle: pending → downloading (aria2c) → uploading (target player) → done
 *            Any step can fail → status = 'failed' with error message.
 */
class TorrentDownload extends Model
{
    protected $table = 'torrent_downloads';

    protected $fillable = [
        'torrent_url',
        'episode_id',
        'language',
        'target_platform',
        'status',
        'aria2_gid',
        'local_path',
        'embed_url',
        'file_code',
        'title',
        'file_size',
        'progress',
        'attempts',
        'error',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'progress' => 'integer',
            'attempts' => 'integer',
        ];
    }

    /* ---- Relationships ---- */

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

    public function markUploading(string $localPath, ?int $fileSize = null): void
    {
        $this->update([
            'status' => 'uploading',
            'local_path' => $localPath,
            'file_size' => $fileSize,
            'progress' => 100,
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

    public function retry(): void
    {
        $this->update([
            'status' => 'pending',
            'error' => null,
            'aria2_gid' => null,
            'progress' => 0,
        ]);
    }
}
