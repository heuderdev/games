<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_room_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained('game_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('cell');
            $table->unsignedTinyInteger('turn_number');
            $table->timestamps();

            $table->unique(['game_room_id', 'turn_number']);
            $table->unique(['game_room_id', 'cell']);
            $table->index(['game_room_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_room_moves');
    }
};
