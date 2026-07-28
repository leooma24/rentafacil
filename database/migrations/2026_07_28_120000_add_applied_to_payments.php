<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue el abono del cobro completo.
 *
 * Por omisión verdadero: todos los pagos que ya existen extendieron una renta,
 * así que nacen aplicados y ningún saldo cambia con esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('applied')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('applied');
        });
    }
};
