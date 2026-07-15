<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the detected video codec (hevc/h264/...) per file so we can avoid
 * sending HEVC/x265 files to hosts that convert them poorly (e.g. Streamtape).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrent_files', function (Blueprint $table) {
            $table->string('video_codec', 20)->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('torrent_files', function (Blueprint $table) {
            $table->dropColumn('video_codec');
        });
    }
};
