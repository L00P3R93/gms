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
        Schema::create('company_withdraws', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 50)->nullable();
            $table->double('amount', 10, 2)->default(0.00);
            $table->unsignedBigInteger('user_id')->default(0);
            $table->mediumText('reason')->nullable();
            $table->string('receipt', 100)->nullable();
            $table->string('conversation_id', 100)->nullable();
            $table->mediumText('response')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('approved_by')->default(0);
            $table->timestamps();

            $table->index('approved_by');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_withdraws');
    }
};
