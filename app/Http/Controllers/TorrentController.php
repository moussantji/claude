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

        // Store uploaded torrent file
        $uploadedFile = $request->file('torrent');
        $originalName = $uploadedFile->getClientOriginalName();
        $storagePath = config('torrent-editor.upload_path');

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filePath = $storagePath . '/' . uniqid('torrent_') . '_' . $originalName;
        $uploadedFile->move($storagePath, basename($filePath));

        // Execute the appropriate action
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

        // Clean up on failure
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
        if (!session('edited_torrent')) {
            return redirect()->route('torrent.index');
        }

        return view('torrent.result', [
            'output' => session('output', ''),
            'filename' => session('edited_torrent_name', 'edited.torrent'),
        ]);
    }

    /**
     * Download the edited torrent file.
     */
    public function download()
    {
        $filePath = session('edited_torrent');
        $originalName = session('edited_torrent_name', 'edited.torrent');

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->route('torrent.index')
                ->withErrors(['torrent' => 'No edited torrent available for download.']);
        }

        return response()->download($filePath, $originalName)->deleteFileAfterSend(true);
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
     * Handle tracker operations.
     */
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

    /**
     * Handle file rename operations.
     * Format: one rename per line, "old_name=new_name"
     */
    protected function handleRename(string $filePath, Request $request): array
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $request->input('renames', ''))),
            fn($line) => !empty($line) && str_contains($line, '=')
        );

        if (empty($lines)) {
            return ['success' => false, 'output' => 'No rename mappings provided. Use format: old_name=new_name', 'backup' => null];
        }

        $renames = [];
        foreach ($lines as $line) {
            [$old, $new] = explode('=', $line, 2);
            $renames[trim($old)] = trim($new);
        }

        return $this->editor->renameFiles($filePath, $renames);
    }

    /**
     * Handle metadata alteration.
     */
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
            $value = $request->input('creation_date', '');
            // Accept: 'now', '0' (remove), or a unix timestamp
            $metadata['creation_date'] = $value ?: '0';
        }

        if ($request->has('source')) {
            $metadata['source'] = $request->input('source', '');
        }

        if (empty($metadata)) {
            return ['success' => false, 'output' => 'No metadata fields provided.', 'backup' => null];
        }

        return $this->editor->alterMetadata($filePath, $metadata);
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
     */
    public function split(Request $request)
    {
        $request->validate([
            'torrent' => 'required|file|max:10240',
            'max_size' => 'nullable|numeric|min:0.1|max:100',
        ]);

        // Store uploaded torrent file
        $uploadedFile = $request->file('torrent');
        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $storagePath = config('torrent-editor.upload_path');

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filePath = $storagePath . '/' . uniqid('torrent_') . '_' . $uploadedFile->getClientOriginalName();
        $uploadedFile->move($storagePath, basename($filePath));

        // Output directory for split parts
        $outputDir = $storagePath . '/split_' . uniqid();
        mkdir($outputDir, 0755, true);

        $maxSize = $request->input('max_size', 1.0);

        $result = $this->editor->splitTorrent($filePath, $outputDir, (float) $maxSize);

        if ($result['success']) {
            // Zip the split torrents for download
            $splitFiles = glob($outputDir . '/*.torrent');

            if (count($splitFiles) === 0) {
                return back()->withErrors(['torrent' => 'Split produced no files (torrent may already be under the size limit).'])->withInput();
            }

            if (count($splitFiles) === 1) {
                // Only one part — just return it
                $singleFile = $splitFiles[0];
                session(['edited_torrent' => $singleFile, 'edited_torrent_name' => basename($singleFile)]);
            } else {
                // Multiple parts — create a zip
                $zipPath = $storagePath . '/' . $originalName . '_split.zip';
                $zip = new \ZipArchive();
                $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                foreach ($splitFiles as $sf) {
                    $zip->addFile($sf, basename($sf));
                }
                $zip->close();
                session(['edited_torrent' => $zipPath, 'edited_torrent_name' => $originalName . '_split.zip']);
            }

            return redirect()->route('torrent.result')->with([
                'success' => true,
                'output' => $result['output'],
                'filename' => session('edited_torrent_name'),
            ]);
        }

        // Clean up
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return back()->withErrors(['torrent' => 'Split failed: ' . $result['output']])->withInput();
    }
}
