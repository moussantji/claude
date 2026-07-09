<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::table('animes', function (Blueprint $table) {
            // Manually set trailer (YouTube URL or key). Never overwritten by sync.
            $table->text('trailer_url')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('animes', function (Blueprint $table) {
            $table->dropColumn('trailer_url');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
