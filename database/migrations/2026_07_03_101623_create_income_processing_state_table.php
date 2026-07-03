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
        Schema::create('income_processing_state', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->decimal('last_processed_total', 18, 4)->default(0);
            $table->decimal('last_api_total', 18, 4)->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_processing_state');
    }
};
