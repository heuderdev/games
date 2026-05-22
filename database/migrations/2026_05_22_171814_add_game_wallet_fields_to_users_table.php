<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // saldo carregado (depósito real)
            $table->decimal('carteira_game', 12, 2)
                ->default(0)
                ->after('remember_token');

            // saldo para apostar dentro do jogo
            $table->decimal('credito_game', 12, 2)
                ->default(0)
                ->after('carteira_game');

            // saldo apenas de ganhos (saque só daqui)
            $table->decimal('winnings_balance', 12, 2)
                ->default(0)
                ->after('credito_game');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'carteira_game',
                'credito_game',
                'winnings_balance',
            ]);
        });
    }
};
