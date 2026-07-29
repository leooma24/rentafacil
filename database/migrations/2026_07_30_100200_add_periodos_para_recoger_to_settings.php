<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuántos periodos sin pagar antes de que sea ir por el equipo.
 *
 * Hasta ahora el sistema trataba igual al que se pasó tres días y al que lleva un
 * mes: los dos recibían el mismo recordatorio por WhatsApp. Pero no son la misma
 * conversación. Al de tres días se le avisa; al del mes se le recoge.
 *
 * Arranca en 2, que es lo que hace el negocio: se renta por semana, y a la
 * segunda que no cae el pago se va por la lavadora. Va en settings y no fijo en
 * el código porque la tolerancia de cada rentador es distinta, y equivocarse por
 * el lado duro le cuesta un cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('periodos_para_recoger')
                ->default(2)
                ->after('late_fee_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('periodos_para_recoger');
        });
    }
};
