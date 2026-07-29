<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el cliente quedó debiendo cuando se le recogió el equipo.
 *
 * El adeudo nunca se guarda: se deduce de qué tan atrás quedó end_date. Eso está
 * bien mientras la renta siga abierta, pero recoger la ponía en "completada" y le
 * movía end_date a hoy, así que el saldo desaparecía por los dos lados a la vez.
 * Un moroso de tres semanas quedaba en ceros en el momento de irle a quitar la
 * lavadora, que es exactamente cuando hay que acordarse de lo que debe: cuando
 * vuelva a pedir una.
 *
 * Aquí se congela la cifra al cerrar. Deducirla después es imposible —end_date ya
 * se movió— y recalcularla tampoco serviría: lo que se debía ese día es un hecho
 * de ese día y no puede cambiar porque mañana se corrija un cobro viejo.
 *
 * debt_settled es la decisión del dueño: a veces te llevas la lavadora y ahí
 * quedó. Es una decisión suya y tiene que quedar escrita, no adivinada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('debt_at_close', 10, 2)->nullable()->after('deposit_returned_at');
            $table->boolean('debt_settled')->default(false)->after('debt_at_close');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['debt_at_close', 'debt_settled']);
        });
    }
};
