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

        $editedName = $this->safeName(pathinfo($originalName, PATHINFO_FILENAME)) . '_edited_' . uniqid() . '.torrent';
        $filePath = $storagePath . '/' . $editedName;
        $uploadedFile->move($storagePath, $editedName);

        // Validate torrent
        try {
            $this->editor->loadTorrent($filePath);
        } catch (\Exception $e) {
            if (file_exists($filePath)) unlink($filePath);
            return back()->withErrors(['torrent' => 'Fichier torrent invalide: ' . $e->getMessage()])->withInput();
        }

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
            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'],
                'single_file' => $editedName,
            ]);
        }

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return back()->withErrors(['torrent' => 'Editing failed: ' . $result['output']])->withInput();
    }

    /**
     * Show the result page. Uses flash data (not persisted session file paths).
     */
    public function result()
    {
        if (!session('single_file') && !session('split_results')) {
            return redirect()->route('dashboard');
        }

        return view('torrent.result', [
            'output' => session('output', ''),
            'singleFile' => session('single_file'),
            'splitResults' => session('split_results'),
        ]);
    }

    /**
     * Download a generated file by its name (basename only, path-traversal safe).
     * Does NOT depend on the session, so it works even if the session is lost.
     */
    public function download(Request $request)
    {
        $requested = $request->query('file', '');

        // Security: only allow a bare filename, no path traversal
        $name = basename($requested);
        if ($name === '' || $name !== $requested) {
            return redirect()->route('dashboard')
                ->withErrors(['torrent' => 'Nom de fichier invalide.']);
        }

        // Only allow .torrent or .zip
        if (!str_ends_with($name, '.torrent') && !str_ends_with($name, '.zip')) {
            return redirect()->route('dashboard')
                ->withErrors(['torrent' => 'Type de fichier non autorise.']);
        }

        $storagePath = config('torrent-editor.upload_path');
        $filePath = $storagePath . '/' . $name;

        if (!file_exists($filePath)) {
            return redirect()->route('dashboard')
                ->withErrors(['torrent' => 'Fichier non disponible (peut-etre expire). Relancez l\'operation.']);
        }

        $mimeType = str_ends_with($name, '.zip') ? 'application/zip' : 'application/x-bittorrent';

        clearstatcache(true, $filePath);
        $size = filesize($filePath);

        // Bulletproof RAW binary streaming. Clears ALL output buffers first so no
        // stray whitespace/BOM/framework transformation can corrupt the binary.
        return response()->stream(function () use ($filePath) {
            // Remove any active output buffering (prevents corruption/injection)
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $fp = fopen($filePath, 'rb');
            fpassthru($fp);
            fclose($fp);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Encoding' => 'identity',
            'Accept-Ranges' => 'none',
            'Cache-Control' => 'no-store, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
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
            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'],
                'single_file' => $filename,
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

        // Validate: check the file is a valid torrent
        try {
            $this->editor->loadTorrent($filePath);
        } catch (\Exception $e) {
            if (file_exists($filePath)) unlink($filePath);
            return back()->withErrors(['torrent' => 'Fichier torrent invalide: ' . $e->getMessage()])->withInput();
        }

        // ── STEP 1: Auto-randomize the GLOBAL torrent file ──
        $globalName = $this->safeName($originalName) . '_global_' . uniqid() . '.torrent';
        $globalPath = $storagePath . '/' . $globalName;
        copy($filePath, $globalPath);

        $this->editor->alterMetadata($globalPath, [
            'comment' => '', 'created_by' => '', 'creation_date' => 'now', 'source' => '',
        ]);
        $this->editor->changeHash($globalPath);

        // ── STEP 2: Split into parts (always randomized) ──
        $outputDir = $storagePath . '/split_' . uniqid();
        mkdir($outputDir, 0755, true);
        $maxSize = $request->input('max_size', 1.0);

        $result = $this->editor->splitTorrent($filePath, $outputDir, (float) $maxSize, true);

        // Clean up the original upload
        if (file_exists($filePath)) unlink($filePath);

        // Move split parts into the main storage dir with unique names
        $partNames = [];
        $movedParts = [];
        foreach (glob($outputDir . '/*.torrent') as $sf) {
            $partName = 'part_' . uniqid() . '_' . basename($sf);
            $dest = $storagePath . '/' . $partName;
            rename($sf, $dest);
            $partNames[] = $partName;
            $movedParts[] = $dest;
        }
        @rmdir($outputDir);
        sort($partNames);

        // Build a ZIP for convenience
        $zipName = null;
        if (count($movedParts) >= 1) {
            $zipName = $this->safeName($originalName) . '_split_' . uniqid() . '.zip';
            $zip = new \ZipArchive();
            $zip->open($storagePath . '/' . $zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            foreach ($movedParts as $mp) {
                $zip->addFile($mp, basename($mp));
            }
            $zip->close();
        }

        return redirect()->route('torrent.result')->with([
            'success' => true,
            'output' => $result['output'],
            'split_results' => [
                'global_file' => $globalName,
                'part_files' => $partNames,
                'zip_file' => $zipName,
                'split_count' => count($partNames),
            ],
        ]);
    }

    /**
     * Sanitize a name for use in a filename.
     */
    protected function safeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return substr($name, 0, 60) ?: 'torrent';
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
        $base = 'magnet_' . substr(md5($magnetUri), 0, 8);

        // Action: just download the resolved .torrent
        if ($action === 'download_only') {
            $info = $this->editor->getInfo($torrentPath);
            $name = $base . '_resolved.torrent';
            rename($torrentPath, $storagePath . '/' . $name);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'] . "\n\n" . ($info['output'] ?? ''),
                'single_file' => $name,
            ]);
        }

        // Action: edit metadata only
        if ($action === 'edit_only') {
            $this->editor->alterMetadata($torrentPath, [
                'comment' => '', 'created_by' => '', 'creation_date' => 'now', 'source' => '',
            ]);
            $this->editor->changeHash($torrentPath);
            $name = $base . '_edited.torrent';
            rename($torrentPath, $storagePath . '/' . $name);

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'] . "\n\nMetadata randomized + hash changed.",
                'single_file' => $name,
            ]);
        }

        // Action: split + randomize (default)
        $globalName = $base . '_global_' . uniqid() . '.torrent';
        $globalPath = $storagePath . '/' . $globalName;
        copy($torrentPath, $globalPath);
        $this->editor->alterMetadata($globalPath, [
            'comment' => '', 'created_by' => '', 'creation_date' => 'now', 'source' => '',
        ]);
        $this->editor->changeHash($globalPath);

        $outputDir = $storagePath . '/split_' . uniqid();
        mkdir($outputDir, 0755, true);
        $maxSize = $request->input('max_size', 1.0);

        $splitResult = $this->editor->splitTorrent($torrentPath, $outputDir, (float) $maxSize, true);

        if (file_exists($torrentPath)) unlink($torrentPath);

        // Move parts to storage dir
        $partNames = [];
        $movedParts = [];
        foreach (glob($outputDir . '/*.torrent') as $sf) {
            $partName = 'part_' . uniqid() . '_' . basename($sf);
            rename($sf, $storagePath . '/' . $partName);
            $partNames[] = $partName;
            $movedParts[] = $storagePath . '/' . $partName;
        }
        @rmdir($outputDir);
        sort($partNames);

        $zipName = null;
        if (count($movedParts) >= 1) {
            $zipName = $base . '_split_' . uniqid() . '.zip';
            $zip = new \ZipArchive();
            $zip->open($storagePath . '/' . $zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            foreach ($movedParts as $mp) {
                $zip->addFile($mp, basename($mp));
            }
            $zip->close();
        }

        return redirect()->route('torrent.result')->with([
            'success' => true,
            'output' => $result['output'] . "\n\n" . $splitResult['output'],
            'split_results' => [
                'global_file' => $globalName,
                'part_files' => $partNames,
                'zip_file' => $zipName,
                'split_count' => count($partNames),
            ],
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
