<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El recargo por atraso.
 *
 * El sistema sabe al día cuánto lleva atrasado cada quien, pero atrasarse salía
 * gratis. Ahora se puede cobrar por periodo vencido, fijo o en porcentaje, con
 * días de gracia.
 *
 * Arranca en CERO a propósito: con recargo en cero el cálculo del adeudo queda
 * idéntico al de hoy, así que las 17 empresas que ya usan la app no ven ningún
 * cambio hasta que ellas lo configuren. Un recargo que aparece solo, sin que el
 * dueño lo haya decidido, le rompe la relación con sus clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 10, 2)->default(0)->after('days_per_payment');
            $table->string('late_fee_type')->default('fijo')->after('late_fee_amount');
            $table->unsignedSmallInteger('late_fee_grace_days')->default(0)->after('late_fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['late_fee_amount', 'late_fee_type', 'late_fee_grace_days']);
        });
    }
};
