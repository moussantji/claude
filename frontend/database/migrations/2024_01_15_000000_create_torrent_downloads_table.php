<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores torrent download jobs submitted by the admin.
 *
 * Flow: admin submits torrent/magnet link → system downloads via aria2c →
 * uploads the resulting video to a target player (doodstream, vidmoly, etc.)
 * → creates/updates the episode_link in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torrent_downloads', function (Blueprint $table) {
            $table->id();

            // The torrent source (URL to .torrent file OR magnet: URI)
            $table->text('torrent_url');

            // Target episode link (optional: if provided, the downloaded video
            // will be attached to this episode automatically)
            $table->unsignedBigInteger('episode_id')->nullable()->index();
            $table->string('language', 16)->default('VOSTFR');

            // Target player platform (doodstream, vidmoly, streamtape, mp4upload)
            $table->string('target_platform', 50)->default('doodstream');

            // Job status: pending → downloading → uploading → done / failed
            $table->string('status', 20)->default('pending')->index();

            // aria2c GID (used to poll download progress)
            $table->string('aria2_gid', 64)->nullable();

            // Path to the downloaded file on disk (temporary)
            $table->string('local_path', 1024)->nullable();

            // Result after upload
            $table->string('embed_url', 2048)->nullable();
            $table->string('file_code', 255)->nullable();

            // Metadata
            $table->string('title', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->unsignedTinyInteger('progress')->default(0); // 0-100 download %

            // Error tracking
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

            // Admin who submitted
            $table->unsignedBigInteger('submitted_by')->nullable();

            $table->timestamps();

            $table->foreign('episode_id')->references('id')->on('episodes')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_downloads');
    }
};
