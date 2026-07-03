<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('holders', function (Blueprint $table) {
            // Add sort_order for deterministic distribution ordering
            if (! Schema::hasColumn('holders', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('status');
            }
        });

        // Note: The share field is already being cast to decimal in the model
        // The actual database type change might need manual SQL in production
        // For now, we rely on the model casting to handle precision

        // Convert holders_wallet balance to decimal for precise money handling
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE holders_wallet MODIFY COLUMN balance DECIMAL(18,4) DEFAULT 0');
        } else {
            Schema::table('holders_wallet', function (Blueprint $table) {
                $table->decimal('balance', 18, 4)->default(0)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holders', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        // Revert holders_wallet balance to double
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE holders_wallet MODIFY COLUMN balance DOUBLE DEFAULT 0');
        } else {
            Schema::table('holders_wallet', function (Blueprint $table) {
                $table->double('balance')->default(0)->change();
            });
        }
    }
};
