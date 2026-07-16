<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Detects the video codec of a file WITHOUT requiring it to be stored locally.
 *
 * Two strategies, in order:
 *   1. Filename heuristic (zero dependency): anime releases almost always tag
 *      the codec in the name — x265 / HEVC / H265, or x264 / H264 / AVC.
 *   2. ffprobe fallback (optional): probes a local path OR a remote URL
 *      (ffprobe reads only the header, so the file need not be downloaded).
 *      Only used when ffprobe is installed on the worker machine.
 *
 * Returns a lowercased codec name ('hevc', 'h264', 'av1', ...) or null.
 */
class CodecDetector
{
    private string $ffprobe;
    private int $timeout;

    public function __construct()
    {
        $this->ffprobe = (string) env('FFPROBE_BINARY', 'ffprobe');
        $this->timeout = (int) env('FFPROBE_TIMEOUT', 30);
    }

    public function detect(?string $fileName, ?string $localPath = null, ?string $url = null): ?string
    {
        $fromName = $this->fromFilename((string) $fileName);
        if ($fromName !== null) {
            return $fromName;
        }

        // ffprobe fallback: prefer a local file, otherwise probe the remote URL.
        $input = ($localPath && is_file($localPath)) ? $localPath : ($url ?: null);
        if ($input !== null) {
            return $this->fromFfprobe($input);
        }

        return null;
    }

    /** Guess the codec from tokens commonly present in release filenames. */
    public function fromFilename(string $name): ?string
    {
        if ($name === '') {
            return null;
        }
        if (preg_match('/(x\.?265|h\.?265|hevc)/i', $name)) {
            return 'hevc';
        }
        if (preg_match('/(x\.?264|h\.?264|avc)/i', $name)) {
            return 'h264';
        }
        if (preg_match('/\bav1\b/i', $name)) {
            return 'av1';
        }

        return null;
    }

    public function isHevc(?string $codec): bool
    {
        return $codec !== null && in_array(strtolower($codec), ['hevc', 'h265', 'x265'], true);
    }

    public function isFfprobeAvailable(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = Process::timeout(10)->run([$this->ffprobe, '-version'])->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /** Probe a local path or remote URL with ffprobe (header only). */
    private function fromFfprobe(string $input): ?string
    {
        if (! $this->isFfprobeAvailable()) {
            return null;
        }
        try {
            $result = Process::timeout($this->timeout)->run([
                $this->ffprobe,
                '-v', 'error',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=codec_name',
                '-of', 'default=nw=1:nk=1',
                '-analyzeduration', '5M',
                '-probesize', '5M',
                $input,
            ]);
            if ($result->successful()) {
                $codec = strtolower(trim($result->output()));

                return $codec !== '' ? $codec : null;
            }
        } catch (Throwable) {
            // best-effort
        }

        return null;
    }
}
