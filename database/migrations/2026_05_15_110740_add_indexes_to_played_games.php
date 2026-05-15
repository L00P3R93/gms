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
        Schema::table('played_games', function (Blueprint $table) {
            $table->index('match_type');
            $table->index('time');
            $table->index(['match_type', 'time']);
        });

        // winner is varchar(1000); MySQL needs a prefix index to stay within the 3072-byte key limit
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE played_games ADD INDEX played_games_winner_index (winner(191))');
        } else {
            Schema::table('played_games', fn (Blueprint $table) => $table->index('winner'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('played_games', function (Blueprint $table) {
            $table->dropIndex(['match_type']);
            $table->dropIndex(['time']);
            $table->dropIndex(['match_type', 'time']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE played_games DROP INDEX played_games_winner_index');
        } else {
            Schema::table('played_games', fn (Blueprint $table) => $table->dropIndex(['winner']));
        }
    }
};
