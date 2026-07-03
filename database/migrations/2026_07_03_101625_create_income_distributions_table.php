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
        Schema::create('income_distributions', function (Blueprint $table) {
            $table->id();
            $table->decimal('previous_total', 18, 4)->default(0);
            $table->decimal('current_total', 18, 4)->default(0);
            $table->decimal('delta', 18, 4)->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_distributions');
    }
};
