<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Directory
    |--------------------------------------------------------------------------
    |
    | The directory where uploaded torrent files will be stored for editing.
    |
    */
    'upload_path' => env('TORRENT_EDIT_UPLOAD_PATH', storage_path('app/torrents')),

    /*
    |--------------------------------------------------------------------------
    | Python Binary Path (for magnet link resolution only)
    |--------------------------------------------------------------------------
    |
    | Path to the Python 3 interpreter. Used only for the magnet link feature.
    | On most systems, 'python3' works. Adjust if using a venv.
    |
    | Examples:
    |   - 'python3'                          (system default)
    |   - '/usr/bin/python3'                 (explicit system path)
    |   - base_path('venv/bin/python3')      (project venv)
    |
    */
    'python' => env('TORRENT_PYTHON', 'python3'),

];
