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
        Schema::create('holders_wallet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('holder_id');
            $table->double('balance', 10, 2)->default(0.00);
            $table->dateTime('updated_at')->nullable();

            $table->foreign('holder_id')->references('id')->on('holders')->onDelete('cascade');
            $table->index('holder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holders_wallet');
    }
};
