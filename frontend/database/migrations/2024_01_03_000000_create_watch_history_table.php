<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('serie_name');
            $table->string('season_url', 2048);
            $table->string('season_name')->nullable();
            $table->unsignedInteger('episode_index');
            $table->string('episode_name')->nullable();
            $table->string('player_url', 2048)->nullable();
            $table->string('language', 20)->nullable();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'season_url', 'episode_index'], 'watch_history_unique_episode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_history');
    }
};
