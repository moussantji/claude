<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-target source URL override.
 *
 * Lets the admin retry ONE failed player (e.g. Vidmoly) with a NEW source
 * link, without touching the players that already succeeded. When null, the
 * target falls back to the parent torrent_file's source_url.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('torrent_file_targets') && ! Schema::hasColumn('torrent_file_targets', 'source_url')) {
            Schema::table('torrent_file_targets', function (Blueprint $table) {
                $table->string('source_url', 2048)->nullable()->after('platform');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('torrent_file_targets') && Schema::hasColumn('torrent_file_targets', 'source_url')) {
            Schema::table('torrent_file_targets', function (Blueprint $table) {
                $table->dropColumn('source_url');
            });
        }
    }
};
