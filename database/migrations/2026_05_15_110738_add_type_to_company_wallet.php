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
        Schema::table('company_wallet', function (Blueprint $table) {
            $table->string('type', 50)->after('id')->default('company');
        });

        DB::statement("UPDATE company_wallet SET type='company' WHERE id=1");
        DB::statement("UPDATE company_wallet SET type='referral' WHERE id=2");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_wallet', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
