<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. Orange Money
            $table->string('slug')->unique();        // orange_money, moov_money, wave
            $table->string('account_number')->nullable(); // number the client sends money to
            $table->text('instructions')->nullable();      // step-by-step procedure
            $table->string('qr_path')->nullable();         // admin-uploaded QR code image
            $table->string('color')->nullable();           // brand colour for the badge
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
