<?php

namespace App\Filament\Resources\MaintenanceResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MantenimientosStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $tenant = $this->tenant();

        $programados = $tenant->maintenances()->where('status', 'programada')->count();

        // Los que ya empezaron y siguen abiertos: son lavadoras detenidas.
        $atrasados = $tenant->maintenances()
            ->where('status', 'programada')
            ->whereDate('start_date', '<', today())
            ->count();

        $delMes = fn () => $tenant->maintenances()
            ->whereYear('start_date', now()->year)
            ->whereMonth('start_date', now()->month);

        $completados = $delMes()->where('status', 'completado')->count();
        $costo = (float) $delMes()->sum('cost');

        return [
            Stat::make('Programados', $programados)
                ->description($atrasados > 0 ? "{$atrasados} con fecha ya pasada" : 'ninguno atrasado')
                ->descriptionIcon($atrasados > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-wrench-screwdriver')
                ->color($atrasados > 0 ? 'danger' : ($programados > 0 ? 'warning' : 'success')),

            Stat::make('Completados este mes', $completados)
                ->description('servicios terminados')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            // Lo que cuesta mantener el parque: sin esto la rentabilidad por
            // lavadora se ve mejor de lo que es.
            Stat::make('Costo del mes', $this->dinero($costo))
                ->description('gastado en mantenimiento')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
        ];
    }
}
