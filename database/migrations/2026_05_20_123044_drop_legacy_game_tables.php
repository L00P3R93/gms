<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop in dependency order (children before parents)
        Schema::dropIfExists('toys');
        Schema::dropIfExists('bugs');
        Schema::dropIfExists('shop');
        Schema::dropIfExists('played_games');
        Schema::dropIfExists('games');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('accounts');
    }

    public function down(): void
    {
        // Restore is intentionally not implemented — use git history to recover.
    }
};
