<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('service')->nullable();
            $table->unsignedBigInteger('budget')->default(0);
            $table->string('status')->default('Nouveau');
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('sector')->nullable();
            $table->unsignedBigInteger('value')->default(0);
            $table->string('status')->default('Actif');
            $table->date('since')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('budget')->default(0);
            $table->string('status')->default('En attente');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('deadline')->nullable();
            $table->string('lead')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('E-commerce');
            $table->unsignedBigInteger('price')->default(0);
            $table->string('cycle')->nullable();
            $table->unsignedBigInteger('subscribers')->default(0);
            $table->string('status')->default('Publie');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->nullable();
            $table->string('customer');
            $table->string('type')->default('E-commerce');
            $table->string('item')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('status')->default('En attente');
            $table->string('city')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->string('author')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->default('Brouillon');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('service')->nullable();
            $table->string('status')->default('Nouveau');
            $table->timestamp('date')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company')->default('Manden Baoubab');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('currency')->default('FCFA');
            $table->string('slogan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('products');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('settings');
    }
};
