<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayedGame extends Model
{
    protected $table = 'played_games';

    public $timestamps = false;

    protected $guarded = [];

    const TYPE_FREE_AD = 'free_game_ad';

    const TYPE_ROBOT = 'robot_game';

    const TYPE_MULTI_2 = 'multiplayer2';

    const TYPE_MULTI_3 = 'multiplayer3';

    const TYPE_MULTI_4 = 'multiplayer4';

    const TYPE_JACKPOT = 'JP';

    const TYPE_TOURNAMENT = 'TN';

    public function getIncomeAttribute(): float
    {
        return $this->amount * 0.10;
    }
}
