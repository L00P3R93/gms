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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 1000);
            $table->string('phone', 1000);
            $table->string('email', 1000);
            $table->string('password', 1000);
            $table->string('pic', 1000)->nullable()->default('profilepic.png');
            $table->bigInteger('credit')->default(0);
            $table->bigInteger('vcoins')->default(0);
            $table->string('outh', 1100)->nullable();
            $table->integer('free_games')->default(0);
            $table->string('extra_games', 11)->nullable();
            $table->timestamp('reset_time')->useCurrent()->useCurrentOnUpdate();
            $table->tinyInteger('music')->default(1);
            $table->tinyInteger('notifications')->default(1);
            $table->tinyInteger('effects')->default(1);
            $table->timestamp('reset_15')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('reset_50')->useCurrent()->useCurrentOnUpdate();
            $table->longText('acquired')->nullable();
            $table->string('cur_theame_bg', 1000)->default('default.png');
            $table->string('current_table_bg', 1000)->default('default.png');
            $table->string('currect_deck_cards', 1000)->default('default.png');
            $table->string('current_vip', 1000)->default('default.png');
            // Jackpot ticket counts (Grand)
            $table->integer('jp_g_1')->default(0);
            $table->integer('jp_g_20')->default(0);
            $table->integer('jp_g_50')->default(0);
            $table->integer('jp_g_100')->default(0);
            // Jackpot ticket counts (Silver)
            $table->integer('jp_s_20')->default(0);
            $table->integer('jp_s_50')->default(0);
            $table->integer('jp_s_100')->default(0);
            // Jackpot ticket counts (Bronze)
            $table->integer('jp_b_20')->default(0);
            $table->integer('jp_b_50')->default(0);
            $table->integer('jp_b_100')->default(0);
            // Tournament ticket counts
            $table->integer('tn_5_10')->default(0);
            $table->integer('tn_5_20')->default(0);
            $table->integer('tn_5_50')->default(0);
            $table->integer('tn_5_100')->default(0);
            $table->integer('tn_5_250')->default(0);
            $table->integer('tn_5_500')->default(0);
            $table->integer('tn_5_1000')->default(0);
            $table->integer('tn_4_10')->default(0);
            $table->integer('tn_4_20')->default(0);
            $table->integer('tn_4_50')->default(0);
            $table->integer('tn_4_100')->default(0);
            $table->integer('tn_4_250')->default(0);
            $table->integer('tn_4_500')->default(0);
            $table->integer('tn_4_1000')->default(0);
            $table->integer('tn_3_10')->default(0);
            $table->integer('tn_3_20')->default(0);
            $table->integer('tn_3_50')->default(0);
            $table->integer('tn_3_100')->default(0);
            $table->integer('tn_3_250')->default(0);
            $table->integer('tn_3_500')->default(0);
            $table->integer('tn_3_1000')->default(0);
            $table->integer('mils')->default(0);
            // Tournament wallet addresses
            $table->string('tn_5_10_wallet', 100)->nullable();
            $table->string('tn_5_20_wallet', 100)->nullable();
            $table->string('tn_5_50_wallet', 100)->nullable();
            $table->string('tn_5_100_wallet', 100)->nullable();
            $table->string('tn_5_250_wallet', 100)->nullable();
            $table->string('tn_5_500_wallet', 100)->nullable();
            $table->string('tn_5_1000_wallet', 100)->nullable();
            $table->string('tn_4_10_wallet', 100)->nullable();
            $table->string('tn_4_20_wallet', 100)->nullable();
            $table->string('tn_4_50_wallet', 100)->nullable();
            $table->string('tn_4_100_wallet', 100)->nullable();
            $table->string('tn_4_250_wallet', 100)->nullable();
            $table->string('tn_4_500_wallet', 100)->nullable();
            $table->string('tn_4_1000_wallet', 100)->nullable();
            $table->string('tn_3_10_wallet', 100)->nullable();
            $table->string('tn_3_20_wallet', 100)->nullable();
            $table->string('tn_3_50_wallet', 100)->nullable();
            $table->string('tn_3_100_wallet', 100)->nullable();
            $table->string('tn_3_250_wallet', 100)->nullable();
            $table->string('tn_3_500_wallet', 100)->nullable();
            $table->string('tn_3_1000_wallet', 100)->nullable();
            // Jackpot wallet addresses
            $table->string('jp_b_20_wallet', 100)->nullable();
            $table->string('jp_b_50_wallet', 100)->nullable();
            $table->string('jp_b_100_wallet', 100)->nullable();
            $table->string('jp_s_20_wallet', 100)->nullable();
            $table->string('jp_s_50_wallet', 100)->nullable();
            $table->string('jp_s_100_wallet', 100)->nullable();
            $table->string('jp_g_1_wallet', 100)->nullable();
            $table->string('jp_g_20_wallet', 100)->nullable();
            $table->string('jp_g_50_wallet', 100)->nullable();
            $table->string('jp_g_100_wallet', 100)->nullable();
            $table->integer('game_status')->default(1);
            $table->integer('match_status')->default(0);
            $table->string('google_id', 100)->nullable();
            $table->string('ref_code', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
