<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\WashingMachine;
use Filament\Facades\Filament;

class StatsOverview extends BaseWidget
{
    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        $porEstado = $tenant->washingMachines()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $porEstado->sum();
        $rentadas = (int) $porEstado->get('rentada', 0);
        $libres = (int) $porEstado->get('disponible', 0);
        $mantenimiento = (int) $porEstado->get('mantenimiento', 0);

        // Cuántas secadoras hay, para que el recuadro no siga hablando sólo de
        // lavadoras cuando el parque ya trae de las dos.
        $secadoras = $tenant->washingMachines()->where('kind', 'secadora')->count();

        // Un solo recuadro en vez de cuatro: en el celular cada uno ocupaba
        // un renglón completo y el escritorio se volvía interminable.
        return [
            Stat::make('Equipos', $total)
                ->description(collect([
                    "{$rentadas} rentados",
                    "{$libres} libres",
                    $mantenimiento > 0 ? "{$mantenimiento} en mantenimiento" : null,
                    $secadoras > 0 ? "{$secadoras} son secadoras" : null,
                ])->filter()->join(' · '))
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),
        ];
    }
}
