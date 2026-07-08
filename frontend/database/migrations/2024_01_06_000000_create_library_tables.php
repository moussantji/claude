<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalized library: each entity in its own table.
 *
 *   animes 1---* seasons 1---* episodes 1---* episode_links
 *
 * This is the structured, queryable store (as opposed to the raw JSON API
 * cache in `anime_caches`). Video/player links live in their own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animes', function (Blueprint $table) {
            $table->id();
            $table->string('url', 512)->unique();
            $table->string('slug')->index();
            $table->string('name');
            $table->json('alternative_names')->nullable();
            $table->json('genres')->nullable();
            $table->json('categories')->nullable();
            $table->json('languages')->nullable();
            $table->text('image_url')->nullable();
            $table->longText('synopsis')->nullable();
            $table->boolean('is_mature')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anime_id')->constrained('animes')->cascadeOnDelete();
            $table->string('url', 512)->unique();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->unsignedInteger('index');
            $table->string('name')->nullable();
            $table->string('long_name')->nullable();
            $table->string('short_name')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['season_id', 'index']);
        });

        Schema::create('episode_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->cascadeOnDelete();
            $table->string('language', 16)->index();
            $table->unsignedInteger('position')->default(0);
            $table->text('url');
            $table->string('host')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_links');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('animes');
    }
};
