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
        Schema::create('played_games', function (Blueprint $table) {
            $table->id();
            $table->string('match_name', 1000);
            $table->string('player_1', 1000)->default('');
            $table->string('player_2', 1000)->default('');
            $table->string('player_3', 1000)->default('');
            $table->string('player_4', 1000)->default('');
            $table->string('player_5', 1000)->default('');
            $table->string('player_6', 1000)->default('');
            $table->string('winner', 1000)->default('');
            $table->integer('amount');
            $table->timestamp('time')->useCurrent()->useCurrentOnUpdate();
            $table->enum('match_type', [
                'free_game_ad',
                'robot_game',
                'multiplayer2',
                'multiplayer3',
                'multiplayer4',
                'JP',
                'TN',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('played_games');
    }
};
