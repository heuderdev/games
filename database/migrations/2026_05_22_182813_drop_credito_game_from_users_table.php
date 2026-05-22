<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // remove a coluna carteira_game se existir
            if (Schema::hasColumn('users', 'credito_game')) {
                $table->dropColumn('credito_game');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // recria a coluna credito_game para rollback
            if (! Schema::hasColumn('users', 'credito_game')) {
                $table->decimal('credito_game', 12, 2)
                    ->default(0)
                    ->after('remember_token');
            }
        });
    }
};
