<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_room_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained('game_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['entry_bet', 'platform_fee', 'winner_payout', 'refund']);
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['game_room_id', 'type']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('game_room_transactions');
    }
};
