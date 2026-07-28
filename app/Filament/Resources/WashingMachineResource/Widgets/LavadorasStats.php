<?php

namespace App\Filament\Resources\WashingMachineResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LavadorasStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $tenant = $this->tenant();

        // Un solo recorrido en vez de una consulta por estado.
        $porEstado = $tenant->washingMachines()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $rentadas = (int) $porEstado->get('rentada', 0);
        $disponibles = (int) $porEstado->get('disponible', 0);
        $detenidas = (int) $porEstado->get('mantenimiento', 0)
            + (int) $porEstado->get('fuera_de_servicio', 0);

        // Las vendidas ya no son del parque: no cuentan para la ocupación.
        $parque = $rentadas + $disponibles + $detenidas;
        $ocupacion = $parque > 0 ? round($rentadas / $parque * 100, 1) : 0;

        return [
            Stat::make('Ocupación', $ocupacion . '%')
                ->description("{$rentadas} de {$parque} rentadas")
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($ocupacion >= 70 ? 'success' : ($ocupacion >= 40 ? 'warning' : 'danger')),

            Stat::make('Disponibles', $disponibles)
                ->description($disponibles > 0 ? 'paradas, sin generar renta' : 'ninguna parada')
                ->descriptionIcon($disponibles > 0 ? 'heroicon-m-inbox' : 'heroicon-m-check-circle')
                ->color($disponibles > 0 ? 'warning' : 'success'),

            Stat::make('Detenidas', $detenidas)
                ->description($detenidas > 0 ? 'en mantenimiento o fuera de servicio' : 'todas en condiciones')
                ->descriptionIcon($detenidas > 0 ? 'heroicon-m-wrench-screwdriver' : 'heroicon-m-check-circle')
                ->color($detenidas > 0 ? 'danger' : 'success'),
        ];
    }
}
