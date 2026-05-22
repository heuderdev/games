<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `game_room_transactions`
            MODIFY COLUMN `type` ENUM(
                'entry_bet',
                'platform_fee',
                'winner_payout',
                'refund',
                'draw_refund'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `game_room_transactions`
            MODIFY COLUMN `type` ENUM(
                'entry_bet',
                'platform_fee',
                'winner_payout',
                'refund'
            ) NOT NULL
        ");
    }
};
