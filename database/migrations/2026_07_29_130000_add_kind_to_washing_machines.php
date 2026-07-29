<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué clase de aparato es: lavadora, secadora o combo.
 *
 * El negocio también renta secadoras, y hasta ahora sólo cabían como un *tipo* de
 * lavadora ("Lavadora-secadora" en la columna type). Con eso el inventario, la
 * ocupación y el "qué le renté a quién" quedaban mal contados.
 *
 * La tabla se sigue llamando washing_machines a propósito: Filament Shield guarda
 * los permisos como cadenas (view_any_washing::machine y demás) y ya están
 * asignados a los roles de 43 usuarios en producción. Renombrar obligaría a migrar
 * esas asignaciones, y no vale el riesgo por un nombre interno.
 *
 * Los 60 aparatos que ya existen quedan como lavadora, que es lo que son.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('washing_machines', function (Blueprint $table) {
            $table->string('kind')->default('lavadora')->after('type');

            // Todas las cuentas por tipo van filtradas por empresa.
            $table->index(['company_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('washing_machines', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
