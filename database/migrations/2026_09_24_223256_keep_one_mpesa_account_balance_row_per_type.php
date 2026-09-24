<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each balance type now keeps one row that every fetch updates. The newest row
 * per type is kept and older rows are deleted; they cannot be restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('mpesa_account_balances')->distinct()->pluck('type') as $type) {
            $newestId = DB::table('mpesa_account_balances')
                ->where('type', $type)
                ->orderByDesc('fetched_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('mpesa_account_balances')
                ->where('type', $type)
                ->where('id', '!=', $newestId)
                ->delete();
        }

        Schema::table('mpesa_account_balances', function (Blueprint $table) {
            $table->unique('type');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_account_balances', function (Blueprint $table) {
            $table->dropUnique(['type']);
        });
    }
};
