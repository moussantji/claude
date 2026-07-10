<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks transfer progress for the episodes:daily-transfer command.
 * Allows resuming from where we left off and enforcing daily quotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('episode_link_id')->unique();
            $table->unsignedBigInteger('anime_id')->index();
            $table->string('host', 100)->index();
            $table->string('status', 20)->default('pending'); // pending, done, failed
            $table->string('new_url', 2048)->nullable();
            $table->string('new_file_code', 255)->nullable();
            $table->text('error')->nullable();
            $table->date('transferred_at')->nullable();
            $table->timestamps();

            $table->index(['anime_id', 'host', 'status']);
            $table->index(['anime_id', 'host', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_progress');
    }
};
