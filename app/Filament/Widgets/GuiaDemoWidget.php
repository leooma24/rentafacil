<?php

namespace App\Filament\Widgets;

use App\Support\GuiaDelDemo;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * La guía del demo, arriba de todo y sólo dentro del demo.
 *
 * Va primero que el onboarding y que los pendientes a propósito: es lo primero
 * que ve el visitante y es lo único que va a convertir la visita en una prueba
 * de verdad.
 */
class GuiaDemoWidget extends Widget
{
    protected static string $view = 'filament.widgets.guia-demo';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) Filament::getTenant()?->is_demo;
    }

    public function getGuia(): GuiaDelDemo
    {
        return GuiaDelDemo::for(Filament::getTenant());
    }

    /**
     * Marca el paso y manda a la pantalla. Se hace aquí y no con un enlace
     * normal para no tener que inventar una ruta intermedia sólo por esto.
     */
    public function abrir(string $clave, string $url)
    {
        GuiaDelDemo::marcarVisto($clave);

        return redirect($url);
    }
}
