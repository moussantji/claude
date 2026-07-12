<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torrent download jobs submitted by the admin.
 *
 * A single torrent can be a SEASON PACK containing many episodes, so the
 * schema is split in two:
 *
 *   torrent_downloads : the torrent itself (one .torrent / magnet link)
 *   torrent_files     : each video file found inside it (one row per episode)
 *
 * Flow: admin submits torrent/magnet → aria2c downloads ALL files → each video
 * file is uploaded to the target player (doodstream, vidmoly, ...) → each is
 * attached to the matching episode (episode number parsed from the filename).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_downloads', function (Blueprint $table) {
            $table->id();

            // The torrent source (URL to a .torrent file OR a magnet: URI)
            $table->text('torrent_url');

            // Target mapping. For a SEASON PACK, set season_id: each downloaded
            // file is auto-matched to an episode of this season by its number.
            // For a SINGLE episode torrent, set episode_id instead.
            $table->unsignedBigInteger('season_id')->nullable()->index();
            $table->unsignedBigInteger('episode_id')->nullable()->index();
            $table->string('language', 16)->default('VOSTFR');

            // Target player platform (doodstream, vidmoly, streamtape, mp4upload)
            $table->string('target_platform', 50)->default('doodstream');

            // Job status: pending → downloading → uploading → done / failed
            //   (uploading = iterating over torrent_files and pushing each one)
            $table->string('status', 20)->default('pending')->index();

            // aria2c GID (used to poll the download progress)
            $table->string('aria2_gid', 64)->nullable();

            // Download progress (0-100) across the whole torrent
            $table->unsignedTinyInteger('progress')->default(0);

            // File counters (populated once the torrent is scanned)
            $table->unsignedSmallInteger('files_total')->default(0);
            $table->unsignedSmallInteger('files_done')->default(0);
            $table->unsignedSmallInteger('files_failed')->default(0);

            // Metadata
            $table->string('title', 500)->nullable();
            $table->unsignedBigInteger('total_size')->nullable(); // bytes

            // Error tracking (job-level: download stage errors)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

            // Admin who submitted
            $table->unsignedBigInteger('submitted_by')->nullable();

            $table->timestamps();

            $table->foreign('season_id')->references('id')->on('seasons')->nullOnDelete();
            $table->foreign('episode_id')->references('id')->on('episodes')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::create('torrent_files', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('torrent_download_id')->index();

            // The video file inside the torrent
            $table->string('file_name', 1024);
            $table->string('local_path', 1024)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes

            // Episode matching
            $table->unsignedInteger('detected_episode')->nullable(); // parsed from filename
            $table->unsignedBigInteger('episode_id')->nullable()->index(); // matched episode
            $table->string('language', 16)->default('VOSTFR');

            // Per-file status: pending → uploading → done / failed / skipped
            //   skipped = no matching episode found and no episode_id set
            $table->string('status', 20)->default('pending')->index();

            // Upload result
            $table->string('embed_url', 2048)->nullable();
            $table->string('file_code', 255)->nullable();

            // Error tracking
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->timestamps();

            $table->foreign('torrent_download_id')->references('id')->on('torrent_downloads')->cascadeOnDelete();
            $table->foreign('episode_id')->references('id')->on('episodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_files');
        Schema::dropIfExists('torrent_downloads');
    }
};
