<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a column to store the path of an uploaded .torrent file.
 *
 * When the admin uploads a .torrent file directly (instead of pasting a
 * magnet or URL), the binary file is stored on disk and its path is kept
 * here so the worker can read and send it to Real-Debrid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrent_downloads', function (Blueprint $table) {
            $table->string('torrent_file_path', 1024)->nullable()->after('torrent_url');
        });
    }

    public function down(): void
    {
        Schema::table('torrent_downloads', function (Blueprint $table) {
            $table->dropColumn('torrent_file_path');
        });
    }
};
