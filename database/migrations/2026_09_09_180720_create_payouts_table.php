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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payee_id')->constrained('payees');
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('declined_by')->nullable()->constrained('users');
            $table->text('declined_reason')->nullable();
            $table->string('conversation_id')->nullable()->index();
            $table->string('receipt')->nullable();
            $table->text('response')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
