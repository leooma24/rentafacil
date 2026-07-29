<?php

namespace App\Filament\Widgets;

use App\Support\PanoramaSaaS;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que hay que atender del lado del negocio, no del de los rentadores.
 *
 * Pruebas que se acaban, cuentas que llegaron a media rampa y prospectos sin
 * marcar. Cada uno lleva a la pantalla donde se hace algo al respecto.
 */
class SeguimientoDelNegocio extends BaseWidget
{
    protected static ?int $sort = 2;

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $porVencer = PanoramaSaaS::pruebasPorVencer();
        $sinCobrar = PanoramaSaaS::sinCobrar();
        $prospectos = PanoramaSaaS::prospectosSinContactar();
        $demos = PanoramaSaaS::demosRecientes();

        return [
            Stat::make('Pruebas por vencer', (string) $porVencer->count())
                ->description($porVencer->isNotEmpty()
                    ? 'la más próxima: ' . $porVencer->first()->name
                    : 'ninguna se acaba esta semana')
                ->descriptionIcon('heroicon-m-clock')
                ->color($porVencer->isNotEmpty() ? 'warning' : 'success'),

            // Llegaron más lejos que las atoradas: entendieron para qué era y se
            // quedaron a un paso.
            Stat::make('Cargaron pero no cobran', (string) $sinCobrar->count())
                ->description('se quedaron a un paso de usarla en serio')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($sinCobrar->isNotEmpty() ? 'warning' : 'success'),

            Stat::make('Prospectos sin marcar', (string) $prospectos)
                ->description($prospectos > 0 ? 'están esperando' : 'ninguno pendiente')
                ->descriptionIcon('heroicon-m-phone')
                ->color($prospectos > 0 ? 'danger' : 'success')
                ->url($prospectos > 0
                    ? route('filament.propietario.pages.contactar', ['tenant' => \Filament\Facades\Filament::getTenant()])
                    : null),

            Stat::make('Demos de los últimos 30 días', (string) $demos)
                ->description('gente que entró a probarla sin registrarse')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),
        ];
    }
}
