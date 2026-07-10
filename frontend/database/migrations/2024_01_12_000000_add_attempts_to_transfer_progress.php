<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an attempts counter to transfer_progress so failed transfers can be
 * auto-retried a limited number of times (error handling for daily quota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_progress', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_progress', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_attempt_at']);
        });
    }
};
