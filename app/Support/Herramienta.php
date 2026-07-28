<?php

namespace App\Support;

use Filament\Facades\Filament;

/**
 * Una herramienta de la app, contada desde lo que el dueño gana con ella.
 *
 * El título dice qué logra, no cómo se llama la pantalla: "Sal a cobrar en el
 * orden más corto" en vez de "Planificador de rutas". Quien no sabe que
 * necesita algo no lo busca por su nombre técnico.
 */
class Herramienta
{
    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly string $beneficio,
        public readonly string $comoSeUsa,
        public readonly ?string $pista,
        /** Provecho::USANDO, Provecho::SIN_ESTRENAR, o null cuando no hay huella que mirar. */
        public readonly ?string $estado,
        public readonly string $icono,
        public readonly string $ruta,
        public readonly string $accion,
        public readonly int $peso,
    ) {
    }

    public function url(): string
    {
        return route($this->ruta, ['tenant' => Filament::getTenant()]);
    }

    public function sinEstrenar(): bool
    {
        return $this->estado === Provecho::SIN_ESTRENAR;
    }

    public function usando(): bool
    {
        return $this->estado === Provecho::USANDO;
    }
}
