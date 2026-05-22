<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_room_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained('game_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['player', 'spectator'])->default('spectator');
            $table->enum('symbol', ['X', 'O'])->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['game_room_id', 'user_id']);
            $table->index(['game_room_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_room_participants');
    }
};
