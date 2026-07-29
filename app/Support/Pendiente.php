<?php

namespace App\Support;

use Filament\Facades\Filament;

/**
 * Una tarea del día con el botón para hacerla.
 *
 * El título dice qué falta y cuánto; el detalle, por qué importa. Sin el porqué
 * la lista se vuelve ruido y se deja de leer.
 */
class Pendiente
{
    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly string $detalle,
        public readonly string $accion,
        public readonly string $icono,
        public readonly string $color,
        public readonly string $ruta,
    ) {
    }

    public function url(): string
    {
        return route($this->ruta, ['tenant' => Filament::getTenant()]);
    }
}
