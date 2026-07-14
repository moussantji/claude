<?php

namespace App\Services;

use SandFox\Bencode\Bencode;
use SandFox\Bencode\Bencode\Collection;
use Illuminate\Support\Str;

class TorrentEditorService
{
    // ─────────────────────────────────────────────────────────────────────
    // RANDOM DATA POOLS
    // ─────────────────────────────────────────────────────────────────────

    private const ADJECTIVES = ['Epic', 'Dark', 'Lost', 'Final', 'Hidden', 'Secret', 'Wild', 'Neon', 'Cosmic', 'Iron',
        'Golden', 'Silent', 'Frozen', 'Blazing', 'Savage', 'Phantom', 'Eternal', 'Wicked'];

    private const NOUNS = ['Phoenix', 'Shadow', 'Storm', 'Horizon', 'Legacy', 'Empire', 'Cipher', 'Vortex', 'Titan',
        'Ronin', 'Serpent', 'Eclipse', 'Nebula', 'Raven', 'Inferno', 'Abyss', 'Cascade'];

    private const CLIENTS = ['qBittorrent 4.6.3', 'Transmission 4.0.5', 'Deluge 2.1.1', 'rTorrent 0.9.8',
        'libtorrent 2.0.9', 'uTorrent 3.6.0', 'Vuze 5.7.7.0', 'BiglyBT 3.5'];

    private const TRACKERS = [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://open.tracker.cl:1337/announce',
        'udp://tracker.openbittorrent.com:6969/announce',
        'udp://explodie.org:6969/announce',
        'udp://exodus.desync.com:6969/announce',
        'https://tracker.torrent.eu.org:443/announce',
        'udp://tracker.torrent.eu.org:451/announce',
        'udp://tracker.moeking.me:6969/announce',
        'udp://tracker.dler.org:6969/announce',
        'udp://open.stealth.si:80/announce',
    ];

    private const SOURCES = ['TorrentLeech', 'IPTorrents', 'TorrentDay', 'FileList', 'HDBits',
        'PassThePopcorn', 'GazelleGames', 'Orpheus', 'RED', 'BHD', 'Blutopia'];

    private const QUALITIES = ['720p', '1080p', '2160p', '4K', 'REMUX', 'WEB-DL', 'BluRay', 'HDRip', 'DVDRip'];

    private const GROUPS = ['SPARKS', 'FGT', 'YIFY', 'RARBG', 'EVO', 'FLUX', 'NTb', 'EDITH', 'NOGRP', 'ION10'];

    // ─────────────────────────────────────────────────────────────────────
    // CORE: LOAD / SAVE / HASH
    // ─────────────────────────────────────────────────────────────────────

    public function loadTorrent(string $path): array
    {
        $raw = file_get_contents($path);
        return Bencode::decode($raw, dictType: Collection::ARRAY, listType: Collection::ARRAY);
    }

    public function saveTorrent(array $data, string $path, bool $backup = true): void
    {
        if ($backup && file_exists($path)) {
            copy($path, $path . '.bak');
        }
        file_put_contents($path, Bencode::encode($data));
    }

    public function getInfoHash(array $data): string
    {
        $info = $data['info'] ?? [];
        return sha1(Bencode::encode($info));
    }

    // ─────────────────────────────────────────────────────────────────────
    // TRACKER OPERATIONS
    // ─────────────────────────────────────────────────────────────────────

    public function addTrackers(string $torrentPath, array $trackers): array
    {
        $data = $this->loadTorrent($torrentPath);

        // Add to announce-list
        $announceList = $data['announce-list'] ?? [];
        foreach ($trackers as $url) {
            $announceList[] = [$url];
        }
        $data['announce-list'] = $announceList;

        // Set first as announce if not set
        if (empty($data['announce'])) {
            $data['announce'] = $trackers[0];
        }

        $this->saveTorrent($data, $torrentPath);
        return ['success' => true, 'output' => 'Added ' . count($trackers) . ' tracker(s). Hash: ' . $this->getInfoHash($data)];
    }

    public function removeTrackers(string $torrentPath, array $trackers): array
    {
        $data = $this->loadTorrent($torrentPath);

        // Remove from announce
        if (isset($data['announce']) && in_array($data['announce'], $trackers)) {
            unset($data['announce']);
        }

        // Remove from announce-list
        if (isset($data['announce-list'])) {
            $data['announce-list'] = array_values(array_filter($data['announce-list'], function ($tier) use ($trackers) {
                return !in_array($tier[0] ?? '', $trackers);
            }));
            if (empty($data['announce-list'])) {
                unset($data['announce-list']);
            }
        }

        $this->saveTorrent($data, $torrentPath);
        return ['success' => true, 'output' => 'Removed ' . count($trackers) . ' tracker(s). Hash: ' . $this->getInfoHash($data)];
    }

