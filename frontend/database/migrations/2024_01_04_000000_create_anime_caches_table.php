<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anime_caches', function (Blueprint $table) {
            $table->id();
            // sha256 of the human cache_key: keeps the unique index short enough
            // for MySQL utf8mb4 (catalogue/season URLs easily exceed 191 chars).
            $table->char('key_hash', 64)->unique();
            // Logical bucket: search / catalogue / episodes / new_episodes / planning / trailer.
            $table->string('type', 32)->index();
            // The original key (for debugging / manual inspection), not indexed.
            $table->text('cache_key');
            // The JSON API payload — includes episode player/mirror ("vid") links.
            $table->longText('payload');
            // When the payload was last fetched from the backend (drives freshness).
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anime_caches');
    }
};
