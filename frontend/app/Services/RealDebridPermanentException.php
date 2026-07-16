<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when Real-Debrid returns a permanent error that should never be
 * retried (infringing content, IP blocked, bad token, etc.).
 *
 * Catching this separately from RuntimeException lets the torrent pipeline
 * mark jobs as permanently failed (high attempt count) rather than scheduling
 * futile retries.
 */
class RealDebridPermanentException extends RuntimeException
{
    public readonly string $context;
    public readonly ?int $rdErrorCode;
    public readonly int $httpStatus;

    public function __construct(string $context, string $message, ?int $rdErrorCode, int $httpStatus)
    {
        $this->context = $context;
        $this->rdErrorCode = $rdErrorCode;
        $this->httpStatus = $httpStatus;

        $suffix = $rdErrorCode !== null ? " (code {$rdErrorCode})" : '';
        parent::__construct(
            "Real-Debrid {$context} [HTTP {$httpStatus}]: {$message}{$suffix}"
        );
    }

    /** Whether this is a DMCA / copyright block (error code 35). */
    public function isInfringing(): bool
    {
        return $this->rdErrorCode === 35
            || str_contains($this->getMessage(), 'infringing')
            || $this->httpStatus === 451;
    }

    /** Whether the IP address is blocked by RD (VPS/cloud). */
    public function isIpBlocked(): bool
    {
        return $this->rdErrorCode === 22
            || str_contains($this->getMessage(), 'ip_not_allowed');
    }

    /** Whether the token is bad / account locked. */
    public function isAuthError(): bool
    {
        return in_array($this->rdErrorCode, [8, 9, 14], true);
    }
}
