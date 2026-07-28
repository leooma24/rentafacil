<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Un rótulo entre los bloques del escritorio.
 *
 * El orden de los widgets ya contaba una historia —qué hacer hoy, el dinero, el
 * estado del negocio— pero no se veía: parecía una pila de tarjetas. Esto la
 * hace visible.
 *
 * No lleva tarjeta ni fondo a propósito: es un rótulo, y una tarjeta lo
 * volvería un bloque más de los que quiere separar.
 */
class SectionHeading extends Widget
{
    protected static string $view = 'filament.widgets.section-heading';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Lo llena Filament desde la configuración del widget en el Dashboard. */
    public string $titulo = '';

    public string $descripcion = '';
}
