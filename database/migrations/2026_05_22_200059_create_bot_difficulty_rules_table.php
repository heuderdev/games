<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_difficulty_rules', function (Blueprint $table) {
            $table->id();

            // Data específica (YYYY-MM-DD). Null = qualquer dia.
            $table->date('date')->nullable();

            // Dia da semana opcional (0=domingo ... 6=sábado). Null = qualquer dia da semana.
            $table->unsignedTinyInteger('weekday')->nullable();

            // Janela de horário (HH:MM:SS)
            $table->time('start_time'); // ex: 00:00:00
            $table->time('end_time');   // ex: 23:59:59

            // Nível de dificuldade
            $table->enum('difficulty', [
                'idiot',
                'easy',
                'medium',
                'hard',
                'hardcore',
            ]);

            // prioridade caso tenha mais de uma regra que se aplica
            $table->unsignedInteger('priority')->default(100);

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_difficulty_rules');
    }
};
