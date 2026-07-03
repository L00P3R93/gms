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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holder_id')->constrained('holders')->onDelete('cascade');
            $table->foreignId('distribution_id')->nullable()->constrained('income_distributions')->onDelete('set null');
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('balance_before', 18, 4)->default(0);
            $table->decimal('balance_after', 18, 4)->default(0);
            $table->string('description')->nullable();
            $table->string('transaction_type')->default('credit');
            $table->timestamps();

            $table->index(['holder_id', 'distribution_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
