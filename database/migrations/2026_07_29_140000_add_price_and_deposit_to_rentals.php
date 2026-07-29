<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El precio de cada renta y el depósito en garantía.
 *
 * Hasta ahora el precio vivía sólo en settings y era uno para toda la empresa: no
 * se podía cobrar distinto por una lavadora de 17 kg que por una de 12, ni hacerle
 * precio especial a un cliente. Peor, AccountStatement lo deducía del último pago
 * aplicado — una adivinanza que se rompe en cuanto alguien abona distinto.
 *
 * El depósito no existía en ninguna tabla, y en este giro casi siempre se pide.
 *
 * price va nullable a propósito: nulo significa "usa el de la empresa", que es lo
 * que corresponde a las 85 rentas que ya existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('status');
            $table->decimal('deposit', 10, 2)->default(0)->after('price');
            $table->decimal('deposit_returned', 10, 2)->nullable()->after('deposit');
            $table->timestamp('deposit_returned_at')->nullable()->after('deposit_returned');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['price', 'deposit', 'deposit_returned', 'deposit_returned_at']);
        });
    }
};
