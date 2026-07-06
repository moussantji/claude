<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('catalogue_url', 2048);
            $table->string('name');
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'catalogue_url'], 'favorites_user_catalogue_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
