<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El estatus "en_revision" para el equipo que acaba de regresar.
 *
 * Recoger lo dejaba disponible en el mismo instante, así que la siguiente renta
 * podía salir con un aparato que nadie abrió. En este negocio la lavadora regresa
 * sucia, con la manguera mordida o sin la tapa, y eso se descubre en la puerta
 * del cliente siguiente.
 *
 * No cuenta como disponible —no aparece para rentar— pero sí sigue siendo parque
 * activo: es un aparato que se tiene y que va a volver a trabajar.
 *
 * status es un ENUM de MySQL, así que agregar un valor obliga a redefinir la
 * columna entera. Va con SQL directo porque Doctrine no maneja enums nativos.
 */
return new class extends Migration
{
    private const VALORES_NUEVOS = "'disponible','rentada','en_revision','mantenimiento','vendida','fuera_de_servicio','extraviada'";

    private const VALORES_VIEJOS = "'disponible','rentada','mantenimiento','vendida','fuera_de_servicio','extraviada'";

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE washing_machines MODIFY status ENUM(' . self::VALORES_NUEVOS . ") NOT NULL DEFAULT 'disponible'"
        );
    }

    public function down(): void
    {
        // Los que estén en revisión vuelven a disponible: es de donde salieron y
        // no se puede dejar una fila con un valor que el enum ya no acepta.
        DB::table('washing_machines')
            ->where('status', 'en_revision')
            ->update(['status' => 'disponible']);

        DB::statement(
            'ALTER TABLE washing_machines MODIFY status ENUM(' . self::VALORES_VIEJOS . ") NOT NULL DEFAULT 'disponible'"
        );
    }
};
