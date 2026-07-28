<?php

namespace App\Support;

/**
 * Quién es quién dentro del panel.
 *
 * Existe para que la regla se escriba una sola vez: qué pantallas son del dueño
 * y cuáles también le tocan a quien sale a cobrar. Repartida por cada página,
 * tarde o temprano una se queda sin candado.
 */
class Acceso
{
    /** Pantallas de dueño: precios, plan, reportes, prospección. */
    public static function soloDueno(): bool
    {
        return auth()->user()?->hasAnyRole(['propietario', 'super_admin']) ?? false;
    }

    public static function esCobrador(): bool
    {
        $usuario = auth()->user();

        // Un dueño que además trae el rol de cobrador sigue siendo dueño.
        return $usuario !== null
            && $usuario->hasRole('cobrador')
            && ! $usuario->hasAnyRole(['propietario', 'super_admin']);
    }
}
