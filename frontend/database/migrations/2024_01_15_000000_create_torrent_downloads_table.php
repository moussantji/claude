<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Torrent" remote-upload jobs submitted by the admin.
 *
 * The admin submits a magnet / .torrent (or, optionally, direct download
 * links). How the torrent becomes a playable link depends on the provider:
 *
 *   • aria2 (100% free): OUR server downloads the torrent with aria2c, then
 *     uploads each video file to the TARGET player host (file upload).
 *   • realdebrid (paid): Real-Debrid downloads it and exposes a direct HTTP
 *     link per file, which the host fetches itself (remote upload). Nothing is
 *     downloaded on our server.
 *
 * Once a file is live on the host, its embed URL is attached to the episode.
 *
 * Because a torrent can be a SEASON PACK, the schema is split in two:
 *   torrent_downloads : one batch (a magnet/torrent or a set of links)
 *   torrent_files     : one row per file/link (→ one episode)
 *
 * Batch status:
 *   pending → downloading (aria2) | resolving (Real-Debrid) → processing → done
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_downloads', function (Blueprint $table) {
            $table->id();

            // Target mapping. Season pack → season_id (each link auto-matched to
            // an episode by its number). Single episode → episode_id.
            $table->unsignedBigInteger('season_id')->nullable()->index();
            $table->unsignedBigInteger('episode_id')->nullable()->index();
            $table->string('language', 16)->default('VOSTFR');

            // Target player host: doodstream, vidmoly, streamtape, mp4upload.
            $table->string('target_platform', 50)->default('doodstream');

            // How the torrent is fetched: 'aria2' (free, self-hosted) or
            // 'realdebrid' (paid remote upload).
            $table->string('provider', 20)->default('aria2');

            // Source: a magnet / .torrent URL. Null when the admin pasted
            // direct links instead.
            $table->text('torrent_url')->nullable();

            // aria2c GID while downloading (self-hosted provider).
            $table->string('aria2_gid', 64)->nullable();

            // Slice mode (limited-disk VPS): ordered JSON list of the torrent's
            // video files [{index,path,length}] used to download a few at a time.
            $table->text('aria2_files')->nullable();

            // Real-Debrid torrent id (set while resolving) + its progress 0-100.
            $table->string('debrid_id', 64)->nullable();
            $table->unsignedTinyInteger('debrid_progress')->default(0);

            // Batch status:
            //   pending → downloading (aria2) | resolving (RD) → processing → done/failed
            $table->string('status', 20)->default('pending')->index();

            // Per-file counters (link-level results)
            $table->unsignedSmallInteger('files_total')->default(0);
            $table->unsignedSmallInteger('files_done')->default(0);
            $table->unsignedSmallInteger('files_failed')->default(0);

            // Metadata / errors
            $table->string('title', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

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

            // The direct download link (from Real-Debrid, or pasted by the
            // admin) sent to the host's remote-upload API. Empty for aria2,
            // which uploads the local file instead.
            $table->text('source_url')->nullable();
            // Local path of the downloaded video (aria2 provider only).
            $table->string('local_path', 1024)->nullable();
            $table->string('file_name', 1024)->nullable(); // basename, for ep detection

            // Episode matching
            $table->unsignedInteger('detected_episode')->nullable();
            $table->unsignedBigInteger('episode_id')->nullable()->index();
            $table->string('language', 16)->default('VOSTFR');

            // Per-file status: pending → (queued: host fetching, RD only) → done
            //   failed  = host refused / errored
            //   skipped = no episode matched (needs manual mapping first)
            $table->string('status', 20)->default('pending')->index();

            // Remote job id returned by the host (polled via remoteStatus)
            $table->string('remote_job_id', 255)->nullable();

            // Result once the host finishes
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
