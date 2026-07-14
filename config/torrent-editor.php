<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Torrent-Edit Binary Path
    |--------------------------------------------------------------------------
    |
    | The full path to the torrent-edit Python CLI binary (tracker/privacy ops).
    |
    */
    'binary' => env('TORRENT_EDIT_BINARY', '/root/.pyenv/shims/torrent-edit'),

    /*
    |--------------------------------------------------------------------------
    | Advanced Script Path
    |--------------------------------------------------------------------------
    |
    | Path to the advanced Python script for rename/metadata/hash operations.
    |
    */
    'advanced_script' => env('TORRENT_ADVANCED_SCRIPT', base_path('scripts/torrent_advanced.py')),

    /*
    |--------------------------------------------------------------------------
    | Python Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the Python 3 interpreter.
    |
    */
    'python' => env('TORRENT_PYTHON_BINARY', '/root/.pyenv/shims/python3'),

    /*
    |--------------------------------------------------------------------------
    | Upload Directory
    |--------------------------------------------------------------------------
    |
    | The directory where uploaded torrent files will be stored for editing.
    |
    */
    'upload_path' => env('TORRENT_EDIT_UPLOAD_PATH', storage_path('app/torrents')),

];
