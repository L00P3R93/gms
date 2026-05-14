<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users.status
        DB::statement("UPDATE users SET status = CASE status WHEN 1 THEN 'active' WHEN 2 THEN 'blocked' WHEN 3 THEN 'suspended' ELSE 'active' END");
        Schema::table('users', fn (Blueprint $t) => $t->string('status', 20)->default('active')->change());

        // holders.status
        DB::statement("UPDATE holders SET status = CASE status WHEN 1 THEN 'active' WHEN 2 THEN 'inactive' ELSE 'active' END");
        Schema::table('holders', fn (Blueprint $t) => $t->string('status', 20)->default('active')->change());

        // dependants.status
        DB::statement("UPDATE dependants SET status = CASE status WHEN 1 THEN 'active' WHEN 2 THEN 'inactive' ELSE 'active' END");
        Schema::table('dependants', fn (Blueprint $t) => $t->string('status', 20)->default('active')->change());

        // withdraws.status
        DB::statement("UPDATE withdraws SET status = CASE status WHEN 1 THEN 'pending' WHEN 2 THEN 'processing' WHEN 3 THEN 'failed' ELSE 'pending' END");
        Schema::table('withdraws', fn (Blueprint $t) => $t->string('status', 20)->default('pending')->change());

        // company_withdraws.status
        DB::statement("UPDATE company_withdraws SET status = CASE status WHEN 1 THEN 'pending' WHEN 2 THEN 'processing' WHEN 3 THEN 'failed' ELSE 'pending' END");
        Schema::table('company_withdraws', fn (Blueprint $t) => $t->string('status', 20)->default('pending')->change());

        // expenses.category
        DB::statement("UPDATE expenses SET category = CASE category WHEN 1 THEN 'income' WHEN 2 THEN 'expense' ELSE 'expense' END");
        Schema::table('expenses', fn (Blueprint $t) => $t->string('category', 20)->default('expense')->change());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->integer('status')->default(1)->change());
        Schema::table('holders', fn (Blueprint $t) => $t->integer('status')->default(1)->change());
        Schema::table('dependants', fn (Blueprint $t) => $t->integer('status')->default(1)->change());
        Schema::table('withdraws', fn (Blueprint $t) => $t->integer('status')->default(1)->change());
        Schema::table('company_withdraws', fn (Blueprint $t) => $t->integer('status')->default(1)->change());
        Schema::table('expenses', fn (Blueprint $t) => $t->integer('category')->default(2)->change());
    }
};
