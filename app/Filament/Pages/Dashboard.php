<?php

namespace App\Filament\Pages;

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
        return [
            // Hoy
            \App\Filament\Widgets\TodayStats::class,
            // A quién cobrar
            \App\Filament\Widgets\CollectionsWidget::class,
            // El dinero
            \App\Filament\Widgets\PaymentStats::class,
            \App\Filament\Widgets\MonthlyRevenueChart::class,
            // Estado del negocio
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
