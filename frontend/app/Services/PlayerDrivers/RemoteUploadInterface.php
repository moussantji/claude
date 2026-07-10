<?php

namespace App\Services\PlayerDrivers;

/**
 * Optional capability: server-to-server (remote) upload.
 *
 * Instead of downloading the video to our server and re-uploading it, the
 * TARGET host fetches the video directly from a source URL. This is ideal for
 * shared hosting (cPanel) since our server only makes lightweight API calls.
 *
 * Remote uploads are asynchronous: remoteUpload() queues a job and returns a
 * job id; remoteStatus() is polled later to get the final embed URL.
 */
interface RemoteUploadInterface
{
    /**
     * Queue a remote (server-side) fetch of $sourceUrl into the target account.
     *
     * @param  string  $sourceUrl   Direct video URL (or an embed URL the host can resolve).
     * @param  string  $title       Title for the video.
     * @param  array   $credentials Target account credentials.
     * @return array{job_id: string|null, embed_url: string|null, file_code: string|null, state: string}
     *         state = 'queued' | 'done' (some hosts return the file immediately).
     */
    public function remoteUpload(string $sourceUrl, string $title, array $credentials): array;

    /**
     * Check the status of a previously queued remote upload.
     *
     * @return array{state: string, embed_url: string|null, file_code: string|null, error: string|null}
     *         state = 'pending' | 'done' | 'error'
     */
    public function remoteStatus(string $jobId, array $credentials): array;
}
