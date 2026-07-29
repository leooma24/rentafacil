<?php

namespace App\Filament\Widgets;

use App\Support\DecisionDeCrecer;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * ¿Me alcanza para otra lavadora?
 *
 * La única decisión grande del negocio y la que menos apoyo tenía: todas las
 * gráficas del escritorio miran hacia atrás, y ninguna contesta la pregunta que
 * se hace parado en la tienda de electrodomésticos.
 */
class CrecerWidget extends Widget
{
    protected static string $view = 'filament.widgets.crecer';

    protected int|string|array $columnSpan = 'full';

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        // Sin equipo dado de alta la pregunta no aplica: lo que toca es dar de
        // alta el primero, y de eso ya se encarga el checklist de arranque.
        return $tenant !== null && $tenant->washingMachines()->exists();
    }

    public function getDecision(): DecisionDeCrecer
    {
        return DecisionDeCrecer::for(Filament::getTenant());
    }
}
