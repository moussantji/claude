<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track whether the admin has manually pushed the batch's uploaded links into
 * the database (episode_links).
 *
 * The DB is no longer updated automatically after upload: the admin verifies
 * on the player that everything is live, then clicks "Mettre à jour la base"
 * which sets this flag and creates the episode_links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torrent_downloads', function (Blueprint $table) {
            $table->boolean('links_synced')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('torrent_downloads', function (Blueprint $table) {
            $table->dropColumn('links_synced');
        });
    }
};
