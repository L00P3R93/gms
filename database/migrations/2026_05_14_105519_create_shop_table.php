<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop', function (Blueprint $table) {
            $table->id();
            $table->string('name', 1000);
            $table->enum('category', ['chips', 'gems', 'background', 'themes', 'vip', 'cards']);
            $table->integer('price');
            $table->string('pic', 1000);
            $table->string('reward', 1000);
            $table->string('cart_pic', 1000);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop');
    }
};
