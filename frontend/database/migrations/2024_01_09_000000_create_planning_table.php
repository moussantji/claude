<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anime_id')->constrained('animes')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Lundi ... 6=Dimanche
            $table->string('time', 5)->nullable(); // HH:MM
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning');
    }
};
