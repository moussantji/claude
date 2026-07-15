<?php

namespace App\Services\PlayerDrivers;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared folder-organization logic for XFileSharing-based hosts
 * (Doodstream, Vidmoly, Mp4upload). Uses the standard endpoints:
 *   {base}/folder/create?...&parent_id=&name=      → result.fld_id
 *   {base}/folder/list?...&fld_id=                  → result.folders[]{fld_id,name}
 *   {base}/{moveEndpoint}?...&file_code=&fld_id=    (move into folder)
 *   {base}/file/rename?...&file_code=&title=        (rename)
 *
 * Each host provides its base URL + auth params (and, if needed, a different
 * "move" endpoint name).
 */
trait OrganizesViaXFileSharing
{
    /** Folder-API base URL (e.g. https://doodapi.co/api). */
    abstract protected function xfsBase(array $credentials): string;

    /** Auth query params (e.g. ['key' => '...']). */
    abstract protected function xfsAuth(array $credentials): array;

    /** Endpoint used to move a file into a folder. */
    protected function xfsMoveEndpoint(): string
    {
        return 'file/set_folder';
    }

    /**
     * Query param name for the new file title on rename.
     * Doodstream uses "title"; mp4upload/vidmoly use "name".
     */
    protected function xfsRenameParam(): string
    {
        return 'title';
    }

    public function ensureFolderPath(array $segments, array $credentials): ?string
    {
        $base = rtrim($this->xfsBase($credentials), '/');
        $auth = $this->xfsAuth($credentials);
        $parent = '0';

        foreach ($segments as $segment) {
            $name = $this->cleanFolderName($segment);
            if ($name === '') {
                continue;
            }

            $existing = $this->xfsFindFolder($base, $auth, $parent, $name);
            if ($existing !== null) {
                $parent = $existing;

                continue;
            }

            $created = $this->xfsCreateFolder($base, $auth, $parent, $name);
            if ($created === null) {
                return null; // couldn't create — abort
            }
            $parent = $created;
        }

        return $parent;
    }

    public function organizeFile(string $fileCode, ?string $folderId, ?string $title, array $credentials): bool
    {
        $base = rtrim($this->xfsBase($credentials), '/');
        $auth = $this->xfsAuth($credentials);
        $any = false;

        // Move into the folder (best-effort — some hosts have no folder API).
        if ($folderId !== null && $folderId !== '') {
            try {
                $resp = Http::timeout(20)->acceptJson()->get("{$base}/{$this->xfsMoveEndpoint()}", array_merge($auth, [
                    'file_code' => $fileCode,
                    'fld_id' => $folderId,
                ]));
                if ($resp->successful()) {
                    $any = true;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        // Do NOT rename — keep the original filename and extension as-is.

        return $any;
    }

    protected function xfsFindFolder(string $base, array $auth, string $parent, string $name): ?string
    {
        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$base}/folder/list", array_merge($auth, [
                'fld_id' => $parent,
            ]));
            $folders = $resp->json('result.folders') ?? [];
            foreach ($folders as $f) {
                if (strcasecmp((string) ($f['name'] ?? ''), $name) === 0) {
                    $id = $f['fld_id'] ?? $f['id'] ?? null;

                    return $id !== null ? (string) $id : null;
                }
            }
        } catch (Throwable) {
            // ignore
        }

        return null;
    }

    protected function xfsCreateFolder(string $base, array $auth, string $parent, string $name): ?string
    {
        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$base}/folder/create", array_merge($auth, [
                'parent_id' => $parent,
                'name' => $name,
            ]));
            $id = $resp->json('result.fld_id') ?? $resp->json('result.id');

            return $id !== null ? (string) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function cleanFolderName(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Check a file's existence via {base}/file/info?...&file_code=CODE.
     * Standard XFileSharing: result is a list of entries, status 200 = present.
     *
     * @return bool|null true = present, false = absent, null = unknown.
     */
    public function fileExists(string $fileCode, array $credentials): ?bool
    {
        $base = rtrim($this->xfsBase($credentials), '/');
        $auth = $this->xfsAuth($credentials);

        try {
            $resp = Http::timeout(20)->acceptJson()->get("{$base}/file/info", array_merge($auth, [
                'file_code' => $fileCode,
            ]));
            if (! $resp->successful()) {
                return null;
            }
            $result = $resp->json('result');
            if (! is_array($result)) {
                return null;
            }
            $any = false;
            foreach ($result as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $any = true;
                if ((int) ($entry['status'] ?? 0) === 200) {
                    return true;
                }
            }

            return $any ? false : null;
        } catch (Throwable) {
            return null;
        }
    }
}
