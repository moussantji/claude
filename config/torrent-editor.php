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

];
