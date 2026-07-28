<?php

namespace App\Filament\Resources\ExpenseResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use App\Support\Utilidad;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GastosStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $utilidad = Utilidad::delMes($this->tenant());
        $margen = $utilidad->margen();

        $mayor = collect($utilidad->porCategoria($this->tenant()))->take(1);
        $categoria = $mayor->keys()->first();
        $monto = $mayor->first();

        return [
            Stat::make('Gastado este mes', $this->dinero($utilidad->salidas()))
                ->description($utilidad->mantenimiento > 0
                    ? 'incluye ' . $this->dinero($utilidad->mantenimiento) . ' de mantenimiento'
                    : 'gasolina, sueldos, refacciones y demás')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($utilidad->salidas() > 0 ? 'warning' : 'success'),

            // El número que faltaba: lo que cobró menos lo que gastó.
            Stat::make('Te quedó', $this->dinero($utilidad->ganancia()))
                ->description($margen === null
                    ? 'todavía no hay cobros este mes'
                    : ($utilidad->pierde()
                        ? 'este mes vas perdiendo'
                        : "te queda el {$margen}% de lo que cobras"))
                ->descriptionIcon($utilidad->pierde() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-banknotes')
                ->color($utilidad->pierde() ? 'danger' : 'success'),

            Stat::make('En qué se va más', $categoria
                ? (\App\Models\Expense::CATEGORIAS[$categoria] ?? ucfirst($categoria))
                : '—')
                ->description($monto ? $this->dinero($monto) . ' este mes' : 'sin gastos registrados')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('info'),
        ];
    }
}
