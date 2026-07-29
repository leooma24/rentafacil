<?php

namespace App\Filament\Widgets;

use App\Support\PanoramaSaaS;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dónde se quedan las cuentas, de registrarse a cobrar.
 *
 * Es el número que decide en qué vale la pena trabajar. Con 17 registradas, 6
 * que cargaron un equipo y una sola que ha cobrado, el problema no es que falten
 * funciones: es que la gente no llega a usar las que hay.
 */
class EmbudoDeCuentas extends BaseWidget
{
    protected static ?int $sort = 0;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $panorama = PanoramaSaaS::actual();

        $paso = fn (string $titulo, int $cuantas, string $detalle, string $icono) => Stat::make(
            $titulo,
            (string) $cuantas
        )
            ->description($panorama->registradas > 0
                ? $panorama->porcentaje($cuantas) . '% · ' . $detalle
                : $detalle)
            ->descriptionIcon($icono)
            ->color(match (true) {
                $panorama->porcentaje($cuantas) >= 70 => 'success',
                $panorama->porcentaje($cuantas) >= 35 => 'warning',
                default => 'danger',
            });

        return [
            Stat::make('Cuentas registradas', (string) $panorama->registradas)
                ->description('sin contar las demos')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),

            $paso('Cargaron equipo', $panorama->conEquipo, 'dieron el primer paso', 'heroicon-m-archive-box'),
            $paso('Están rentando', $panorama->rentando, 'asignaron un equipo a un cliente', 'heroicon-m-users'),
            $paso('Han cobrado', $panorama->cobrando, 'la app entró a su rutina', 'heroicon-m-banknotes'),
        ];
    }
}
