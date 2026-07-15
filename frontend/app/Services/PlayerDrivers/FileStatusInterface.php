<?php

namespace App\Services\PlayerDrivers;

/**
 * Optional capability: check whether a file actually exists (and is ready) on
 * the player host, via the host's file-info endpoint.
 *
 * Used before writing a link to the DB, so we only publish links whose file
 * is really present on the player.
 */
interface FileStatusInterface
{
    /**
     * @param  string  $fileCode   The host file code.
     * @param  array   $credentials  Account credentials.
     * @return bool|null  true = present, false = absent, null = unknown/unsupported.
     */
    public function fileExists(string $fileCode, array $credentials): ?bool;
}
