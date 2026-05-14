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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiver_id');
            $table->enum('type', ['holder', 'dependant'])->default('holder');
            $table->string('phone', 50);
            $table->double('amount', 10, 2)->default(0.00);
            $table->integer('status')->default(1);
            $table->string('receipt', 80)->nullable()->unique();
            $table->string('conversation_id', 255)->nullable();
            $table->mediumText('response')->nullable();
            $table->integer('approve')->default(0);
            $table->mediumText('comments')->nullable();
            $table->timestamps();

            $table->index('receiver_id');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};
