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
        Schema::table('users', function (Blueprint $table) {
            $table->string('userName', 100)->unique()->after('name');
            $table->integer('status')->default(1)->after('password');
            $table->mediumText('referral_codes')->nullable()->after('status');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['userName', 'status', 'referral_codes', 'createdAt', 'updatedAt']);
        });
    }
};
