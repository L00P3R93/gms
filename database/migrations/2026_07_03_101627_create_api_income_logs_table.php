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
        Schema::create('api_income_logs', function (Blueprint $table) {
            $table->id();
            $table->decimal('api_total', 18, 4)->default(0);
            $table->json('raw_response');
            $table->date('business_date');
            $table->timestamp('created_at')->nullable();

            $table->index('business_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_income_logs');
    }
};
