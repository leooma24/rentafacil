<?php

namespace App\Filament\Widgets;

use App\Support\LatidoDeTareas;
use App\Support\RevisionDeDatos;
use Filament\Widgets\Widget;

/**
 * Si el sistema está trabajando o nada más lo parece.
 *
 * Una tarea caída no se ve: si marcar vencidas se muere, nadie aparece como
 * vencido, los avisos salen vacíos y la cobranza sale limpia. Una semana en que
 * todos pagaron y una semana con el sistema roto se ven idénticas en el panel, y
 * eso es exactamente lo que las vuelve peligrosas.
 *
 * Por eso va aquí y no sólo por correo: el correo se puede filtrar, la campana se
 * puede no abrir, pero esta pantalla es la que se mira para saber cómo va el
 * negocio.
 */
class SaludDelSistema extends Widget
{
    protected static string $view = 'filament.widgets.salud-del-sistema';

    protected int|string|array $columnSpan = 'full';

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public function getTareas()
    {
        return LatidoDeTareas::estado();
    }

    public function getConProblema()
    {
        return LatidoDeTareas::conProblema();
    }

    public function getDatos()
    {
        return RevisionDeDatos::todasLasCuentas();
    }
}
