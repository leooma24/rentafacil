<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién registró cada cobro.
 *
 * Sin esto el corte de caja sólo puede decir cuánto entró, no quién lo trae
 * encima, que es justo lo que hace falta cuando el dueño manda a alguien más a
 * cobrar.
 *
 * Va nulo para los 641 cobros que ya existen: de esos no hay forma de saberlo,
 * y adivinarlo sería peor que dejarlo en blanco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('collected_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
        });
    }
};
