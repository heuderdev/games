<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->enum('mode', ['human_vs_human', 'human_vs_bot'])
                ->default('human_vs_human')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
