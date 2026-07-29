<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SectionHeading;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * El escritorio declara sus widgets a mano, en vez de heredar todo lo que
 * encuentre el descubrimiento automático. Así el orden y el contenido de la
 * pantalla principal son una decisión visible y no un efecto secundario.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Escritorio';

    public function getWidgets(): array
    {
        // Al cobrador se le da su trabajo del día y nada más: los ingresos, la
        // rentabilidad por lavadora y la proyección son del dueño. Enseñárselos
        // no sólo sobra, le pone enfrente números que no le tocan.
        if (\App\Support\Acceso::esCobrador()) {
            return [
                // Sus tareas del día con el botón para hacerlas.
                \App\Filament\Widgets\PendientesWidget::class,
                SectionHeading::make([
                    'titulo' => 'Tu día',
                    'descripcion' => 'A quién hay que cobrarle antes de que se acabe el día.',
                ]),
                \App\Filament\Widgets\TodayStats::class,
                \App\Filament\Widgets\CollectionsWidget::class,
            ];
        }

        return [
            // Sólo aparece dentro del demo, y ahí va antes que nada: sin ella el
            // visitante se queda mirando gráficas y se va sin tocar un botón.
            \App\Filament\Widgets\GuiaDemoWidget::class,
            // Primeros pasos: se esconde solo cuando ya no hay pendientes.
            \App\Filament\Widgets\OnboardingWidget::class,
            // Lo que hay que HACER hoy, antes de los números. También se esconde
            // solo cuando no queda nada pendiente.
            \App\Filament\Widgets\PendientesWidget::class,

            SectionHeading::make([
                'titulo' => 'Hoy',
                'descripcion' => 'Lo que hay que atender antes de que se acabe el día.',
            ]),
            \App\Filament\Widgets\TodayStats::class,
            \App\Filament\Widgets\CollectionsWidget::class,

            SectionHeading::make([
                'titulo' => 'El dinero',
                'descripcion' => 'Qué entró, qué salió y cuánto te quedó este mes.',
            ]),
            \App\Filament\Widgets\PaymentStats::class,
            // Va justo debajo de los ingresos: sin esto, "Ingresos del Mes" se
            // lee como ganancia y no lo es.
            \App\Filament\Widgets\UtilidadStats::class,
            \App\Filament\Widgets\MonthlyRevenueChart::class,

            SectionHeading::make([
                'titulo' => 'Estado del negocio',
                'descripcion' => 'Tus lavadoras y qué tan bien están trabajando.',
            ]),
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\RentalStatusChart::class,
            \App\Filament\Widgets\MachineProfitabilityWidget::class,
            \App\Filament\Widgets\BusinessAnalyticsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