    public function replaceTrackers(string $torrentPath, array $trackers): array
    {
        $data = $this->loadTorrent($torrentPath);

        $data['announce'] = $trackers[0];
        $data['announce-list'] = array_map(fn($url) => [$url], $trackers);

        $this->saveTorrent($data, $torrentPath);
        return ['success' => true, 'output' => 'Replaced with ' . count($trackers) . ' tracker(s). Hash: ' . $this->getInfoHash($data)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVACY FLAG
    // ─────────────────────────────────────────────────────────────────────

    public function makePrivate(string $torrentPath): array
    {
        $data = $this->loadTorrent($torrentPath);
        $data['info']['private'] = 1;
        $this->saveTorrent($data, $torrentPath);
        return ['success' => true, 'output' => 'Set private flag. New hash: ' . $this->getInfoHash($data)];
    }

    public function makePublic(string $torrentPath): array
    {
        $data = $this->loadTorrent($torrentPath);
        unset($data['info']['private']);
        $this->saveTorrent($data, $torrentPath);
        return ['success' => true, 'output' => 'Removed private flag. New hash: ' . $this->getInfoHash($data)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // RENAME INTERNAL FILES
    // ─────────────────────────────────────────────────────────────────────

    public function renameFiles(string $torrentPath, array $renames): array
    {
        $data = $this->loadTorrent($torrentPath);
        $info = &$data['info'];
        $output = [];

        // Rename torrent name
        $currentName = $info['name'] ?? '';
        if (isset($renames[$currentName])) {
            $info['name'] = $renames[$currentName];
            $output[] = "Renamed torrent: {$currentName} -> {$renames[$currentName]}";
        }

        // Rename individual files
        if (isset($info['files'])) {
            foreach ($info['files'] as &$file) {
                $fullPath = implode('/', $file['path']);
                if (isset($renames[$fullPath])) {
                    $file['path'] = explode('/', $renames[$fullPath]);
                    $output[] = "Renamed: {$fullPath} -> {$renames[$fullPath]}";
                } else {
                    // Check just filename
                    $filename = end($file['path']);
                    if (isset($renames[$filename])) {
                        $file['path'][array_key_last($file['path'])] = $renames[$filename];
                        $output[] = "Renamed: {$filename} -> {$renames[$filename]}";
                    }
                }
            }
        }

        $this->saveTorrent($data, $torrentPath);
        $output[] = "New hash: " . $this->getInfoHash($data);
        return ['success' => true, 'output' => implode("\n", $output)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // ALTER METADATA
    // ─────────────────────────────────────────────────────────────────────

    public function alterMetadata(string $torrentPath, array $metadata): array
    {
        $data = $this->loadTorrent($torrentPath);
        $output = [];

        if (array_key_exists('comment', $metadata)) {
            if ($metadata['comment'] === '') {
                unset($data['comment']);
                $output[] = "Removed comment";
            } else {
                $data['comment'] = $metadata['comment'];
                $output[] = "Set comment: {$metadata['comment']}";
            }
        }

        if (array_key_exists('created_by', $metadata)) {
            if ($metadata['created_by'] === '') {
                unset($data['created by']);
                $output[] = "Removed 'created by'";
            } else {
                $data['created by'] = $metadata['created_by'];
                $output[] = "Set created by: {$metadata['created_by']}";
            }
        }

        if (array_key_exists('creation_date', $metadata)) {
            if ($metadata['creation_date'] === '0') {
                unset($data['creation date']);
                $output[] = "Removed creation date";
            } elseif ($metadata['creation_date'] === 'now') {
                $data['creation date'] = time();
                $output[] = "Set creation date to now: " . time();
            } else {
                $data['creation date'] = (int) $metadata['creation_date'];
                $output[] = "Set creation date: {$metadata['creation_date']}";
            }
        }

        if (array_key_exists('source', $metadata)) {
            if ($metadata['source'] === '') {
                unset($data['info']['source']);
                $output[] = "Removed source";
            } else {
                $data['info']['source'] = $metadata['source'];
                $output[] = "Set source: {$metadata['source']}";
            }
        }

        $this->saveTorrent($data, $torrentPath);
        $output[] = "Hash: " . $this->getInfoHash($data);
        return ['success' => true, 'output' => implode("\n", $output)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // CHANGE HASH
    // ─────────────────────────────────────────────────────────────────────

    public function changeHash(string $torrentPath): array
    {
        $data = $this->loadTorrent($torrentPath);
        $oldHash = $this->getInfoHash($data);

        // Inject random entropy to change the hash
        $data['info']['entropy'] = Str::random(32);

        $this->saveTorrent($data, $torrentPath);
        $newHash = $this->getInfoHash($data);

        return ['success' => true, 'output' => "Old hash: {$oldHash}\nNew hash: {$newHash}\nHash changed: " . ($oldHash !== $newHash ? 'true' : 'false')];
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET INFO
    // ─────────────────────────────────────────────────────────────────────

    public function getInfo(string $torrentPath): array
    {
        $data = $this->loadTorrent($torrentPath);
        $info = $data['info'] ?? [];
        $output = [];

        $output[] = "Info hash: " . $this->getInfoHash($data);
        $output[] = "Name: " . ($info['name'] ?? 'N/A');

        if (isset($data['announce'])) $output[] = "Announce: " . $data['announce'];
        if (isset($data['comment'])) $output[] = "Comment: " . $data['comment'];
        if (isset($data['created by'])) $output[] = "Created by: " . $data['created by'];
        if (isset($data['creation date'])) $output[] = "Creation date: " . $data['creation date'];
        if (isset($info['source'])) $output[] = "Source: " . $info['source'];
        if (isset($info['private'])) $output[] = "Private: " . $info['private'];

        if (isset($info['files'])) {
            $output[] = "\nFiles (" . count($info['files']) . "):";
            foreach ($info['files'] as $f) {
                $path = implode('/', $f['path']);
                $output[] = "  {$path} ({$f['length']} bytes)";
            }
        } else {
            $output[] = "\nSingle file: " . ($info['name'] ?? '') . " (" . ($info['length'] ?? 0) . " bytes)";
        }

        return ['success' => true, 'output' => implode("\n", $output)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // GENERATE RANDOM TORRENT
    // ─────────────────────────────────────────────────────────────────────

    public function generateRandom(string $outputPath, array $options = []): array
    {
        $torrentName = $this->randomReleaseName();
        $numFiles = $options['num_files'] ?? rand(1, 8);
        $pieceLength = [256 * 1024, 512 * 1024, 1024 * 1024, 2 * 1024 * 1024][rand(0, 3)];

        $extensions = ['.mkv', '.mp4', '.avi', '.iso'];
        $subExtensions = ['.srt', '.ass', '.nfo', '.txt', '.jpg', '.png'];

        $files = [];
        $totalLength = 0;

        // Main file
        $mainExt = $extensions[rand(0, count($extensions) - 1)];
        $mainSize = rand(500_000_000, 15_000_000_000);
        $files[] = ['length' => $mainSize, 'path' => ["{$torrentName}{$mainExt}"]];
        $totalLength += $mainSize;

        // Additional files
        for ($i = 0; $i < $numFiles - 1; $i++) {
            $subExt = $subExtensions[rand(0, count($subExtensions) - 1)];
            $subSize = rand(100, 5_000_000);
            if (rand(0, 1)) {
                $subfolder = ['Subs', 'Sample', 'Extras', 'Covers', 'Docs'][rand(0, 4)];
                $files[] = ['length' => $subSize, 'path' => [$subfolder, "{$torrentName}{$subExt}"]];
            } else {
                $files[] = ['length' => $subSize, 'path' => ["{$torrentName}{$subExt}"]];
            }
            $totalLength += $subSize;
        }

        $numPieces = (int) ceil($totalLength / $pieceLength);
        $pieces = random_bytes($numPieces * 20);

        $info = [
            'name' => $torrentName,
            'piece length' => $pieceLength,
            'pieces' => $pieces,
            'files' => $files,
        ];

        if (($options['private'] ?? false) || rand(0, 10) > 6) {
            $info['private'] = 1;
        }
        if (!empty($options['source'])) {
            $info['source'] = $options['source'];
        } elseif (rand(0, 1)) {
            $info['source'] = self::SOURCES[rand(0, count(self::SOURCES) - 1)];
        }

        $numTrackers = rand(1, 5);
        $selectedTrackers = (array) array_rand(array_flip(self::TRACKERS), $numTrackers);

        $torrentData = [
            'announce' => $selectedTrackers[0],
            'announce-list' => array_map(fn($t) => [$t], $selectedTrackers),
            'comment' => $options['comment'] ?? "Generated - {$torrentName}",
            'created by' => $options['created_by'] ?? self::CLIENTS[rand(0, count(self::CLIENTS) - 1)],
            'creation date' => time(),
            'info' => $info,
        ];

        file_put_contents($outputPath, Bencode::encode($torrentData));

        $hash = sha1(Bencode::encode($info));
        $output = "Generated: {$outputPath}\nName: {$torrentName}\nInfo hash: {$hash}\nFiles: " . count($files) .
            "\nTotal size: {$totalLength} bytes (" . round($totalLength / (1024 ** 3), 2) . " GB)" .
            "\nPrivate: " . (isset($info['private']) ? 'yes' : 'no') .
            "\nCreated by: " . $torrentData['created by'];

        return ['success' => true, 'output' => $output];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SPLIT TORRENT (with auto-randomization)
    // ─────────────────────────────────────────────────────────────────────

    public function splitTorrent(string $torrentPath, string $outputDir, float $maxSizeGb = 1.0, bool $randomize = true): array
    {
        $data = $this->loadTorrent($torrentPath);
        $info = $data['info'] ?? [];

        $torrentName = $info['name'] ?? 'unknown';
        $pieceLength = $info['piece length'] ?? 524288;
        $allPieces = $info['pieces'] ?? '';
        $maxSize = $maxSizeGb * 1024 * 1024 * 1024;

        // Single-file check
        if (!isset($info['files'])) {
            $fileSize = $info['length'] ?? 0;
            if ($fileSize <= $maxSize) {
                return ['success' => true, 'output' => "Single file ({$fileSize} bytes) already under limit."];
            }
            return ['success' => false, 'output' => "Cannot split a single-file torrent."];
        }

        // Build file info with offsets
        $fileInfos = [];
        $currentOffset = 0;
        foreach ($info['files'] as $file) {
            $path = implode('/', $file['path']);
            $size = $file['length'];
            $fileInfos[] = [
                'entry' => $file,
                'path' => $path,
                'size' => $size,
                'offset' => $currentOffset,
            ];
            $currentOffset += $size;
        }
        $totalSize = $currentOffset;

        // Group files into buckets
        $buckets = [];
        $currentBucket = [];
        $currentBucketSize = 0;

        foreach ($fileInfos as $fi) {
            if ($fi['size'] > $maxSize) {
                if ($currentBucket) {
                    $buckets[] = $currentBucket;
                    $currentBucket = [];
                    $currentBucketSize = 0;
                }
                $buckets[] = [$fi];
            } elseif ($currentBucketSize + $fi['size'] > $maxSize) {
                $buckets[] = $currentBucket;
                $currentBucket = [$fi];
                $currentBucketSize = $fi['size'];
            } else {
                $currentBucket[] = $fi;
                $currentBucketSize += $fi['size'];
            }
        }
        if ($currentBucket) {
            $buckets[] = $currentBucket;
        }

        if (count($buckets) <= 1) {
            return ['success' => true, 'output' => "All files fit within {$maxSizeGb} GB. No split needed."];
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $output = [];
        $output[] = "Original: {$torrentName}";
        $output[] = "Total size: " . round($totalSize / (1024 ** 3), 2) . " GB";
        $output[] = "Total files: " . count($fileInfos);
        $output[] = "Splitting into " . count($buckets) . " parts (max {$maxSizeGb} GB each)";
        if ($randomize) {
            $output[] = "Randomization: ENABLED";
        }
        $output[] = "Output: {$outputDir}";
        $output[] = "";

        $generatedFiles = [];

        foreach ($buckets as $i => $bucket) {
            $partNum = $i + 1;
            $bucketSize = array_sum(array_column($bucket, 'size'));

            $partName = $randomize ? $this->randomReleaseName() : "{$torrentName}.part{$partNum}";

            // Extract pieces for this bucket.
            // NOTE: a standalone torrent needs exactly ceil(size / pieceLength) piece
            // hashes. We take that many starting from the bucket's first piece.
            $bucketStart = $bucket[0]['offset'];
            $firstPiece = (int) floor($bucketStart / $pieceLength);
            $numPiecesNeeded = (int) ceil($bucketSize / $pieceLength);
            $extractedPieces = substr($allPieces, $firstPiece * 20, $numPiecesNeeded * 20);

            // Safety: if we ran past the end of the pieces string, pad with the last available
            if (strlen($extractedPieces) < $numPiecesNeeded * 20) {
                $extractedPieces = str_pad($extractedPieces, $numPiecesNeeded * 20, "\0");
            }

            // Build file list
            $newFiles = [];
            foreach ($bucket as $fi) {
                if ($randomize) {
                    $ext = '.' . pathinfo($fi['path'], PATHINFO_EXTENSION);
                    $newFilename = $this->randomReleaseName() . $ext;
                    $newPath = count($fi['entry']['path']) > 1
                        ? [['Season' . rand(1, 8), 'Extras', 'Subs', 'Bonus', 'Specials'][rand(0, 4)], $newFilename]
                        : [$newFilename];
                    $newFiles[] = ['length' => $fi['entry']['length'], 'path' => $newPath];
                } else {
                    $newFiles[] = $fi['entry'];
                }
            }

            // Build info dict
            $newInfo = [
                'files' => $newFiles,
                'name' => $partName,
                'piece length' => $pieceLength,
                'pieces' => $extractedPieces,
            ];

            if ($randomize) {
                $newInfo['entropy'] = Str::random(32);
                $newInfo['source'] = self::SOURCES[rand(0, count(self::SOURCES) - 1)];
                if (rand(0, 10) > 4) {
                    $newInfo['private'] = 1;
                }
            } else {
                if (isset($info['source'])) $newInfo['source'] = $info['source'];
                if (isset($info['private'])) $newInfo['private'] = $info['private'];
            }

            // Build torrent dict
            $newTorrent = ['info' => $newInfo];

            if ($randomize) {
                $numTrackers = rand(1, 4);
                $selected = array_slice(self::TRACKERS, 0, $numTrackers);
                shuffle($selected);
                $newTorrent['announce'] = $selected[0];
                $newTorrent['announce-list'] = array_map(fn($t) => [$t], $selected);
                $newTorrent['comment'] = $this->randomReleaseName() . ' - uploaded';
                $newTorrent['created by'] = self::CLIENTS[rand(0, count(self::CLIENTS) - 1)];
                $newTorrent['creation date'] = time() - rand(0, 86400 * 60);
            } else {
                if (isset($data['announce'])) $newTorrent['announce'] = $data['announce'];
                if (isset($data['announce-list'])) $newTorrent['announce-list'] = $data['announce-list'];
                if (isset($data['comment'])) $newTorrent['comment'] = $data['comment'];
                if (isset($data['created by'])) $newTorrent['created by'] = $data['created by'];
                $newTorrent['creation date'] = time();
            }

            // Save
            $outputFilename = "{$partName}.torrent";
            $outputPath = "{$outputDir}/{$outputFilename}";
            file_put_contents($outputPath, Bencode::encode($newTorrent));

            $newHash = sha1(Bencode::encode($newInfo));

            $output[] = "  Part {$partNum}: {$outputFilename}";
            $output[] = "    Hash: {$newHash}";
            $output[] = "    Name: {$partName}";
            $output[] = "    Size: " . round($bucketSize / (1024 ** 3), 2) . " GB";
            $output[] = "    Files (" . count($bucket) . "):";
            foreach ($bucket as $idx => $fi) {
                if ($randomize) {
                    $newPath = implode('/', $newFiles[$idx]['path']);
                    $output[] = "      {$fi['path']}  ->  {$newPath}";
                } else {
                    $output[] = "      - {$fi['path']} (" . round($fi['size'] / (1024 ** 2), 1) . " MB)";
                }
            }
            $output[] = "";

            $generatedFiles[] = $outputPath;
        }

        $output[] = "Done! " . count($generatedFiles) . " torrents generated.";
        return ['success' => true, 'output' => implode("\n", $output)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // MAGNET LINK RESOLUTION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve a magnet link to a .torrent file using Python libtorrent.
     *
     * @param string $magnetUri The magnet link
     * @param string $outputPath Where to save the resolved .torrent
     * @param int $timeout Max seconds to wait (default 120)
     */
    public function resolveMagnet(string $magnetUri, string $outputPath, int $timeout = 120): array
    {
        $script = base_path('scripts/magnet_to_torrent.py');
        $python = config('torrent-editor.python', 'python3');

        $process = new \Symfony\Component\Process\Process([
            $python, $script, $magnetUri, $outputPath, (string) $timeout
        ]);
        $process->setTimeout($timeout + 30);

        $process->run();
        $output = trim($process->getOutput() . $process->getErrorOutput());

        if ($process->isSuccessful() && file_exists($outputPath)) {
            return ['success' => true, 'output' => $output];
        }

        return ['success' => false, 'output' => $output ?: 'Failed to resolve magnet link (timeout or no peers found)'];
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function randomReleaseName(): string
    {
        $adj = self::ADJECTIVES[rand(0, count(self::ADJECTIVES) - 1)];
        $noun = self::NOUNS[rand(0, count(self::NOUNS) - 1)];
        $year = rand(2019, 2026);
        $quality = self::QUALITIES[rand(0, count(self::QUALITIES) - 1)];
        $group = self::GROUPS[rand(0, count(self::GROUPS) - 1)];
        return "{$adj}.{$noun}.{$year}.{$quality}-{$group}";
    }
}
