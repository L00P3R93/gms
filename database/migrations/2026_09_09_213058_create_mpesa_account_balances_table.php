<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_account_balances', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['b2c', 'c2b']);
            $table->string('conversation_id')->nullable();
            $table->string('originator_conversation_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('working_account_balance', 12, 2)->default(0);
            $table->decimal('utility_account_balance', 12, 2)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['type', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_account_balances');
    }
};
