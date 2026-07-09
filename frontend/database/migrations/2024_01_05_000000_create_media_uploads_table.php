<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table) {
            $table->id();
            // sha256 of the absolute source path: dedupe uploads across re-runs.
            $table->char('path_hash', 64)->unique();
            $table->text('source_path');
            $table->string('title')->nullable();
            $table->string('provider', 32)->default('vidmoly');
            $table->string('file_code')->nullable();
            $table->text('embed_url')->nullable();
            // pending / uploaded / failed
            $table->string('status', 16)->default('pending')->index();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
