<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add anime_id to torrent_downloads so batches are directly linked to an
 * anime. Used for:
 *   - Auto-resolving the title from the anime name
 *   - Organizing downloaded files in anime/season/episode folders
 *   - Ensuring episode_links are always correctly created
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrent_downloads', function (Blueprint $table) {
            $table->unsignedBigInteger('anime_id')->nullable()->after('id');
            $table->foreign('anime_id')->references('id')->on('animes')->nullOnDelete();
            $table->index('anime_id');
        });
    }

    public function down(): void
    {
        Schema::table('torrent_downloads', function (Blueprint $table) {
            $table->dropForeign(['anime_id']);
            $table->dropColumn('anime_id');
        });
    }
};
