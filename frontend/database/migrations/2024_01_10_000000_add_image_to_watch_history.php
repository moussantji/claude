<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_history', function (Blueprint $table) {
            $table->text('image_url')->nullable()->after('language');
            $table->string('catalogue_url', 2048)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('watch_history', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'catalogue_url']);
        });
    }
};
