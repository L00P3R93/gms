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
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('cur_theame_bg', 'cur_theme_bg');
            $table->renameColumn('currect_deck_cards', 'current_deck_cards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('cur_theme_bg', 'cur_theame_bg');
            $table->renameColumn('current_deck_cards', 'currect_deck_cards');
        });
    }
};
