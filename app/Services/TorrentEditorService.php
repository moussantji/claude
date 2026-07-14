<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TorrentEditorService
{
    /**
     * Path to the torrent-edit binary (tracker/privacy operations).
     */
    protected string $binary;

    /**
     * Path to the advanced Python script (rename/metadata/hash operations).
     */
    protected string $advancedScript;

    /**
     * Python binary path.
     */
    protected string $python;

    public function __construct()
    {
        $this->binary = config('torrent-editor.binary', '/root/.pyenv/shims/torrent-edit');
        $this->advancedScript = config('torrent-editor.advanced_script', base_path('scripts/torrent_advanced.py'));
        $this->python = config('torrent-editor.python', '/root/.pyenv/shims/python3');
    }

    // ──────────────────────────────────────────────────────────────────────
    // TRACKER & PRIVACY OPERATIONS (via torrent-edit CLI)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Edit a torrent file with the given options (torrent-edit CLI).
     */
    public function edit(string $torrentPath, array $options = []): array
    {
        $command = $this->buildCommand($torrentPath, $options);
        return $this->runProcess($command);
    }

    public function makePrivate(string $torrentPath, bool $inplace = true): array
    {
        return $this->edit($torrentPath, ['private' => true, 'inplace' => $inplace]);
    }

    public function makePublic(string $torrentPath, bool $inplace = true): array
    {
        return $this->edit($torrentPath, ['public' => true, 'inplace' => $inplace]);
    }

    public function addTrackers(string $torrentPath, array $trackers): array
    {
        return $this->edit($torrentPath, ['add' => $trackers]);
    }

    public function removeTrackers(string $torrentPath, array $trackers): array
    {
        return $this->edit($torrentPath, ['remove' => $trackers]);
    }

    public function replaceTrackers(string $torrentPath, array $trackers): array
    {
        return $this->edit($torrentPath, ['replace' => $trackers]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // RENAME INTERNAL FILES (via advanced script)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Rename internal files/directories in the torrent.
     *
     * @param string $torrentPath
     * @param array $renames Associative array ['old_name' => 'new_name', ...]
     */
    public function renameFiles(string $torrentPath, array $renames): array
    {
        $command = [$this->python, $this->advancedScript, 'rename', $torrentPath];

        foreach ($renames as $old => $new) {
            $command[] = '--rename';
            $command[] = "{$old}={$new}";
        }

        return $this->runProcess($command);
    }

    // ──────────────────────────────────────────────────────────────────────
    // ALTER METADATA (via advanced script)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Alter torrent metadata (comment, created by, creation date, source).
     *
     * @param string $torrentPath
     * @param array $metadata Keys: comment, created_by, creation_date, source
     */
    public function alterMetadata(string $torrentPath, array $metadata): array
    {
        $command = [$this->python, $this->advancedScript, 'metadata', $torrentPath];

        if (array_key_exists('comment', $metadata)) {
            $command[] = '--comment';
            $command[] = $metadata['comment'];
        }

        if (array_key_exists('created_by', $metadata)) {
            $command[] = '--created-by';
            $command[] = $metadata['created_by'];
        }

        if (array_key_exists('creation_date', $metadata)) {
            $command[] = '--creation-date';
            $command[] = $metadata['creation_date'];
        }

        if (array_key_exists('source', $metadata)) {
            $command[] = '--source';
            $command[] = $metadata['source'];
        }

        return $this->runProcess($command);
    }

    // ──────────────────────────────────────────────────────────────────────
    // CHANGE HASH (via advanced script)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Randomize the info hash by injecting entropy into the info dict.
     */
    public function changeHash(string $torrentPath): array
    {
        $command = [$this->python, $this->advancedScript, 'hash', $torrentPath];
        return $this->runProcess($command);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GENERATE RANDOM TORRENT (via advanced script)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate a random .torrent file from scratch.
     *
     * @param string $outputPath Where to save the generated .torrent
     * @param array $options Optional: num_files, private, source, comment, created_by
     */
    public function generateRandom(string $outputPath, array $options = []): array
    {
        $command = [$this->python, $this->advancedScript, 'generate', $outputPath];

        if (!empty($options['num_files'])) {
            $command[] = '--num-files';
            $command[] = (string) $options['num_files'];
        }

        if (!empty($options['private'])) {
            $command[] = '--private';
        }

        if (!empty($options['source'])) {
            $command[] = '--source';
            $command[] = $options['source'];
        }

        if (!empty($options['comment'])) {
            $command[] = '--comment';
            $command[] = $options['comment'];
        }

        if (!empty($options['created_by'])) {
            $command[] = '--created-by';
            $command[] = $options['created_by'];
        }

        return $this->runProcess($command);
    }

    // ──────────────────────────────────────────────────────────────────────
    // TORRENT INFO (via advanced script)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get torrent file information (hash, name, trackers, files...).
     */
    public function getInfo(string $torrentPath): array
    {
        $command = [$this->python, $this->advancedScript, 'info', $torrentPath];
        return $this->runProcess($command);
    }

    // ──────────────────────────────────────────────────────────────────────
    // INTERNAL HELPERS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Run a process and return structured result.
     */
    protected function runProcess(array $command): array
    {
        Log::info('TorrentEditor: Executing', ['command' => implode(' ', $command)]);

        $process = new Process($command);
        $process->setTimeout(60);
        $process->setEnv([
            'PATH' => '/root/.pyenv/shims:/root/.pyenv/bin:/usr/local/bin:/usr/bin:/bin',
            'PYENV_ROOT' => '/root/.pyenv',
            'HOME' => '/root',
        ]);

        try {
            $process->run();
            $output = $process->getOutput() . $process->getErrorOutput();

            if ($process->isSuccessful()) {
                return [
                    'success' => true,
                    'output' => trim($output),
                    'backup' => $this->findBackup($command[1] ?? ($command[2] ?? '')),
                ];
            }

            Log::warning('TorrentEditor: Command failed', [
                'exit_code' => $process->getExitCode(),
                'output' => $output,
            ]);

            return [
                'success' => false,
                'output' => trim($output) ?: 'Command failed with exit code ' . $process->getExitCode(),
                'backup' => null,
            ];
        } catch (ProcessFailedException $e) {
            Log::error('TorrentEditor: Process exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'output' => $e->getMessage(),
                'backup' => null,
            ];
        }
    }

    /**
     * Build the command array for the torrent-edit CLI.
     */
    protected function buildCommand(string $torrentPath, array $options): array
    {
        $command = [$this->binary, $torrentPath];

        if (!empty($options['private'])) {
            $command[] = '--private';
        }

        if (!empty($options['public'])) {
            $command[] = '--public';
        }

        if (!empty($options['inplace'])) {
            $command[] = '--inplace';
        }

        if (!empty($options['add']) && is_array($options['add'])) {
            $command[] = '--add';
            foreach ($options['add'] as $tracker) {
                $command[] = $tracker;
            }
        }

        if (!empty($options['remove']) && is_array($options['remove'])) {
            $command[] = '--remove';
            foreach ($options['remove'] as $tracker) {
                $command[] = $tracker;
            }
        }

        if (!empty($options['replace']) && is_array($options['replace'])) {
            $command[] = '--replace';
            foreach ($options['replace'] as $tracker) {
                $command[] = $tracker;
            }
        }

        if (!empty($options['verbose'])) {
            $command[] = '-v';
        }

        return $command;
    }

    /**
     * Check if a .bak backup file was created.
     */
    protected function findBackup(string $torrentPath): ?string
    {
        $backupPath = $torrentPath . '.bak';
        return file_exists($backupPath) ? $backupPath : null;
    }
}
