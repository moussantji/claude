<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Torrent" remote-upload jobs submitted by the admin.
 *
 * The admin provides direct DOWNLOAD LINKS (e.g. DDL/seedbox HTTP URLs to the
 * episode files of a torrent). We do NOT download them on our server. Instead
 * each link is sent to the TARGET player host (Doodstream, Vidmoly, Streamtape,
 * Mp4upload) which fetches it itself (server-to-server "remote upload"). Once
 * the host finishes, the resulting embed URL is attached to the episode.
 *
 * Because a torrent can be a SEASON PACK, the schema is split in two:
 *   torrent_downloads : one batch (a set of links for a season / an episode)
 *   torrent_files     : one row per download link (→ one episode)
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

            // Target player host that will fetch the links (must support remote
            // upload): doodstream, vidmoly, streamtape, mp4upload.
            $table->string('target_platform', 50)->default('doodstream');

            // Batch status: pending → processing → done / failed
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

            // The direct download link the admin provides. Sent as-is to the
            // target host's remote-upload API (the host downloads it).
            $table->text('source_url');
            $table->string('file_name', 1024)->nullable(); // basename, for ep detection

            // Episode matching
            $table->unsignedInteger('detected_episode')->nullable();
            $table->unsignedBigInteger('episode_id')->nullable()->index();
            $table->string('language', 16)->default('VOSTFR');

            // Per-link status: pending → queued (host is fetching) → done
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
