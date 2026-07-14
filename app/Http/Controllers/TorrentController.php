<?php

namespace App\Http\Controllers;

use App\Services\TorrentEditorService;
use Illuminate\Http\Request;

class TorrentController extends Controller
{
    public function __construct(
        protected TorrentEditorService $editor
    ) {}

    /**
     * Admin dashboard — hub for all tools.
     */
    public function dashboard()
    {
        return view('torrent.dashboard');
    }

    /**
     * Show the torrent editor form.
     */
    public function index()
    {
        return view('torrent.index');
    }

    /**
     * Handle torrent upload and editing.
     */
    public function edit(Request $request)
    {
        $request->validate([
            'torrent' => 'required|file|max:10240',
            'action' => 'required|in:add_trackers,remove_trackers,replace_trackers,make_private,make_public,rename_files,alter_metadata,change_hash',
            'trackers' => 'nullable|string',
            'renames' => 'nullable|string',
            'comment' => 'nullable|string|max:500',
            'created_by' => 'nullable|string|max:200',
            'creation_date' => 'nullable|string|max:20',
            'source' => 'nullable|string|max:200',
        ]);

        $uploadedFile = $request->file('torrent');
        $originalName = $uploadedFile->getClientOriginalName();
        $storagePath = config('torrent-editor.upload_path');

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filePath = $storagePath . '/' . uniqid('torrent_') . '_' . $originalName;
        $uploadedFile->move($storagePath, basename($filePath));

        $result = match ($request->input('action')) {
            'add_trackers' => $this->handleTrackers($filePath, $request, 'add'),
            'remove_trackers' => $this->handleTrackers($filePath, $request, 'remove'),
            'replace_trackers' => $this->handleTrackers($filePath, $request, 'replace'),
            'make_private' => $this->editor->makePrivate($filePath),
            'make_public' => $this->editor->makePublic($filePath),
            'rename_files' => $this->handleRename($filePath, $request),
            'alter_metadata' => $this->handleMetadata($filePath, $request),
            'change_hash' => $this->editor->changeHash($filePath),
        };

        if ($result['success']) {
            session(['edited_torrent' => $filePath, 'edited_torrent_name' => $originalName]);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'],
                'filename' => $originalName,
            ]);
        }

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return back()->withErrors(['torrent' => 'Editing failed: ' . $result['output']])->withInput();
    }

    /**
     * Show the result page.
     */
    public function result()
    {
        if (!session('edited_torrent') && !session('split_results')) {
            return redirect()->route('dashboard');
        }

        return view('torrent.result', [
            'output' => session('output', ''),
            'filename' => session('edited_torrent_name', 'edited.torrent'),
            'splitResults' => session('split_results'),
        ]);
    }

    /**
     * Download the edited torrent file (or zip).
     */
    public function download(Request $request)
    {
        $type = $request->query('type', 'edited'); // 'edited', 'global', 'split'

        if ($type === 'global' && session('global_torrent')) {
            $filePath = session('global_torrent');
            $name = session('global_torrent_name', 'global_edited.torrent');
        } elseif ($type === 'split' && session('split_zip')) {
            $filePath = session('split_zip');
            $name = session('split_zip_name', 'split.zip');
        } else {
            $filePath = session('edited_torrent');
            $name = session('edited_torrent_name', 'edited.torrent');
        }

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->route('dashboard')
                ->withErrors(['torrent' => 'Fichier non disponible pour le téléchargement.']);
        }

        return response()->download($filePath, $name)->deleteFileAfterSend(true);
    }

    /**
     * Show the generate form.
     */
    public function generateForm()
    {
        return view('torrent.generate');
    }

    /**
     * Generate a random torrent and offer download.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'num_files' => 'nullable|integer|min:1|max:50',
            'private' => 'nullable|boolean',
            'source' => 'nullable|string|max:200',
            'comment' => 'nullable|string|max:500',
            'created_by' => 'nullable|string|max:200',
        ]);

        $storagePath = config('torrent-editor.upload_path');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filename = 'generated_' . uniqid() . '.torrent';
        $outputPath = $storagePath . '/' . $filename;

        $options = array_filter([
            'num_files' => $request->input('num_files'),
            'private' => $request->boolean('private'),
            'source' => $request->input('source'),
            'comment' => $request->input('comment'),
            'created_by' => $request->input('created_by'),
        ]);

        $result = $this->editor->generateRandom($outputPath, $options);

        if ($result['success']) {
            session(['edited_torrent' => $outputPath, 'edited_torrent_name' => $filename]);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'],
                'filename' => $filename,
            ]);
        }

        return back()->withErrors(['generate' => 'Generation failed: ' . $result['output']])->withInput();
    }

    /**
     * Show the split form.
     */
    public function splitForm()
    {
        return view('torrent.split');
    }

    /**
     * Handle torrent split.
     * - Always auto-randomizes metadata on the GLOBAL file
     * - Splits into parts with randomized metadata
     * - Offers both the global edited file AND the split zip
     */
    public function split(Request $request)
    {
        $request->validate([
            'torrent' => 'required|file|max:10240',
            'max_size' => 'nullable|numeric|min:0.1|max:100',
        ]);

        $uploadedFile = $request->file('torrent');
        $originalFullName = $uploadedFile->getClientOriginalName();
        $originalName = pathinfo($originalFullName, PATHINFO_FILENAME);
        $storagePath = config('torrent-editor.upload_path');

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Save the upload
        $filePath = $storagePath . '/' . uniqid('torrent_') . '_' . $originalFullName;
        $uploadedFile->move($storagePath, basename($filePath));

        // ── STEP 1: Auto-randomize the GLOBAL torrent file ──
        // Copy to a "global" file that we'll randomize
        $globalPath = $storagePath . '/' . uniqid('global_') . '_' . $originalFullName;
        copy($filePath, $globalPath);

        // Randomize metadata on the global copy
        $this->editor->alterMetadata($globalPath, [
            'comment' => '',
            'created_by' => '',
            'creation_date' => 'now',
            'source' => '',
        ]);
        // Rename + change hash on global
        $this->editor->changeHash($globalPath);

        // ── STEP 2: Split into parts (always randomized) ──
        $outputDir = $storagePath . '/split_' . uniqid();
        mkdir($outputDir, 0755, true);

        $maxSize = $request->input('max_size', 1.0);

        $result = $this->editor->splitTorrent($filePath, $outputDir, (float) $maxSize, true);

        if ($result['success']) {
            $splitFiles = glob($outputDir . '/*.torrent');

            // Store session data for both downloads
            session(['global_torrent' => $globalPath, 'global_torrent_name' => $originalName . '_edited.torrent']);

            if (count($splitFiles) > 1) {
                // Create zip of split parts
                $zipPath = $storagePath . '/' . $originalName . '_split.zip';
                $zip = new \ZipArchive();
                $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                foreach ($splitFiles as $sf) {
                    $zip->addFile($sf, basename($sf));
                }
                $zip->close();
                session(['split_zip' => $zipPath, 'split_zip_name' => $originalName . '_split.zip']);
            } elseif (count($splitFiles) === 1) {
                session(['split_zip' => $splitFiles[0], 'split_zip_name' => basename($splitFiles[0])]);
            }

            // Also keep the global as default "edited_torrent"
            session(['edited_torrent' => $globalPath, 'edited_torrent_name' => $originalName . '_edited.torrent']);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'],
                'filename' => $originalName,
                'split_results' => [
                    'global' => $originalName . '_edited.torrent',
                    'split_count' => count($splitFiles),
                    'split_name' => session('split_zip_name'),
                ],
            ]);
        }

        // Clean up on failure
        foreach ([$filePath, $globalPath] as $f) {
            if (file_exists($f)) unlink($f);
        }

        return back()->withErrors(['torrent' => 'Split failed: ' . $result['output']])->withInput();
    }

    // ──────────────────────────────────────────────────────────────────────
    // MAGNET LINK
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Show the magnet link form.
     */
    public function magnetForm()
    {
        return view('torrent.magnet');
    }

    /**
     * Resolve a magnet link then split + randomize.
     */
    public function magnet(Request $request)
    {
        $request->validate([
            'magnet' => 'required|string|starts_with:magnet:',
            'action' => 'required|in:split,edit_only,download_only',
            'max_size' => 'nullable|numeric|min:0.1|max:100',
        ]);

        $storagePath = config('torrent-editor.upload_path');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $magnetUri = $request->input('magnet');
        $torrentPath = $storagePath . '/' . uniqid('magnet_') . '.torrent';

        // Step 1: Resolve magnet to .torrent
        $result = $this->editor->resolveMagnet($magnetUri, $torrentPath, 120);

        if (!$result['success']) {
            return back()->withErrors(['magnet' => 'Resolution du magnet echouee: ' . $result['output']])->withInput();
        }

        $action = $request->input('action');

        // Action: just download the resolved .torrent
        if ($action === 'download_only') {
            $info = $this->editor->getInfo($torrentPath);
            session(['edited_torrent' => $torrentPath, 'edited_torrent_name' => 'resolved.torrent']);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'] . "\n\n" . ($info['output'] ?? ''),
                'filename' => 'resolved.torrent',
            ]);
        }

        // Action: edit metadata only (randomize global)
        if ($action === 'edit_only') {
            $this->editor->alterMetadata($torrentPath, [
                'comment' => '', 'created_by' => '', 'creation_date' => 'now', 'source' => '',
            ]);
            $this->editor->changeHash($torrentPath);

            session(['edited_torrent' => $torrentPath, 'edited_torrent_name' => 'edited_magnet.torrent']);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'] . "\n\nMetadata randomized + hash changed.",
                'filename' => 'edited_magnet.torrent',
            ]);
        }

        // Action: split + randomize (default)
        $originalName = 'magnet_' . substr(md5($magnetUri), 0, 8);

        // Global: clean metadata + change hash
        $globalPath = $storagePath . '/' . uniqid('global_') . '.torrent';
        copy($torrentPath, $globalPath);
        $this->editor->alterMetadata($globalPath, [
            'comment' => '', 'created_by' => '', 'creation_date' => 'now', 'source' => '',
        ]);
        $this->editor->changeHash($globalPath);

        // Split with randomize
        $outputDir = $storagePath . '/split_' . uniqid();
        mkdir($outputDir, 0755, true);
        $maxSize = $request->input('max_size', 1.0);

        $splitResult = $this->editor->splitTorrent($torrentPath, $outputDir, (float) $maxSize, true);

        if ($splitResult['success']) {
            $splitFiles = glob($outputDir . '/*.torrent');

            session(['global_torrent' => $globalPath, 'global_torrent_name' => $originalName . '_edited.torrent']);

            if (count($splitFiles) > 1) {
                $zipPath = $storagePath . '/' . $originalName . '_split.zip';
                $zip = new \ZipArchive();
                $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                foreach ($splitFiles as $sf) {
                    $zip->addFile($sf, basename($sf));
                }
                $zip->close();
                session(['split_zip' => $zipPath, 'split_zip_name' => $originalName . '_split.zip']);
            } elseif (count($splitFiles) === 1) {
                session(['split_zip' => $splitFiles[0], 'split_zip_name' => basename($splitFiles[0])]);
            }

            session(['edited_torrent' => $globalPath, 'edited_torrent_name' => $originalName . '_edited.torrent']);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'] . "\n\n" . $splitResult['output'],
                'filename' => $originalName,
                'split_results' => [
                    'global' => $originalName . '_edited.torrent',
                    'split_count' => count($splitFiles),
                    'split_name' => session('split_zip_name'),
                ],
            ]);
        }

        // If split fails (e.g., single file), just return the global edited
        session(['edited_torrent' => $globalPath, 'edited_torrent_name' => $originalName . '_edited.torrent']);

        return redirect()->route('torrent.result')->with([
            'success' => true,
            'output' => $result['output'] . "\n\nSplit not possible (single file or too small). Global file edited.",
            'filename' => $originalName . '_edited.torrent',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────

    protected function handleTrackers(string $filePath, Request $request, string $mode): array
    {
        $trackers = array_filter(
            array_map('trim', explode("\n", $request->input('trackers', ''))),
            fn($url) => !empty($url)
        );

        if (empty($trackers)) {
            return ['success' => false, 'output' => 'No tracker URLs provided.', 'backup' => null];
        }

        return match ($mode) {
            'add' => $this->editor->addTrackers($filePath, $trackers),
            'remove' => $this->editor->removeTrackers($filePath, $trackers),
            'replace' => $this->editor->replaceTrackers($filePath, $trackers),
        };
    }

    protected function handleRename(string $filePath, Request $request): array
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $request->input('renames', ''))),
            fn($line) => !empty($line) && str_contains($line, '=')
        );

        if (empty($lines)) {
            return ['success' => false, 'output' => 'No rename mappings provided.', 'backup' => null];
        }

        $renames = [];
        foreach ($lines as $line) {
            [$old, $new] = explode('=', $line, 2);
            $renames[trim($old)] = trim($new);
        }

        return $this->editor->renameFiles($filePath, $renames);
    }

    protected function handleMetadata(string $filePath, Request $request): array
    {
        $metadata = [];

        if ($request->has('comment')) {
            $metadata['comment'] = $request->input('comment', '');
        }
        if ($request->has('created_by')) {
            $metadata['created_by'] = $request->input('created_by', '');
        }
        if ($request->has('creation_date')) {
            $metadata['creation_date'] = $request->input('creation_date', '') ?: '0';
        }
        if ($request->has('source')) {
            $metadata['source'] = $request->input('source', '');
        }

        if (empty($metadata)) {
            return ['success' => false, 'output' => 'No metadata fields provided.', 'backup' => null];
        }

        return $this->editor->alterMetadata($filePath, $metadata);
    }
}
