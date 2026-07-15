<?php

namespace App\Services\PlayerDrivers;

/**
 * Optional capability: organize already-uploaded files into folders ON the
 * player host (e.g. Anime/Saison 1/Episode 05).
 *
 * IMPORTANT: moving/renaming a file on these hosts does NOT change its
 * file_code, so existing embed links keep working. This is purely cosmetic
 * organization inside the host account.
 */
interface FolderOrganizerInterface
{
    /**
     * Ensure a nested folder path exists on the host, creating missing parts.
     *
     * @param  list<string>  $segments  Ordered folder names, e.g. ['One Piece', 'Saison 1'].
     * @param  array  $credentials  Target account credentials.
     * @return string|null  The deepest folder id, or null on failure.
     */
    public function ensureFolderPath(array $segments, array $credentials): ?string;

    /**
     * Move a file into a folder and/or rename it.
     *
     * @param  string  $fileCode   The host file code (unchanged by this op).
     * @param  string|null  $folderId  Destination folder id (null = don't move).
     * @param  string|null  $title   New title (null = don't rename).
     * @param  array  $credentials  Target account credentials.
     * @return bool  true if the move/rename succeeded (best-effort).
     */
    public function organizeFile(string $fileCode, ?string $folderId, ?string $title, array $credentials): bool;
}
