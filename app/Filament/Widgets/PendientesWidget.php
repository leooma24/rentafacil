<?php

namespace App\Filament\Widgets;

use App\Support\PendientesDelDia;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Las tareas del día con su botón, arriba de todo.
 *
 * El escritorio decía cuánto le deben y quién, pero no lo que hay que HACER:
 * registrar una entrega, salir a cobrar en orden, cerrar la caja. Son cosas que
 * sólo se hacen si algo te las pone enfrente.
 *
 * Se esconde solo cuando no hay nada pendiente, igual que el de primeros pasos.
 */
class PendientesWidget extends Widget
{
    protected static ?int $sort = -1;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.pendientes';

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant && PendientesDelDia::for($tenant)->hayPendientes();
    }

    public function getViewData(): array
    {
        return ['pendientes' => PendientesDelDia::for(Filament::getTenant())->pendientes];
    }
}
