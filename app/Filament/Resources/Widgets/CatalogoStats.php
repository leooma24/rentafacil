<?php

namespace App\Filament\Resources\Widgets;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;

/**
 * Base de los recuadros que encabezan cada catálogo.
 *
 * Van en el encabezado de la página del listado y siempre miran al catálogo
 * completo, no a lo que quedó filtrado en la tabla: sirven para decidir dónde
 * pararse, y para eso el filtro estorbaría.
 *
 * Vive fuera de app/Filament/Widgets a propósito. Esa carpeta la recorre
 * discoverWidgets() del panel y estos recuadros no tienen nada que hacer en el
 * escritorio: cada uno se declara a mano en su getHeaderWidgets().
 */
abstract class CatalogoStats extends StatsOverviewWidget
{
    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function tenant(): Company
    {
        return Filament::getTenant();
    }

    /** Los pagos traen company_id propio; la relación de Company va por rentas. */
    protected function pagos()
    {
        return \App\Models\Payment::where('company_id', $this->tenant()->id)
            ->where('status', 'completado');
    }

    protected function dinero(float $monto): string
    {
        return '$' . number_format($monto, 2);
    }
}
