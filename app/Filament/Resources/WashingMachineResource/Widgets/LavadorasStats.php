<?php

namespace App\Filament\Resources\WashingMachineResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LavadorasStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $tenant = $this->tenant();

        // Un solo recorrido en vez de una consulta por estado y tipo.
        $filas = $tenant->washingMachines()
            ->selectRaw('kind, status, count(*) as total')
            ->groupBy('kind', 'status')
            ->get();

        $cuantas = fn (string $estado) => (int) $filas->where('status', $estado)->sum('total');

        $rentadas = $cuantas('rentada');
        $disponibles = $cuantas('disponible');
        $detenidas = $cuantas('mantenimiento') + $cuantas('fuera_de_servicio');

        // Las vendidas ya no son del parque: no cuentan para la ocupación.
        $parque = $rentadas + $disponibles + $detenidas;
        $ocupacion = $parque > 0 ? round($rentadas / $parque * 100, 1) : 0;

        return [
            Stat::make('Ocupación', $ocupacion . '%')
                ->description($this->desglosePorTipo($filas) ?? "{$rentadas} de {$parque} rentadas")
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

    /**
     * "8/10 lavadoras · 2/4 secadoras", pero sólo cuando hay más de un tipo.
     *
     * A quien nada más renta lavadoras el desglose le sobra y le quita lugar al
     * dato: en ese caso devuelve null y la tarjeta usa su texto de siempre.
     */
    private function desglosePorTipo(\Illuminate\Support\Collection $filas): ?string
    {
        $tipos = $filas->pluck('kind')->unique()->filter();

        if ($tipos->count() < 2) {
            return null;
        }

        return $tipos
            ->sort()
            ->map(function (string $tipo) use ($filas) {
                $delTipo = $filas->where('kind', $tipo);
                $rentadas = (int) $delTipo->where('status', 'rentada')->sum('total');
                // Ni las vendidas ni las extraviadas son del parque: contarlas
                // hundiría la ocupación con aparatos que ya no están.
                $parque = (int) $delTipo
                    ->whereIn('status', ['rentada', 'disponible', 'mantenimiento', 'fuera_de_servicio'])
                    ->sum('total');

                $nombre = \App\Models\WashingMachine::KINDS_PLURAL[$tipo] ?? $tipo;

                return "{$rentadas}/{$parque} {$nombre}";
            })
            ->join(' · ');
    }
}
