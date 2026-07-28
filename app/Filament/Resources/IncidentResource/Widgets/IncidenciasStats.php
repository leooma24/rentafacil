<?php

namespace App\Filament\Resources\IncidentResource\Widgets;

use App\Filament\Resources\Widgets\CatalogoStats;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IncidenciasStats extends CatalogoStats
{
    protected function getStats(): array
    {
        $tenant = $this->tenant();

        $abiertas = $tenant->incidents()->where('status', 'abierta')->count();
        $enProgreso = $tenant->incidents()->where('status', 'en_progreso')->count();

        $altaSinCerrar = $tenant->incidents()
            ->where('priority', 'alta')
            ->whereIn('status', ['abierta', 'en_progreso'])
            ->count();

        // Cuánto tarda en resolverse un reporte: es la medida de servicio que el
        // cliente sí siente. Se mira a 90 días para que un mes flojo no la borre.
        $cerradas = $tenant->incidents()
            ->where('status', 'cerrada')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(90))
            // Un reporte cerrado antes de abrirse es un dato mal capturado, y
            // promediarlo saca días negativos. Se deja fuera en vez de tapar
            // el resultado con un valor absoluto que mentiría.
            ->whereColumn('resolved_at', '>=', 'created_at')
            ->get(['created_at', 'resolved_at']);

        $promedio = $cerradas->isEmpty()
            ? null
            : round($cerradas->avg(
                fn ($incidencia) => Carbon::parse($incidencia->created_at)
                    ->diffInDays(Carbon::parse($incidencia->resolved_at))
            ), 1);

        return [
            Stat::make('Abiertas', $abiertas)
                ->description($altaSinCerrar > 0 ? "{$altaSinCerrar} de prioridad alta" : 'ninguna urgente')
                ->descriptionIcon($abiertas > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($altaSinCerrar > 0 ? 'danger' : ($abiertas > 0 ? 'warning' : 'success')),

            Stat::make('En progreso', $enProgreso)
                ->description('ya con alguien atendiéndolas')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),

            Stat::make('Días para resolver', $promedio === null ? '—' : $promedio)
                ->description($promedio === null ? 'aún sin reportes cerrados' : 'promedio de los últimos 90 días')
                ->descriptionIcon('heroicon-m-clock')
                ->color(match (true) {
                    $promedio === null => 'gray',
                    $promedio <= 2 => 'success',
                    $promedio <= 5 => 'warning',
                    default => 'danger',
                }),
        ];
    }
}
