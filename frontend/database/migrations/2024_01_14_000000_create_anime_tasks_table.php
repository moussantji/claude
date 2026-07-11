<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team task-tracking for MANUAL download/upload work.
 * One row per anime: status, "modified" / "do later" flags, who is on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anime_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('anime_id')->unique();
            $table->string('status', 20)->default('todo'); // todo | in_progress | done
            $table->boolean('is_modified')->default(false);
            $table->boolean('is_deferred')->default(false); // "à faire plus tard" (ex: +25 épisodes)
            $table->string('assigned_to', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_modified');
            $table->index('is_deferred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anime_tasks');
    }
};
