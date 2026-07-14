<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-target upload for torrents: push each episode to SEVERAL player hosts
 * at once (quotas, retry and embed URL tracked per player).
 *
 * Split out of the original 2024_01_15 migration so servers that already ran
 * it still receive these additions:
 *   • torrent_downloads.target_platforms  (JSON list of players)
 *   • torrent_file_targets                (one row per file × player)
 *
 * Every change is guarded (hasColumn / hasTable) so it is safe to run whether
 * or not the objects already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('torrent_downloads') && ! Schema::hasColumn('torrent_downloads', 'target_platforms')) {
            Schema::table('torrent_downloads', function (Blueprint $table) {
                // JSON list, e.g. ["doodstream","vidmoly",...]. Null = fall back
                // to the legacy single `target_platform`.
                $table->text('target_platforms')->nullable()->after('target_platform');
            });
        }

        if (! Schema::hasTable('torrent_file_targets')) {
            Schema::create('torrent_file_targets', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('torrent_file_id')->index();
                $table->string('platform', 50); // doodstream, vidmoly, streamtape, mp4upload

                // pending → queued (host fetching, remote upload) → done / failed
                $table->string('status', 20)->default('pending')->index();

                $table->string('remote_job_id', 255)->nullable();
                $table->string('embed_url', 2048)->nullable();
                $table->string('file_code', 255)->nullable();

                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('error')->nullable();

                $table->timestamps();

                $table->foreign('torrent_file_id')->references('id')->on('torrent_files')->cascadeOnDelete();
                $table->unique(['torrent_file_id', 'platform']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_file_targets');

        if (Schema::hasTable('torrent_downloads') && Schema::hasColumn('torrent_downloads', 'target_platforms')) {
            Schema::table('torrent_downloads', function (Blueprint $table) {
                $table->dropColumn('target_platforms');
            });
        }
    }
};
