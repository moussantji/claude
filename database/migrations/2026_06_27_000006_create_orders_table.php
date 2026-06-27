<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_name')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_name')->nullable();
            $table->string('payment_number')->nullable(); // phone number the customer paid from
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('FCFA');
            $table->string('status')->default('pending'); // pending, processing, completed, rejected

            // Fulfilment details supplied by an admin.
            $table->string('iptv_username')->nullable();
            $table->string('iptv_password')->nullable();
            $table->string('iptv_dns')->nullable();
            $table->text('admin_note')->nullable();

            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
