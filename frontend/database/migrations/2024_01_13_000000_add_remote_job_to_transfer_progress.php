<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds remote_job_id for server-to-server (remote upload) transfers.
 * With remote upload, the target host fetches the video itself, so a job is
 * queued and finalized later (status 'queued' → 'done').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_progress', function (Blueprint $table) {
            $table->string('remote_job_id', 255)->nullable()->after('new_file_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_progress', function (Blueprint $table) {
            $table->dropColumn('remote_job_id');
        });
    }
};
