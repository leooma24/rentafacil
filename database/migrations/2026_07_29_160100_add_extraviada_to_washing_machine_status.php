<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El estatus "extraviada" para los equipos.
 *
 * Pasa: el cliente se muda y se lleva la lavadora. Hasta ahora había que dejarla
 * como disponible o fuera de servicio, y en los dos casos seguía contando en el
 * inventario un aparato que ya no está.
 *
 * status es un ENUM de MySQL, así que agregar un valor obliga a redefinir la
 * columna entera. Va con SQL directo porque Doctrine no maneja enums nativos.
 */
return new class extends Migration
{
    private const VALORES_NUEVOS = "'disponible','rentada','mantenimiento','vendida','fuera_de_servicio','extraviada'";

    private const VALORES_VIEJOS = "'disponible','rentada','mantenimiento','vendida','fuera_de_servicio'";

    public function up(): void
    {
        DB::statement(
            "ALTER TABLE washing_machines MODIFY status ENUM(" . self::VALORES_NUEVOS . ") NOT NULL DEFAULT 'disponible'"
        );
    }

    public function down(): void
    {
        // Los extraviados vuelven a fuera de servicio: es lo más cercano y no se
        // puede dejar una fila con un valor que el enum ya no acepta.
        DB::table('washing_machines')
            ->where('status', 'extraviada')
            ->update(['status' => 'fuera_de_servicio']);

        DB::statement(
            "ALTER TABLE washing_machines MODIFY status ENUM(" . self::VALORES_VIEJOS . ") NOT NULL DEFAULT 'disponible'"
        );
    }
};
