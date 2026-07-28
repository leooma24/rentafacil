<?php

namespace App\Filament\Widgets;

use App\Support\Utilidad;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que quedó del mes.
 *
 * Va debajo de los ingresos a propósito: el escritorio decía "Ingresos del Mes"
 * y ahí paraba, así que ese número se leía como ganancia. Aquí se le restan los
 * gastos y el mantenimiento para que diga lo que de verdad quedó.
 */
class UtilidadStats extends BaseWidget
{
    protected static ?int $sort = 4;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $utilidad = Utilidad::delMes($tenant);
        $margen = $utilidad->margen();

        $dinero = fn (float $monto) => '$' . number_format($monto, 2);

        return [
            Stat::make('Entró este mes', $dinero($utilidad->ingresos))
                ->description('cobros registrados')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Salió este mes', $dinero($utilidad->salidas()))
                ->description($utilidad->gananciaInflada()
                    ? 'sin gastos anotados: lo de abajo sale de más'
                    : 'gastos y mantenimiento')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($utilidad->gananciaInflada() ? 'danger' : 'warning'),

            Stat::make('Te quedó', $dinero($utilidad->ganancia()))
                ->description($margen === null
                    ? 'todavía no hay cobros este mes'
                    : ($utilidad->pierde()
                        ? 'este mes vas perdiendo'
                        : "el {$margen}% de lo que cobraste"))
                ->descriptionIcon($utilidad->pierde() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-banknotes')
                ->color($utilidad->pierde() ? 'danger' : 'success'),
        ];
    }
}
