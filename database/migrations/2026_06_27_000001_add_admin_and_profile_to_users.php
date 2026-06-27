<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
            // Unique credentials used to generate the user's IPTV playlist.
            $table->string('iptv_username')->nullable()->unique()->after('is_admin');
            $table->string('iptv_password')->nullable()->after('iptv_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'iptv_username', 'iptv_password']);
        });
    }
};
