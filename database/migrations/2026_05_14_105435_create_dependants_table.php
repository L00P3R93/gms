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
        Schema::create('dependants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('holder_id');
            $table->string('name', 80);
            $table->string('phone', 50)->nullable();
            $table->string('id_no', 50)->nullable();
            $table->double('share', 10, 2)->default(0.00);
            $table->integer('status')->default(1);
            $table->timestamps();

            $table->foreign('holder_id')->references('id')->on('holders')->onDelete('cascade');
            $table->index('holder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dependants');
    }
};
